<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Data\GeneratedOutputParityReportData;
use Capell\SiteDiscovery\Data\GeneratedOutputParityRowData;
use Capell\SiteDiscovery\Data\PublicUrlRepairQueueData;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildAuthorisedPublicUrlRepairQueueAction
{
    use AsFake;
    use AsObject;

    public function handle(): PublicUrlRepairQueueData
    {
        $report = BuildGeneratedOutputParityReportAction::run();
        $actor = auth()->user();

        if ($actor !== null && SiteScope::isGlobalActor($actor)) {
            return BuildPublicUrlRepairQueueAction::run($report);
        }

        $allowed = SiteScope::applyForCurrentActor(Site::query(), 'id')->get()->map(static fn (Site $site): string => (string) $site->id)->all();
        $rows = array_values(array_filter($report->rows, static fn (GeneratedOutputParityRowData $row): bool => in_array((string) $row->siteKey, $allowed, true)));

        return BuildPublicUrlRepairQueueAction::run(new GeneratedOutputParityReportData(
            rows: $rows,
            totalUrls: count($rows),
            missingOutputUrls: count(array_filter($rows, static fn (GeneratedOutputParityRowData $row): bool => $row->hasMissingOutput())),
        ));
    }
}
