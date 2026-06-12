<?php

declare(strict_types=1);

namespace Capell\SiteDiscovery\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

final class RedactIndexNowNotificationErrorMessageAction
{
    use AsAction;

    public function handle(Throwable|string $error): string
    {
        $redacted = $error instanceof Throwable ? $error->getMessage() : $error;

        foreach ($this->knownSensitiveValues() as $value) {
            $redacted = str_replace($value, '[redacted]', $redacted);
        }

        foreach ($this->patterns() as $pattern => $replacement) {
            $redacted = preg_replace($pattern, $replacement, $redacted) ?? $redacted;
        }

        return $redacted;
    }

    /**
     * @return list<non-empty-string>
     */
    private function knownSensitiveValues(): array
    {
        $values = [
            config('capell-site-discovery.indexnow.key'),
            config('capell-site-discovery.indexnow.endpoint'),
            config('capell-site-discovery.indexnow.key_location'),
        ];

        return array_values(array_unique(array_filter(
            $values,
            static fn (mixed $value): bool => is_string($value) && mb_strlen($value) >= 8,
        )));
    }

    /**
     * @return array<non-empty-string, non-empty-string>
     */
    private function patterns(): array
    {
        return [
            '/\b(api[_-]?key|indexnow[_-]?key|key|token|secret)(\s*["\']?\s*[:=]\s*["\']?)[^"\'\s,;}{]+/i' => '$1$2[redacted]',
        ];
    }
}
