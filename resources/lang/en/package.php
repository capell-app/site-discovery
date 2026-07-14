<?php

declare(strict_types=1);

return [
    'description' => 'Make every published Capell page discoverable with automatic XML sitemaps, an HTML sitemap, and the canonical public-URL registry.',
    'setup' => [
        'completed' => 'Site Discovery ensured an HTML sitemap for :count site(s).',
    ],
    'health' => [
        'public_urls' => [
            'label' => 'Site Discovery public URL contributors',
            'passed' => 'The CMS page contributor is registered and :count public URL contributor(s) implement the registry contract.',
            'failed' => 'Public URL contributors are not ready. Valid contributors: :count. Invalid contributors: :invalid.',
            'remediation' => 'Ensure SiteDiscoveryServiceProvider tags CmsPagePublicUrlContributor with PublicUrlContributor::TAG and remove invalid public URL contributor tags.',
        ],
        'xml_sitemaps' => [
            'label' => 'Site Discovery XML sitemap output',
            'passed' => 'The XML sitemap generator resolves, sitemap storage is writable, and the :path XML route can serve generated output.',
            'failed' => 'The XML sitemap generator, sitemap storage, or :path XML route is not ready.',
            'remediation' => 'Check capell.sitemap.disk, capell.sitemap.directory, capell.sitemap.xml_path, and the Site Discovery web route registration.',
        ],
        'incremental' => [
            'label' => 'Site Discovery incremental sitemap regeneration',
            'passed' => 'Incremental sitemap state persists and compares URL maps; scheduled regeneration is :schedule.',
            'failed' => 'Incremental sitemap configuration, scheduled regeneration, or persisted state comparison is not ready.',
            'remediation' => 'Check capell-site-discovery.incremental_sitemap_schedule config, the capell:xml-sitemap --incremental schedule event, and SitemapStateStore storage.',
            'schedule_disabled' => 'disabled',
            'schedule_enabled' => 'enabled',
        ],
        'html_sitemap' => [
            'label' => 'Site Discovery HTML sitemap type',
            'passed' => 'The default HTML sitemap page implementation, page registry, and Livewire component are registered.',
            'failed' => 'The default HTML sitemap page implementation, page registry, or Livewire component is not registered.',
            'remediation' => 'Ensure SiteDiscoveryServiceProvider registers PagesSitemap, the sitemap page type, and the site.sitemap Livewire component.',
        ],
    ],
];
