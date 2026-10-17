<?php

declare(strict_types=1);

use Capell\Core\Events\PageDeleted;
use Capell\Core\Events\PageSaved;
use Capell\Core\Events\SiteCreated;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageDeleted;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnPageSaved;
use Capell\SiteDiscovery\Listeners\Sitemap\RegenerateSitemapsOnSiteCreated;
use Capell\SiteDiscovery\Tests\SiteDiscoveryTestCase;
use Capell\SiteDiscovery\Tests\Support\CapturingSitemapQueue;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

uses(SiteDiscoveryTestCase::class);

/**
 * @return array{Connection, CapturingSitemapQueue, Dispatcher}
 */
function sitemapLifecycleTransactionQueue(PageSaved|PageDeleted|SiteCreated $event, RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnSiteCreated $listener): array
{
    // A separate in-memory connection exercises real outermost commits independently
    // of RefreshDatabase's surrounding test transaction and transaction manager.
    config(['database.connections.sitemap_lifecycle' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    $transactions = new DatabaseTransactionsManager;
    app()->instance('db.transactions', $transactions);
    $connection = DB::connection('sitemap_lifecycle');
    $connection->setTransactionManager($transactions);

    $queue = new CapturingSitemapQueue($connection, 'unused', dispatchAfterCommit: false);
    $queue->setContainer(app());
    $events = new Dispatcher(app());
    $events->setQueueResolver(static fn (): CapturingSitemapQueue => $queue);
    $events->listen($event::class, $listener::class);

    return [$connection, $queue, $events];
}

dataset('sitemap lifecycle events', [
    'page saved' => fn (): array => [new PageSaved((new Page)->forceFill(['id' => 42, 'site_id' => 55])), new RegenerateSitemapsOnPageSaved],
    'page deleted' => fn (): array => [new PageDeleted((new Page)->forceFill(['id' => 42, 'site_id' => 55])), new RegenerateSitemapsOnPageDeleted],
    'site created' => fn (): array => [new SiteCreated((new Site)->forceFill(['id' => 55]), []), resolve(RegenerateSitemapsOnSiteCreated::class)],
]);

it('discards sitemap lifecycle work when a transaction rolls back', function (PageSaved|PageDeleted|SiteCreated $event, RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnSiteCreated $listener): void {
    [$connection, $queue, $events] = sitemapLifecycleTransactionQueue($event, $listener);
    $rollback = new RuntimeException('Roll back the model operation.');
    $queuedBeforeRollback = null;

    try {
        $connection->transaction(function () use ($event, $events, $queue, $rollback, &$queuedBeforeRollback): void {
            $events->dispatch($event);
            $queuedBeforeRollback = count($queue->payloads);

            throw $rollback;
        });
    } catch (RuntimeException $exception) {
        expect($exception)->toBe($rollback);
    }

    expect($connection->transactionLevel())->toBe(0)
        ->and(count($queue->payloads))->toBe(0)
        ->and($queuedBeforeRollback)->toBe(0);
})->with('sitemap lifecycle events');

it('queues exactly one sitemap lifecycle job only after the outermost commit', function (PageSaved|PageDeleted|SiteCreated $event, RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnSiteCreated $listener): void {
    [$connection, $queue, $events] = sitemapLifecycleTransactionQueue($event, $listener);

    $connection->transaction(function () use ($connection, $event, $events, $queue): void {
        $connection->transaction(function () use ($event, $events, $queue): void {
            $events->dispatch($event);

            expect(count($queue->payloads))->toBe(0);
        });

        expect(count($queue->payloads))->toBe(0);
    });

    expect($queue->payloads)->toHaveCount(1)
        ->and(json_decode($queue->payloads[0], true, flags: JSON_THROW_ON_ERROR))->toMatchArray(['displayName' => $listener::class]);
})->with('sitemap lifecycle events');

it('queues exactly one sitemap lifecycle job immediately outside a transaction', function (PageSaved|PageDeleted|SiteCreated $event, RegenerateSitemapsOnPageSaved|RegenerateSitemapsOnPageDeleted|RegenerateSitemapsOnSiteCreated $listener): void {
    [$connection, $queue, $events] = sitemapLifecycleTransactionQueue($event, $listener);

    expect($connection->transactionLevel())->toBe(0);

    $events->dispatch($event);

    expect($queue->payloads)->toHaveCount(1)
        ->and(json_decode($queue->payloads[0], true, flags: JSON_THROW_ON_ERROR))->toMatchArray(['displayName' => $listener::class]);
})->with('sitemap lifecycle events');
