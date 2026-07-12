<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\NotifyPageUrlChangesAction;
use Capell\SiteDiscovery\Contracts\UrlChangeNotifier;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Collection;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    clearNotifyPageUrlChangesActionNotifierTags();
});

afterEach(function (): void {
    clearNotifyPageUrlChangesActionNotifierTags();
});

it('notifies public urls for a saved or deleted page', function (): void {
    $language = Language::query()->create([
        'name' => 'English',
        'locale' => 'en',
        'code' => 'en',
        'flag' => 'gb-eng',
        'status' => true,
        'default' => true,
        'order' => 1,
    ]);
    $site = Site::factory()->default()->create(['language_id' => $language->id]);
    $domain = SiteDomain::factory()
        ->site($site)
        ->language($language)
        ->create([
            'domain' => 'example.test',
            'path' => null,
            'scheme' => 'https',
        ]);
    $page = Page::factory()
        ->site($site)
        ->published()
        ->withTranslations($language)
        ->create();

    PageUrl::factory()
        ->site($site)
        ->language($language)
        ->page($page)
        ->create(['url' => '/public']);

    PageUrl::factory()
        ->site($site)
        ->language($language)
        ->page($page)
        ->create([
            'status' => false,
            'url' => '/disabled',
        ]);

    $notifier = new class implements UrlChangeNotifier
    {
        /** @var list<string> */
        public array $urls = [];

        /**
         * @param  Collection<int, non-falsy-string>  $urls
         */
        public function notify(Site $site, Language $language, Collection $urls, ?SiteDomain $domain = null): UrlChangeNotificationResultData
        {
            $this->urls = array_values($urls->all());

            return new UrlChangeNotificationResultData(
                notifier: 'test',
                urls: $this->urls,
                accepted: true,
            );
        }
    };

    app()->instance('site-discovery-page-url-change-notifier', $notifier);
    app()->tag(['site-discovery-page-url-change-notifier'], UrlChangeNotifier::TAG);

    $results = NotifyPageUrlChangesAction::run($page);

    expect($results)->toHaveCount(1)
        ->and($notifier->urls)->toContain('https://example.test/public')
        ->and($notifier->urls)->not->toContain('https://example.test/disabled');
});

function clearNotifyPageUrlChangesActionNotifierTags(): void
{
    $reflection = new ReflectionClass(app());
    $property = $reflection->getProperty('tags');
    $tags = $property->getValue(app());

    if (is_array($tags)) {
        unset($tags[UrlChangeNotifier::TAG]);
        $property->setValue(app(), $tags);
    }
}
