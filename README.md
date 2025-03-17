# PSR15 implementation of the CMS HealthCheck RFC

## Introduction

To have a [PSR-15 compatible request handler](https://www.php-fig.org/psr/psr-15/) for the [CMS health check RFC](https://github.com/cms-health-project/health-check-rfc) this repo is built using [eqsgroup/health-check-provider](https://github.com/eqsgroup/health-check-provider) as a base but using [cms-health-project/serializable-reference-implementation](https://github.com/cms-health-project/serializable-reference-implementation) for the actual implementation of the output schema which got slightly adjusted from the original RFC.

This was created during [CloudFest Hackathon 2025](https://hackathon.cloudfest.com/project/cms-health-checks-2025/).

## Installation

To use this library in your project or library, require it with:

```terminal
composer require "cms-health-project/psr15-implementation"
```

## Usage

To use this in your application, you'd typically register a new route like e.g. `GET /health` which can then use the [RequestHandler](/src/RequestHandler.php) to process the request.

To configure your health checks you have two options.
You can pass instances of [HealthCheckerInterface](/src/HealthChecker/HealthCheckerInterface.php) directly to the constructor of [RequestHandler](/src/RequestHandler.php).
It also accepts a [PSR-14 Event Dispatcher](https://www.php-fig.org/psr/psr-14/) which allows you to register some event listeners/subscribers that react to the [CollectHealthCheckResultsEvent](/src/EventDispatcher/CollectHealthCheckResultsEvent.php).

```php
$checks = [
    new CallableHealthChecker(
        'callable:responseTime',
        fn () => true,
        'your-component-id',
        'your-component-type',
        500,
    ),
    new DoctrineConnectionHealthChecker(
        $connection,
    ),
    new HttpHealthChecker(
        'http:request',
        new Client(),
        new Request('GET', '/some/endpoint'),
    ),
];

$handler = new RequestHandler(
    new ResponseFactory(),
    new StreamFactory(),
    'your-service-id',
    'your-service-description',
    $checks,
    eventDispatcher: $eventDispatcher,
);

$response = $handler->handle($request);
```

Take a look at the [tests](/tests) for more usage examples.