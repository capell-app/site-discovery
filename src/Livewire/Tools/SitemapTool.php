<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Livewire\Tools;

use Capell\SiteDiscovery\Enums\SitemapCacheKey;
use Capell\SiteDiscovery\Jobs\RebuildAllSitemapsJob;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class SitemapTool extends Component
{
    public function generate(): void
    {
        $this->assertGlobalAdmin();

        Cache::put(SitemapCacheKey::Generating->value, 'queued', now()->addMinutes(60));
        RebuildAllSitemapsJob::dispatch();

        Notification::make('sitemap_queue')
            ->status('warning')
            ->title(__('capell-admin::message.sitemap_queue'))
            ->body(__('capell-admin::message.sitemap_info'))
            ->send();

        $this->dispatch('close-dropdown', id: 'admin-tools-dropdown');
    }

    public function render(): View
    {
        return view('capell-site-discovery::livewire.tools.sitemap-tool');
    }

    private function assertGlobalAdmin(): void
    {
        $user = Filament::auth()->user();

        throw_if($user === null, AuthenticationException::class);

        if (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin()) {
            return;
        }

        $configured = config('filament-shield.super_admin.name', 'super_admin');
        $superAdminRole = is_string($configured) && $configured !== '' ? $configured : 'super_admin';

        if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        throw new AuthorizationException;
    }
}
