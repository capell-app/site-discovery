<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Support\Creator\SitemapPageCreator;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class EnsureSitemapPagesAction
{
    use AsFake;
    use AsObject;

    public function handle(): int
    {
        $siteCount = 0;

        Site::query()->orderBy('id')->chunkById(
            100,
            function (Collection $sites) use (&$siteCount): void {
                $pageCreator = resolve(SitemapPageCreator::class);

                foreach ($sites as $site) {
                    $pageCreator->createSitemapPage($site, $site->languages()->get()->toBase());
                    $siteCount++;
                }
            },
        );

        return $siteCount;
    }
}
