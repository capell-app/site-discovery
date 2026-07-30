<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\IndexNow\IndexNowUrlChangeNotifier;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

uses(SiteDiscoveryTestCase::class);

it('submits public url changes to indexnow', function (): void {
    config()->set('capell-site-discovery.indexnow.key', 'site-key');
    config()->set('capell-site-discovery.indexnow.endpoint', 'https://api.indexnow.org/indexnow');

    Http::fake([
        'https://api.indexnow.org/indexnow' => Http::response([], 202),
    ]);

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        indexNowPublicUrls(['https://example.test/one', 'https://example.test/two']),
    );

    expect($result->notifier)->toBe('indexnow')
        ->and($result->accepted)->toBeTrue()
        ->and($result->urls)->toBe([
            'https://example.test/one',
            'https://example.test/two',
        ]);

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
        && $request['host'] === 'example.test'
        && $request['key'] === 'site-key'
        && $request['keyLocation'] === 'https://example.test/site-key.txt'
        && $request['urlList'] === [
            'https://example.test/one',
            'https://example.test/two',
        ]);
});

it('retries a temporarily unavailable indexnow endpoint', function (): void {
    config()->set('capell-site-discovery.indexnow.key', 'site-key');
    config()->set('capell-site-discovery.indexnow.endpoint', 'https://api.indexnow.org/indexnow');
    config()->set('capell-site-discovery.indexnow.retry_times', 2);
    config()->set('capell-site-discovery.indexnow.retry_delay_ms', 0);

    Http::fake([
        'https://api.indexnow.org/indexnow' => Http::sequence()
            ->push([], 503)
            ->push([], 202),
    ]);

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        indexNowPublicUrls(['https://example.test/one']),
    );

    expect($result->accepted)->toBeTrue();

    Http::assertSentCount(2);
});

it('reports skipped indexnow notifications when no key is configured', function (): void {
    config()->set('capell-site-discovery.indexnow.key');

    Http::fake();

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        indexNowPublicUrls(['https://example.test/one']),
    );

    expect($result->notifier)->toBe('indexnow')
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('IndexNow key is not configured.');

    Http::assertNothingSent();
});

it('reports failed indexnow notifications when the request cannot be sent', function (): void {
    config()->set('capell-site-discovery.indexnow.key', 'indexnow-key-secret');
    config()->set('capell-site-discovery.indexnow.endpoint', 'https://api.indexnow.org/indexnow?token=endpoint-token-secret');

    Http::fake([
        'https://api.indexnow.org/indexnow*' => fn (): never => throw new ConnectionException(
            'Connection failed for key=indexnow-key-secret token=endpoint-token-secret https://api.indexnow.org/indexnow?token=endpoint-token-secret.',
        ),
    ]);

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        indexNowPublicUrls(['https://example.test/one']),
    );

    expect($result->notifier)->toBe('indexnow')
        ->and($result->accepted)->toBeFalse()
        ->and($result->message)->toContain('key=[redacted]')
        ->and($result->message)->toContain('token=[redacted]')
        ->and($result->message)->not->toContain('indexnow-key-secret')
        ->and($result->message)->not->toContain('endpoint-token-secret');
});

it('does not disclose the IndexNow key to untrusted or private endpoints', function (): void {
    config()->set('capell-site-discovery.indexnow.key', 'indexnow-key-secret');
    config()->set('capell-site-discovery.indexnow.endpoint', 'https://127.0.0.1/indexnow');

    Http::fake();

    $result = (new IndexNowUrlChangeNotifier)->notify(
        new Site,
        new Language,
        indexNowPublicUrls(['https://example.test/one']),
    );

    expect($result->accepted)->toBeFalse()
        ->and($result->message)->toBe('IndexNow endpoint is not allowed.')
        ->and($result->message)->not->toContain('indexnow-key-secret');

    Http::assertNothingSent();
});

/**
 * @param  list<non-falsy-string>  $urls
 * @return Collection<int, non-falsy-string>
 */
function indexNowPublicUrls(array $urls): Collection
{
    return new Collection($urls);
}
