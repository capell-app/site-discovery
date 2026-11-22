<?php

declare(strict_types=1);

return [
    'description' => 'Machen Sie jede veröffentlichte Capell-Seite mit automatischen XML-Sitemaps, einer HTML-Sitemap und dem kanonischen Register öffentlicher URLs auffindbar.',
    'setup' => [
        'completed' => 'Site Discovery hat eine HTML-Sitemap sichergestellt. Sites: :count.',
    ],
    'health' => [
        'public_urls' => [
            'label' => 'Site Discovery: Öffentliche URLs verfügbar',
            'passed' => 'Der CMS-Seiten-Contributor ist registriert und die Contributors für öffentliche URLs implementieren den Registry-Contract. Contributors: :count.',
            'failed' => 'Die Contributors für öffentliche URLs sind nicht bereit. Gültige Contributors: :count. Ungültige Contributors: :invalid.',
            'remediation' => 'Stellen Sie sicher, dass SiteDiscoveryServiceProvider CmsPagePublicUrlContributor mit PublicUrlContributor::TAG markiert, und entfernen Sie ungültige Tags von Contributors für öffentliche URLs.',
        ],
        'xml_sitemaps' => [
            'label' => 'Site Discovery: XML-Sitemaps verfügbar',
            'passed' => 'Der XML-Sitemap-Generator wird aufgelöst, der Sitemap-Speicher ist beschreibbar und die XML-Route :path kann generierte Ausgaben ausliefern.',
            'failed' => 'Der XML-Sitemap-Generator, der Sitemap-Speicher oder die XML-Route :path ist nicht bereit.',
            'remediation' => 'Prüfen Sie capell.sitemap.disk, capell.sitemap.directory, capell.sitemap.xml_path und die Registrierung der Web-Route von Site Discovery.',
        ],
        'incremental' => [
            'label' => 'Site Discovery: Sitemap-Aktualisierungen geplant',
            'passed' => 'Der inkrementelle Sitemap-Status wird gespeichert und URL-Zuordnungen werden verglichen; die geplante Neugenerierung ist :schedule.',
            'failed' => 'Die inkrementelle Sitemap-Konfiguration, die geplante Neugenerierung oder der Vergleich des gespeicherten Status ist nicht bereit.',
            'remediation' => 'Prüfen Sie die Konfiguration capell-site-discovery.incremental_sitemap_schedule, das Schedule-Event capell:xml-sitemap --incremental und den Speicher von SitemapStateStore.',
            'schedule_disabled' => 'deaktiviert',
            'schedule_enabled' => 'aktiviert',
        ],
        'html_sitemap' => [
            'label' => 'Site Discovery: HTML-Sitemap-Seitentyp verfügbar',
            'passed' => 'Die Standardimplementierung der HTML-Sitemap-Seite, die Seitenregistry und die Livewire-Komponente sind registriert.',
            'failed' => 'Die Standardimplementierung der HTML-Sitemap-Seite, die Seitenregistry oder die Livewire-Komponente ist nicht registriert.',
            'remediation' => 'Stellen Sie sicher, dass SiteDiscoveryServiceProvider PagesSitemap, den Sitemap-Seitentyp und die Livewire-Komponente site.sitemap registriert.',
        ],
    ],
];
