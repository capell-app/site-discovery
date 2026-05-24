<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Core\Models\SiteDomain;
use Capell\SiteDiscovery\Actions\DiscoverPublicDiscoveryOutputsAction;
use Capell\SiteDiscovery\Contracts\DiscoveryOutputSource;
use Capell\SiteDiscovery\Data\DiscoveryOutputData;
use Capell\SiteDiscovery\Support\DiscoveryOutputRegistry;
use Illuminate\Support\Collection;

it('discovers registered public outputs and filters unsafe entries', function (): void {
    $site = new Site(['id' => 1]);
    $language = new Language(['id' => 1, 'code' => 'en', 'locale' => 'en']);
    $domain = new SiteDomain([
        'domain' => 'example.com',
        'scheme' => 'https',
        'path' => null,
    ]);

    $registry = new DiscoveryOutputRegistry;
    $registry->register(new class implements DiscoveryOutputSource
    {
        /**
         * @return Collection<int, DiscoveryOutputData>
         */
        public function discover(Site $site, Language $language, ?SiteDomain $domain = null): Collection
        {
            return collect([
                new DiscoveryOutputData(
                    key: 'llms-txt',
                    url: 'https://example.com/llms.txt',
                    contentType: 'text/plain; charset=UTF-8',
                    label: 'llms.txt',
                ),
                new DiscoveryOutputData(
                    key: 'llms-txt',
                    url: 'https://example.com/llms.txt',
                    contentType: 'text/plain; charset=UTF-8',
                    label: 'Duplicate llms.txt',
                ),
                new DiscoveryOutputData(
                    key: 'admin',
                    url: '/admin/secret',
                    contentType: 'text/html',
                ),
                new DiscoveryOutputData(
                    key: 'invalid-scheme',
                    url: 'http-not-a-url',
                    contentType: 'text/plain',
                ),
                new DiscoveryOutputData(
                    key: '',
                    url: 'https://example.com/invalid',
                    contentType: 'text/plain',
                ),
                new DiscoveryOutputData(
                    key: 'other-domain',
                    url: 'https://other.example.com/llms.txt',
                    contentType: 'text/plain',
                ),
            ]);
        }
    });

    $outputs = (new DiscoverPublicDiscoveryOutputsAction($registry))->handle($site, $language, $domain);

    expect($outputs)->toHaveCount(1)
        ->and($outputs->first()?->key)->toBe('llms-txt')
        ->and($outputs->first()?->url)->toBe('https://example.com/llms.txt');
});
