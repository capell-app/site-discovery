<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Filament\Extenders\Page;

use Capell\Admin\Contracts\Extenders\ResourceHeaderActionExtender;
use Capell\Admin\Filament\Pages\SitemapPage;
use Capell\Admin\Filament\Resources\Pages\Pages\EditPage;
use Capell\Admin\Filament\Resources\Pages\Pages\ListPages;
use Filament\Actions\Action;

class SitemapResourceHeaderActionExtender implements ResourceHeaderActionExtender
{
    public function supports(string $pageClass): bool
    {
        return in_array($pageClass, [EditPage::class, ListPages::class], true);
    }

    /** @return array<int, Action> */
    public function actions(): array
    {
        return [
            Action::make('sitemap')
                ->label(__('capell-admin::button.sitemap'))
                ->icon('heroicon-c-globe-alt')
                ->color('gray')
                ->url(fn (): string => SitemapPage::getUrl()),
        ];
    }
}
