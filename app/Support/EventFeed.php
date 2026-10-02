<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * A short, numbered list of recent events kept in the cache (not the database),
 * which pages poll for live updates. The store is `cache.feed_store`: the file
 * cache normally, the array cache in tests so test runs never reach real users.
 */
class EventFeed
{
    public function __construct(
        private string $key,
        private int $limit = 50,
    ) {}

    /** Append an event, give it the next id, and return it. */
    public function push(array $event): array
    {
        $store = $this->store();

        // Lock so two simultaneous pushes don't overwrite each other.
        return $store->lock($this->key . '-lock', 5)->block(5, function () use ($store, $event) {
            $feed = $this->read();

            $event['id'] = ++$feed['seq'];
            $feed['events'][] = $event;
            $feed['events'] = array_slice($feed['events'], -$this->limit);

            $store->put($this->key, $feed, now()->addDay());

            return $event;
        });
    }

    /** @return array{seq: int, events: array<int, array>} */
    public function read(): array
    {
        return $this->store()->get($this->key, ['seq' => 0, 'events' => []]);
    }

    private function store(): Repository
    {
        return Cache::store(config('cache.feed_store', 'file'));
    }
}
