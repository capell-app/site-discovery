<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Tests\Support;

use Illuminate\Contracts\Queue\Factory;
use Illuminate\Queue\DatabaseQueue;
use Override;

/**
 * Keep Laravel's real enqueue/after-commit path, capturing only the transport write.
 */
final class CapturingSitemapQueue extends DatabaseQueue implements Factory
{
    /** @var list<string> */
    public array $payloads = [];

    #[Override]
    public function connection(mixed $name = null): self
    {
        return $this;
    }

    /** @param string $payload */
    #[Override]
    protected function pushToDatabase(mixed $queue, mixed $payload, mixed $delay = 0, mixed $attempts = 0): int
    {
        $this->payloads[] = $payload;

        return count($this->payloads);
    }
}
