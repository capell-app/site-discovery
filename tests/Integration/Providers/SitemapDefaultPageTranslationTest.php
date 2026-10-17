<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\SiteDiscovery\Support\Sitemap\SitemapPageType;
use Capell\Tests\Support\InstalledRuntimeParity;
use Illuminate\Foundation\Application;

/*
 * A fresh installed boot runs the installed runtime before this package's
 * translation namespace is registered. Translating there returned the raw key
 * and cached an empty `generic` group for the rest of the request, so every
 * sitemap page, blueprint and navigation label was persisted as
 * `capell-site-discovery::generic.sitemap`.
 */
it('stores the translated sitemap name after a fresh installed boot and in-process installation', function (): void {
    InstalledRuntimeParity::assertPackage('site-discovery', static function (Application $app): void {
        $phase = $app->make('installed-runtime.initially-installed') === true ? 'fresh installed boot' : 'in-process installation';
        $expected = 'Sitemap';

        $language = Language::factory()->create();
        $site = Site::factory()->withTranslations($language)->create();
        $createDefaultPage = CapellCore::getDefaultPage('sitemap')->callback;

        expect($createDefaultPage)->toBeInstanceOf(Closure::class);

        if (! $createDefaultPage instanceof Closure) {
            return;
        }

        $createDefaultPage($site, collect([$language]));

        $blueprint = Blueprint::query()->where('key', SitemapPageType::Key)->sole();
        $page = Page::query()->where('site_id', $site->id)->where('blueprint_id', $blueprint->id)->sole();
        $translation = $page->translations()->where('language_id', $language->id)->sole();

        expect($translation)->toBeInstanceOf(Translation::class);

        if (! $translation instanceof Translation) {
            return;
        }

        // Equality with the translated string also rules out the raw `package::group.key` form.
        expect([$page->name, $translation->title, $blueprint->name])->toBe([$expected, $expected, $expected], $phase);

        expect(CapellCore::getDefaultPage('sitemap')->label)->toBe($expected, $phase);
    });
});
