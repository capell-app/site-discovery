<?php

declare(strict_types=1);

use Capell\Core\Contracts\PublicRead\EvaluatesPublicPageReadCandidatesIndividually;
use Capell\Core\Contracts\PublicRead\PublicPageReadEligibility;
use Capell\Core\Data\PublicRead\PublicPageReadCandidateData;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Core\Support\PublicRead\PublicPageReadEligibilityRegistry;
use Capell\SiteDiscovery\Support\Sitemap\XmlSitemapGenerator;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Illuminate\Support\Facades\Storage;

uses(SiteDiscoveryTestCase::class);

beforeEach(function (): void {
    config(['capell.sitemap.disk' => 'local', 'capell.sitemap.directory' => 'sitemaps_test']);
    $storage = Storage::disk('local');
    $storage->deleteDirectory('sitemaps_test');
    $storage->makeDirectory('sitemaps_test');
});

afterEach(function (): void {
    Storage::disk('local')->deleteDirectory('sitemaps_test');
});

it('leaves a page out of the sitemap of the domain where it is restricted and keeps it on the other', function (): void {
    $languages = Language::factory()->count(2)->create();
    $site = Site::factory()->language($languages[0])->withTranslations($languages)->create();
    $page = Page::factory()->site($site)->withTranslations($languages)->create();
    $page->load('pageUrls.siteDomain');
    $restricted = $page->pageUrls->firstWhere('language_id', $languages[0]->getKey());
    $kept = $page->pageUrls->firstWhere('language_id', $languages[1]->getKey());
    $restrictedUrl = $restricted->full_url;

    resolve(PublicPageReadEligibilityRegistry::class)->register(new class($restrictedUrl) implements PublicPageReadEligibility
    {
        use EvaluatesPublicPageReadCandidatesIndividually;

        public function __construct(private readonly string $restrictedUrl) {}

        public function key(): string
        {
            return 'test-restricted-domain';
        }

        public function isPubliclyReadable(PublicPageReadCandidateData $candidate): bool
        {
            return $candidate->url !== $this->restrictedUrl;
        }
    });

    (new XmlSitemapGenerator)->generate($site);

    $files = collect(Storage::disk('local')->allFiles('sitemaps_test'))
        ->mapWithKeys(fn (string $file): array => [$file => (string) Storage::disk('local')->get($file)]);
    $restrictedXml = $files->first(fn (string $xml): bool => str_contains($xml, '<loc>' . $restrictedUrl . '</loc>'));
    $keptXml = $files->first(fn (string $xml): bool => str_contains($xml, '<loc>' . $kept->full_url . '</loc>'));

    expect($restrictedXml)->toBeNull()
        ->and($keptXml)->toBeString()
        ->and($keptXml)->not->toContain($restrictedUrl);
});
