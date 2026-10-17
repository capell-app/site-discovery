<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Workbench\App\Actions\BuildDiscoveryScreenshotFixtureAction;
use Workbench\App\Filament\Pages\DiscoveryScreenshotFixturePage;

uses(SiteDiscoveryTestCase::class);
uses(CreatesAdminUser::class);

it('keeps discovery fixtures absent by default', function (string $screen): void {
    config(['capell-site-discovery.screenshot_fixtures_enabled' => false]);
    require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';

    $this->get('/screenshot-fixtures/site-discovery/' . $screen)->assertNotFound();
})->with(['html-sitemap-page', 'xml-sitemap-output', 'public-url-registry-page']);

it('renders bounded read-only sitemap fixtures when opted in', function (string $screen, string $marker): void {
    config(['capell-site-discovery.screenshot_fixtures_enabled' => true]);
    require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';

    $this->get('/screenshot-fixtures/site-discovery/' . $screen)
        ->assertOk()
        ->assertSee($marker, false)
        ->assertSee('https://studio.example/workshops')
        ->assertDontSee('wire:snapshot', false);
})->with([
    ['html-sitemap-page', 'data-screenshot-html-sitemap'],
    ['xml-sitemap-output', 'data-screenshot-xml-sitemap'],
]);

it('projects a small representative queue through the real repair action', function (): void {
    $fixture = resolve(BuildDiscoveryScreenshotFixtureAction::class)->handle();
    expect($fixture->queue->totalUrls)->toBe(3)
        ->and($fixture->queue->needsAttentionUrls)->toBe(2)
        ->and($fixture->queue->healthyUrls)->toBe(1)
        ->and($fixture->queue->unavailableUrls)->toBe(0);
    $xml = simplexml_load_string($fixture->xml);
    throw_if($xml === false, RuntimeException::class, 'The fixture sitemap must be valid XML.');
    expect($xml->xpath('//*[local-name()="loc"]'))->toHaveCount(3);
});

it('renders the actual registry page with bounded fixture rows', function (): void {
    $this->actingAsAdmin();
    config(['capell-site-discovery.screenshot_fixtures_enabled' => true]);
    $page = Livewire::test(DiscoveryScreenshotFixturePage::class)
        ->assertSee('https://studio.example/workshops')
        ->assertSee('data-public-url-repair-queue', false);
    $instance = $page->instance();
    throw_unless($instance instanceof DiscoveryScreenshotFixturePage, RuntimeException::class, 'The Livewire instance must be the fixture page.');
    expect($instance->queue()->totalUrls)->toBe(3);
});

it('redirects an authenticated fixture request to the workbench registry page', function (): void {
    $this->actingAsAdmin();
    config(['capell-site-discovery.screenshot_fixtures_enabled' => true]);
    require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';

    $this->get('/screenshot-fixtures/site-discovery/public-url-registry-page')
        ->assertRedirect(DiscoveryScreenshotFixturePage::getUrl(panel: 'admin'));
    $this->get(DiscoveryScreenshotFixturePage::getUrl(panel: 'admin'))
        ->assertOk()
        ->assertSee('https://studio.example/workshops');
});

it('rejects the registry component outside local and testing', function (): void {
    $this->actingAsAdmin();
    config(['capell-site-discovery.screenshot_fixtures_enabled' => true]);
    app()->instance('env', 'production');

    expect(fn () => resolve(DiscoveryScreenshotFixturePage::class)->mount())->toThrow(NotFoundHttpException::class);
});

it('keeps opted-in fixture routes unavailable outside local and testing', function (): void {
    config(['capell-site-discovery.screenshot_fixtures_enabled' => true]);
    require __DIR__ . '/../../workbench/routes/screenshot-fixtures.php';
    app()->instance('env', 'production');

    $this->get('/screenshot-fixtures/site-discovery/html-sitemap-page')->assertNotFound();
});
