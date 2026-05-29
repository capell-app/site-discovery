<?php

declare(strict_types=1);

use Capell\SiteDiscovery\Data\UrlChangeNotificationResultData;

it('stores URL change notification results', function (): void {
    $data = new UrlChangeNotificationResultData(
        notifier: 'indexnow',
        urls: ['https://example.com/updated'],
        accepted: true,
        message: 'Submitted',
    );

    expect($data->notifier)->toBe('indexnow')
        ->and($data->urls)->toBe(['https://example.com/updated'])
        ->and($data->accepted)->toBeTrue()
        ->and($data->message)->toBe('Submitted');
});
