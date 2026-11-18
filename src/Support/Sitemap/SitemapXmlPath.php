<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Support\Sitemap;

/**
 * The single source of truth for the public XML sitemap path.
 *
 * `/sitemap.xml` is canonical. The path comes from `capell.sitemap.xml_path`; an unset or empty value falls back
 * to the canonical path, and the legacy `/sitemap-xml` value (the old default, still present in installs that
 * published the config) resolves to the canonical path so it is never advertised again. The legacy path itself
 * stays reachable as a permanent redirect.
 */
final class SitemapXmlPath
{
    public const string CANONICAL = '/sitemap.xml';

    public const string LEGACY = '/sitemap-xml';

    /**
     * The canonical public path with a leading slash, for example `/sitemap.xml`.
     */
    public static function canonical(): string
    {
        $configured = config('capell.sitemap.xml_path');
        $path = is_string($configured) || is_numeric($configured)
            ? '/' . trim((string) $configured, '/')
            : self::CANONICAL;

        return $path === '/' || $path === self::LEGACY ? self::CANONICAL : $path;
    }

    /**
     * The canonical path without the leading slash, as a route URI.
     */
    public static function routeUri(): string
    {
        return ltrim(self::canonical(), '/');
    }

    /**
     * The legacy path is only an alias when it differs from the canonical path.
     */
    public static function hasLegacyAlias(): bool
    {
        return self::canonical() !== self::LEGACY;
    }
}
