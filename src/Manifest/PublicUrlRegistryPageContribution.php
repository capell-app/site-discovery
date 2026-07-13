<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class PublicUrlRegistryPageContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
