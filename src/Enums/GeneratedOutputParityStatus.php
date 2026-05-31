<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Enums;

use Filament\Support\Contracts\HasLabel;

enum GeneratedOutputParityStatus: string implements HasLabel
{
    case Present = 'present';
    case Missing = 'missing';
    case NotEligible = 'not_eligible';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return (string) __('capell-site-discovery::generic.generated_output_parity_status.' . $this->value);
    }
}
