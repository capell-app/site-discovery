<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Workbench\App\Actions\BuildDiscoveryScreenshotFixtureAction;
use Workbench\App\Filament\Pages\DiscoveryScreenshotFixturePage;

if (config('capell-site-discovery.screenshot_fixtures_enabled', Env::get('CAPELL_SITE_DISCOVERY_SCREENSHOT_FIXTURES_ENABLED', false)) !== true) {
    return;
}

Route::get('/screenshot-fixtures/site-discovery/{screen}', static function (string $screen): View {
    abort_unless(config('capell-site-discovery.screenshot_fixtures_enabled', Env::get('CAPELL_SITE_DISCOVERY_SCREENSHOT_FIXTURES_ENABLED', false)) === true && app()->environment(['local', 'testing']), 404);

    return view('capell-site-discovery::screenshots.sitemap', [
        'fixture' => resolve(BuildDiscoveryScreenshotFixtureAction::class)->handle(),
        'screen' => $screen,
    ]);
})->whereIn('screen', ['html-sitemap-page', 'xml-sitemap-output']);

Route::middleware(['web', 'auth'])->get('/screenshot-fixtures/site-discovery/public-url-registry-page', static function (): RedirectResponse {
    abort_unless(config('capell-site-discovery.screenshot_fixtures_enabled', Env::get('CAPELL_SITE_DISCOVERY_SCREENSHOT_FIXTURES_ENABLED', false)) === true && app()->environment(['local', 'testing']), 404);

    return redirect(DiscoveryScreenshotFixturePage::getUrl(panel: 'admin'));
});

app()->booted(static function (): void {
    $panel = Filament::getPanel('admin');
    $panel->pages([DiscoveryScreenshotFixturePage::class]);
    Route::name('filament.' . $panel->getId() . '.')
        ->prefix($panel->getPath())
        ->middleware([...$panel->getMiddleware(), ...$panel->getAuthMiddleware()])
        ->group(static function () use ($panel): void {
            DiscoveryScreenshotFixturePage::registerRoutes($panel);
        });
});
