<?php

declare(strict_types=1);

use Capell\Core\Testing\Assertions\AssertsDeclaredThrottles;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Routing\RouteCollection;

uses(SiteDiscoveryTestCase::class);

it('registers every throttle its manifest declares, on the routes it declares', function (): void {
    // The IndexNow key file route only exists while IndexNow is enabled with a key.
    config([
        'capell-site-discovery.indexnow.enabled' => true,
        'capell-site-discovery.indexnow.key' => 'abcdef1234567890',
    ]);

    $router = resolve('router');
    $before = $router->getRoutes()->getRoutes();
    $router->setRoutes(new RouteCollection);
    require dirname(__DIR__, 2) . '/routes/web.php';
    $router->getRoutes()->refreshNameLookups();

    try {
        expect(fn () => AssertsDeclaredThrottles::run(dirname(__DIR__, 2) . '/capell.json'))->not->toThrow(AssertionError::class);
    } finally {
        $restored = new RouteCollection;

        foreach ($before as $route) {
            $restored->add($route);
        }

        $router->setRoutes($restored);
    }
});
