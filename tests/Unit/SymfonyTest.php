<?php

declare(strict_types=1);

namespace CmsHealthProject\Psr15Implementation\Tests\Unit;

use CmsHealthProject\Psr15Implementation\HealthChecker\CallableHealthChecker;
use CmsHealthProject\Psr15Implementation\RequestHandler;
use Http\Discovery\Psr17Factory;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class SymfonyTest extends WebTestCase
{
    use MatchesSnapshots;

    public function testInvoke(): void
    {
        $kernel = new class ('test', false) extends Kernel {
            private ?string $projectDir = null;

            /** @return iterable<Bundle> */
            public function registerBundles(): iterable
            {
                return [new FrameworkBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader)
            {
                $loader->load(static function (ContainerBuilder $container): void {
                    $container->loadFromExtension('framework', [
                        'test' => null,
                        'router' => ['resource' => 'kernel::loadRoutes', 'type' => 'service', 'utf8' => true],
                    ]);

                    $container->register('kernel', self::class)
                        ->addTag('routing.route_loader')
                        ->setAutoconfigured(true)
                        ->setPublic(true);
                });
            }

            public function loadRoutes(LoaderInterface $loader): RouteCollection
            {
                $collection = new RouteCollection();
                $collection->add('healthcheck', new Route('/api/health_check', ['_controller' => 'kernel::index']));

                return $collection;
            }

            public function index(Request $request): Response
            {
                $healthChecker = new CallableHealthChecker(
                    'example:check',
                    fn () => true,
                    clock: new MockClock('2024-01-01 00:01:00'),
                );

                $psr17Factory = new Psr17Factory();
                $requestHandler = new RequestHandler(
                    $psr17Factory,
                    $psr17Factory,
                    'test-service',
                    'test-desciption',
                    [$healthChecker],
                    new MockClock('2025-03-16 15:22:42'),
                );

                $requestFactory = new PsrHttpFactory();
                $psrRequest = $requestFactory->createRequest($request);
                $responseFactory = new HttpFoundationFactory();

                return $responseFactory->createResponse(
                    ($requestHandler)->handle($psrRequest)
                );
            }

            public function getProjectDir(): string
            {
                return $this->projectDir ??= sys_get_temp_dir() . '/sf_kernel_' . md5((string) mt_rand());
            }
        };
        $kernel->boot();

        $client = $kernel->getContainer()->get('test.client');
        self::assertInstanceOf(KernelBrowser::class, $client);

        $client->request('GET', '/api/health_check');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        $this->assertMatchesJsonSnapshot($client->getResponse()->getContent());
    }

    protected function restoreExceptionHandler(): void
    {
        while (true) {
            $previousHandler = set_exception_handler(static fn () => null);

            restore_exception_handler();

            if ($previousHandler === null) {
                break;
            }

            restore_exception_handler();
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreExceptionHandler();
    }
}
