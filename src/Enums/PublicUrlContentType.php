<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Enums;

use Filament\Support\Contracts\HasLabel;

enum PublicUrlContentType: string implements HasLabel
{
    case Page = 'page';
    case Article = 'article';
    case Taxonomy = 'taxonomy';
    case Media = 'media';
    case Feed = 'feed';
    case Other = 'other';

    public function getLabel(): string
    {
        return (string) __('capell-site-discovery::generic.public_url_content_type.' . $this->value);
    }
}
