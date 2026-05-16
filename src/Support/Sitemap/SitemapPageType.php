<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

use Capell\Core\Contracts\ModelInterceptors\BlueprintInterceptorInterface;
use Capell\Core\Enums\BlueprintSubjectEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Blueprint;

/**
 * Defines the canonical "sitemap" page type owned by Site Discovery.
 *
 * The Blueprint::key value is registered from Site Discovery rather than core
 * because the page type is meaningful only when Site Discovery is installed.
 */
final class SitemapPageType
{
    public const Key = 'sitemap';

    public const ComponentView = 'capell-site-discovery.page.sitemap';

    public static function createType(): Blueprint
    {
        $defaults = [
            'key' => self::Key,
            'type' => BlueprintSubjectEnum::Page,
            'name' => __('capell-site-discovery::generic.sitemap'),
            'meta' => [
                'listable' => false,
                'component' => self::ComponentView,
                'livewire' => true,
            ],
        ];

        /** @var class-string<Blueprint> $typeModel */
        $typeModel = Blueprint::class;

        $blueprint = CapellCore::createOrUpdateModel(
            $typeModel,
            ['key' => self::Key, 'type' => BlueprintSubjectEnum::Page],
            fn (array $data): array => CapellCore::mergeModelInterceptorData($defaults, $data),
            BlueprintInterceptorInterface::class,
        );

        $blueprint->forceFill([
            'component' => self::ComponentView,
            'is_livewire' => true,
            'meta' => [
                ...($blueprint->meta ?? []),
                'component' => self::ComponentView,
                'livewire' => true,
                'listable' => false,
            ],
        ])->save();

        return $blueprint;
    }
}
