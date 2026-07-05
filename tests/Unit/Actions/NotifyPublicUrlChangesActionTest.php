<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\NotifyPublicUrlChangesAction;
use Capell\SiteDiscovery\Contracts\UrlChangeNotifier;
use Capell\SiteDiscovery\Data\DiscoverableUrlData;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Collection;

uses(SiteDiscoveryTestCase::class);

it('notifies tagged URL change notifiers with unique public URLs', function (): void {
    $site = (new Site)->forceFill(['id' => 1]);
    $language = (new Language)->forceFill(['id' => 1, 'code' => 'en', 'locale' => 'en']);
    $domain = new SiteDomain(['domain' => 'example.com', 'scheme' => 'https']);

    $notifier = new class implements UrlChangeNotifier
    {
        /** @var list<string> */
        public array $receivedUrls = [];

        /**
         * @param  Collection<int, non-falsy-string>  $urls
         */
        public function notify(Site $site, Language $language, Collection $urls, ?SiteDomain $domain = null): UrlChangeNotificationResultData
        {
            $this->receivedUrls = array_values($urls->all());

            return new UrlChangeNotificationResultData(
                notifier: 'test-notifier',
                urls: $this->receivedUrls,
                accepted: true,
            );
        }
    };

    app()->instance('site-discovery-test-url-change-notifier', $notifier);
    app()->instance('site-discovery-invalid-url-change-notifier', new stdClass);
    app()->tag([
        'site-discovery-test-url-change-notifier',
        'site-discovery-invalid-url-change-notifier',
    ], UrlChangeNotifier::TAG);

    $results = NotifyPublicUrlChangesAction::run($site, $language, [
        new DiscoverableUrlData('https://example.com/updated'),
        'https://example.com/updated',
        'http://example.com/also-updated',
        '/relative-url',
        'javascript:alert(1)',
    ], $domain);

    expect($results)->toHaveCount(1)
        ->and($results->first()?->notifier)->toBe('test-notifier')
        ->and($results->first()?->urls)->toBe([
            'https://example.com/updated',
            'http://example.com/also-updated',
        ])
        ->and($notifier->receivedUrls)->toBe([
            'https://example.com/updated',
            'http://example.com/also-updated',
        ]);
});

it('does not call notifiers when no public URLs are provided', function (): void {
    $site = (new Site)->forceFill(['id' => 1]);
    $language = (new Language)->forceFill(['id' => 1, 'code' => 'en', 'locale' => 'en']);

    $results = NotifyPublicUrlChangesAction::run($site, $language, [
        '/relative-url',
        'javascript:alert(1)',
    ]);

    expect($results)->toHaveCount(0);
});
