# PSR15 implementation of the CMS HealthCheck RFC

## Introduction

To have a PSR15 compatible request handler for the [CMS health check RFC](https://github.com/cms-health-project/health-check-rfc) this repo is built using [https://github.com/eqsgroup/health-check-provider]() as a base but using [https://github.com/cms-health-project/serializable-reference-implementation]() for the actual implementation of the output schema which got slightly adjusted from the original RFC.

This was created during [CloudFest Hackathon 2025](https://hackathon.cloudfest.com/project/cms-health-checks-2025/).

## Installation

To use this library in your project or library, require it with:

```terminal
composer require "cms-health-project/psr15-implementation"
```

## Usage

To use this in your application, you'd typically register a new route like e.g. `GET /health` which can then use the [RequestHandler](src/RequestHandler.php) to process the request.

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
);

$response = $handler->handle($request);
```

Take a look at the tests for more usage examples.