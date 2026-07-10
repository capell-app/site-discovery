<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\IndexNow;

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\RedactIndexNowNotificationErrorMessageAction;
use Capell\SiteDiscovery\Contracts\UrlChangeNotifier;
use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class IndexNowUrlChangeNotifier implements UrlChangeNotifier
{
    private const string NotifierName = 'indexnow';

    /** @var list<string> */
    private const array TrustedEndpointHosts = [
        'api.indexnow.org',
        'www.bing.com',
        'yandex.com',
        'indexnow.yandex.ru',
        'searchadvisor.naver.com',
        'search.seznam.cz',
    ];

    /**
     * @param  Collection<int, non-falsy-string>  $urls
     */
    public function notify(Site $site, Language $language, Collection $urls, ?SiteDomain $domain = null): UrlChangeNotificationResultData
    {
        $key = $this->stringConfig('capell-site-discovery.indexnow.key');

        if ($key === null) {
            return new UrlChangeNotificationResultData(
                notifier: self::NotifierName,
                urls: array_values($urls->values()->all()),
                accepted: false,
                message: 'IndexNow key is not configured.',
            );
        }

        $endpoint = $this->stringConfig('capell-site-discovery.indexnow.endpoint') ?? 'https://api.indexnow.org/indexnow';
        $payload = $this->payload($urls, $key, $domain);

        try {
            $resolvedEndpoint = $this->resolveTrustedEndpoint($endpoint);
        } catch (InvalidArgumentException) {
            return new UrlChangeNotificationResultData(
                notifier: self::NotifierName,
                urls: $payload['urlList'],
                accepted: false,
                message: 'IndexNow endpoint is not allowed.',
            );
        }

        try {
            $response = Http::connectTimeout(min(3, $this->timeoutSeconds()))
                ->timeout($this->timeoutSeconds())
                ->withoutRedirecting()
                ->withHeaders(['Host' => $resolvedEndpoint['host_header']])
                ->withOptions([
                    'curl' => [
                        CURLOPT_RESOLVE => [$resolvedEndpoint['curl_resolve']],
                    ],
                ])
                ->acceptJson()
                ->asJson()
                ->post($resolvedEndpoint['url'], $payload);
        } catch (ConnectionException $connectionException) {
            return new UrlChangeNotificationResultData(
                notifier: self::NotifierName,
                urls: $payload['urlList'],
                accepted: false,
                message: (new RedactIndexNowNotificationErrorMessageAction)->handle($connectionException),
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
        $urlList = array_values($urls->values()->all());
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

    /**
     * @return array{url: string, host_header: string, curl_resolve: string}
     */
    private function resolveTrustedEndpoint(string $endpoint): array
    {
        $parts = parse_url($endpoint);
        $scheme = is_array($parts) && is_string($parts['scheme'] ?? null) ? strtolower($parts['scheme']) : null;
        $host = is_array($parts) && is_string($parts['host'] ?? null) ? strtolower($parts['host']) : null;
        $port = is_array($parts) && is_int($parts['port'] ?? null) ? $parts['port'] : 443;

        throw_if(
            $scheme !== 'https'
            || $host === null
            || ! in_array($host, self::TrustedEndpointHosts, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || $port < 1
            || $port > 65535
            || ! defined('CURLOPT_RESOLVE'),
            InvalidArgumentException::class,
            'IndexNow endpoint is not allowed.',
        );

        $records = dns_get_record($host, DNS_A | DNS_AAAA);
        $addresses = $records === false ? [] : array_values(array_unique(array_filter(array_map(
            static fn (array $record): ?string => is_string($record['ip'] ?? null)
                ? $record['ip']
                : (is_string($record['ipv6'] ?? null) ? $record['ipv6'] : null),
            $records,
        ))));

        throw_if($addresses === [] || collect($addresses)->contains(fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false), InvalidArgumentException::class, 'IndexNow endpoint is not allowed.');

        return [
            'url' => $endpoint,
            'host_header' => $port === 443 ? $host : $host . ':' . $port,
            'curl_resolve' => sprintf('%s:%d:%s', $host, $port, $addresses[0]),
        ];
    }
}
