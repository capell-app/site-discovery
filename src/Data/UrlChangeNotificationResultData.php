<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Data;

use Spatie\LaravelData\Data;

final class UrlChangeNotificationResultData extends Data
{
    /**
     * @param  list<string>  $urls
     */
    public function __construct(
        public readonly string $notifier,
        public readonly array $urls,
        public readonly bool $accepted,
        public readonly ?string $message = null,
    ) {}
}
