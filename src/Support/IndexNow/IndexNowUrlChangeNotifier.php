<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\IndexNow;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Contracts\UrlChangeNotifier;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

final class IndexNowUrlChangeNotifier implements UrlChangeNotifier
{
    private const string NotifierName = 'indexnow';

    /**
     * @param  Collection<int, non-falsy-string>  $urls
     */
    public function notify(Site $site, Language $language, Collection $urls, ?SiteDomain $domain = null): UrlChangeNotificationResultData
    {
        $key = $this->stringConfig('capell-site-discovery.indexnow.key');

        if ($key === null) {
            return new UrlChangeNotificationResultData(
                notifier: self::NotifierName,
                urls: $urls->values()->all(),
                accepted: false,
                message: 'IndexNow key is not configured.',
            );
        }

        $endpoint = $this->stringConfig('capell-site-discovery.indexnow.endpoint') ?? 'https://api.indexnow.org/indexnow';
        $payload = $this->payload($urls, $key, $domain);

        try {
            $response = Http::timeout($this->timeoutSeconds())
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);
        } catch (RequestException $exception) {
            return new UrlChangeNotificationResultData(
                notifier: self::NotifierName,
                urls: $payload['urlList'],
                accepted: false,
                message: $exception->getMessage(),
            );
        }

        return new UrlChangeNotificationResultData(
            notifier: self::NotifierName,
            urls: $payload['urlList'],
            accepted: $response->successful(),
            message: $response->successful()
                ? null
                : sprintf('IndexNow returned HTTP %s.', $response->status()),
        );
    }

    /**
     * @param  Collection<int, non-falsy-string>  $urls
     * @return array{host: string, key: string, keyLocation: string, urlList: list<string>}
     */
    private function payload(Collection $urls, string $key, ?SiteDomain $domain): array
    {
        $urlList = $urls->values()->all();
        $host = $domain instanceof SiteDomain && is_string($domain->domain) && $domain->domain !== ''
            ? $domain->domain
            : $this->hostFromUrl($urlList[0]);

        return [
            'host' => $host,
            'key' => $key,
            'keyLocation' => $this->keyLocation($key, $host, $urlList[0], $domain),
            'urlList' => $urlList,
        ];
    }

    private function keyLocation(string $key, string $host, string $firstUrl, ?SiteDomain $domain): string
    {
        $configuredLocation = $this->stringConfig('capell-site-discovery.indexnow.key_location');

        if ($configuredLocation !== null) {
            return $configuredLocation;
        }

        $scheme = $domain instanceof SiteDomain
            ? (string) $domain->scheme
            : $this->schemeFromUrl($firstUrl);

        return sprintf('%s://%s/%s.txt', $scheme, $host, $key);
    }

    private function hostFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : '';
    }

    private function schemeFromUrl(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? $scheme : 'https';
    }

    private function timeoutSeconds(): int
    {
        $timeout = config('capell-site-discovery.indexnow.timeout', 10);

        return is_int($timeout) && $timeout > 0 ? $timeout : 10;
    }

    private function stringConfig(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
