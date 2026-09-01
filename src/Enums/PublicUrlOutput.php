<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicUrlOutput: string implements HasLabel
{
    case Sitemap = 'sitemap';
    case AiDiscovery = 'ai_discovery';
    case Search = 'search';
    case HtmlCache = 'html_cache';
    case AgentDelivery = 'agent_delivery';

    public function getLabel(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.label');
    }

    /**
     * Operator-facing area that owns producing this output.
     */
    public function responsibleArea(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.responsible_area');
    }

    /**
     * Existing safe next step for a URL missing from this output.
     */
    public function missingNextStep(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.missing_next_step');
    }

    /**
     * Explanation shown when no contributor reports coverage for this output.
     */
    public function unavailableNextStep(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.unavailable_next_step');
    }

    public function missingReason(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.missing_reason');
    }

    public function unavailableReason(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_output.' . $this->value . '.unavailable_reason');
    }
}
