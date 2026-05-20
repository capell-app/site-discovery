<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Interceptors;

use Capell\Admin\Filament\Configurators\Types\PageTypeConfigurator;
use Capell\Core\Contracts\ModelInterceptors\BlueprintInterceptorInterface;
use Capell\Core\Models\Blueprint;

class SitemapPageTypeInterceptor implements BlueprintInterceptorInterface
{
    public function beforeCreate(array $data): array
    {
        $data['admin'] = [
            'type_configurator' => PageTypeConfigurator::getKey(),
            'icon' => 'heroicon-o-map',
            'required_fields' => ['title'],
        ];

        return $data;
    }

    public function afterCreated(Blueprint $type, array $data): void {}
}
