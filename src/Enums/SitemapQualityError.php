<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Enums;

enum SitemapQualityError: string
{
    case InvalidStatus = 'invalid_status';
    case InvalidContentType = 'invalid_content_type';
    case PrivateUrl = 'private_url';
    case DuplicateUrl = 'duplicate_url';
    case MissingLastModified = 'missing_last_modified';
    case StaleLastModified = 'stale_last_modified';
    case MalformedXml = 'malformed_xml';
}
