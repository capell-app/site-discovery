<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicUrlRepairStatus: string implements HasLabel
{
    /**
     * At least one eligible output is missing this URL.
     */
    case NeedsAttention = 'needs_attention';

    /**
     * No output is missing, but at least one eligible output cannot report coverage.
     */
    case Unavailable = 'unavailable';

    /**
     * Every eligible output contains this URL.
     */
    case Healthy = 'healthy';

    public function getLabel(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_repair_status.' . $this->value);
    }
}
