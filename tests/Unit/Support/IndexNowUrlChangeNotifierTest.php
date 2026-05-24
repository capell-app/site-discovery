<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\IndexNow\IndexNowUrlChangeNotifier;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(SiteDiscoveryTestCase::class);

it('submits public url changes to indexnow', function (): void {
    config()->set('capell-site-discovery.indexnow.key', 'site-key');
    config()->set('capell-site-discovery.indexnow.endpoint', 'https://indexnow.test/indexnow');

    Http::fake([
        'https://indexnow.test/indexnow' => Http::response([], 202),
    ]);

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        collect(['https://example.test/one', 'https://example.test/two']),
    );

    expect($result->notifier)->toBe('indexnow')
        ->and($result->accepted)->toBeTrue()
        ->and($result->urls)->toBe([
            'https://example.test/one',
            'https://example.test/two',
        ]);

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://indexnow.test/indexnow'
        && $request['host'] === 'example.test'
        && $request['key'] === 'site-key'
        && $request['keyLocation'] === 'https://example.test/site-key.txt'
        && $request['urlList'] === [
            'https://example.test/one',
            'https://example.test/two',
        ]);
});

it('reports skipped indexnow notifications when no key is configured', function (): void {
    config()->set('capell-site-discovery.indexnow.key', null);

    Http::fake();

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        collect(['https://example.test/one']),
    );

    expect($result->notifier)->toBe('indexnow')
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('IndexNow key is not configured.');

    Http::assertNothingSent();
});
