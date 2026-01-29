# Event Loading Core (Symfony 7.4 compatible)

This is a minimal, framework-agnostic core designed to plug into a Symfony 7.4 application running on PHP 8.1+. It defines the interfaces and implements the main event-loading loop. Implementations of networking, storage, and distributed coordination are intentionally left out.

## Docker (PHP 8.3) + Symfony 7.4
This repo includes a ready Symfony 7.4 skeleton plus a minimal Docker setup for PHP and Composer.

To run the dev server:

```bash
docker compose up --build
# visit http://localhost:8000
```

## Database for inbox and storage
`docker-compose.yml` adds a PostgreSQL 16 service. The loader uses DB-backed implementations for:
- inbox (`event_inbox`)
- final storage (`events`)
- cursors (`source_cursor`)
- rate limiting (`source_rate_limit`)
- request log (`event_request_log`)

### Test database
Functional and E2E tests use a separate database container (`db_test`) and are reset on each test run.
`Makefile.cmd test` will:
1) start `db_test` and `rabbitmq`
2) drop/recreate the schema in the test DB
3) run PHPUnit

## Request log (pre-fetch reservation)
To ensure the same request window is not executed more than once across parallel loaders, a request log is used:
- Each fetch reserves `(source, after_id)` before calling the remote source.
- The reservation is marked `succeeded` after inbox write and cursor advance.
- On empty response, the reservation is released to allow future retries.
- On error, the reservation is marked `failed` and **not** retried automatically.

### Retention & partitioning
To avoid unbounded growth, prune old rows:

```bash
docker compose run --rm app php bin/console app:request-log-prune --succeeded-days=7 --failed-days=30
```

Optional: create a partitioned archive table and enable `--archive`:

```bash
docker compose run --rm db psql -U app -d app -f /app/sql/request_log_archive_schema.sql
docker compose run --rm app php bin/console app:request-log-prune --archive --succeeded-days=7 --failed-days=30
```

Automatic monthly partitions:

```bash
docker compose run --rm app php bin/console app:request-log-create-partitions --months-ahead=3
```

## Retry worker
Use the retry worker to move events from the inbox to final storage without re-fetching:

```bash
docker compose run --rm app php bin/console app:inbox-retry-worker
```

### Edge cases
- Parallel workers use `FOR UPDATE SKIP LOCKED`; a worker may return no rows even if the inbox is non-empty.
- Batches can be partial if other workers hold locks on some rows.

## RabbitMQ Streams (example source)
Docker includes a RabbitMQ container with stream plugins enabled.

Ports:
- AMQP: 5672
- Management UI: http://localhost:15672 (user: app / pass: app)
- Streams: 5552

Example service (DI) is `RabbitMqStreamEventSource`, which depends on `StreamClientInterface`.
An in-memory client is provided for tests, and `PhpAmqplibStreamClient` uses `php-amqplib/php-amqplib`.

Note: stream offsets start at 0. For new stream sources, initialize `source_cursor.last_requested_id` to `-1` to avoid skipping the first message.

#### Error handling (best effort)
`PhpAmqplibStreamClient` tries to `nack(false, false)` on callback errors (e.g., invalid JSON) to avoid retry loops.
If nack fails (channel closed), the original exception is still propagated and the consumer is canceled.

### Example: register a stream source
Define a service for your stream source and tag it as `app.event_source`:

```yaml
services:
  App\EventLoading\Infrastructure\RabbitMq\RabbitMqStreamEventSource:
    arguments:
      $streamClient: '@App\EventLoading\Infrastructure\RabbitMq\PhpAmqplibStreamClient'
      $streamName: 'orders-stream'
      $sourceName: 'orders'
    tags: ['app.event_source']
```

### Example: run loader + retry worker
```bash
docker compose up -d --build
docker compose run --rm db psql -U app -d app -f /app/sql/schema.sql
docker compose run --rm app php bin/console app:inbox-retry-worker --once
```

### E2E test
There is a real E2E test guarded by `RABBITMQ_DSN` and `DATABASE_URL`.
To enable it:

```
RABBITMQ_DSN=amqp://app:app@localhost:5672/%2f
DATABASE_URL=postgresql://app:app@localhost:5433/app_test?serverVersion=16&charset=utf8
```

Run:

```bash
docker compose up -d rabbitmq db_test
docker compose run --rm app php bin/reset-test-db.php
docker compose run --rm app ./vendor/bin/phpunit --testsuite=e2e
```

Schema is in `sql/schema.sql`. To initialize:

```bash
docker compose run --rm db psql -U app -d app -f /app/sql/schema.sql
```

## Key design points
- **No duplicate network requests**: events are persisted to the inbox before the request cursor is advanced.
- **Global 200 ms rate limit per source**: enforced by a distributed `SourceRateLimiterInterface`.
- **Round-robin infinite loop**: the loader iterates sources forever and sleeps briefly when idle.
- **Error handling**: network failures are represented by `EventSourceUnavailableException` and are logged then skipped.
- **Source contract**: each source must support `fetchEvents(afterId, limit)` semantics and return events ordered by increasing ID (equivalent to `SELECT * FROM events WHERE id > ? ORDER BY id LIMIT 1000`).

## Files of interest
- `src/EventLoading/EventLoader.php` – main orchestration loop and error handling.
- `src/EventLoading/Contracts/*` – interfaces for source retrieval, storage, coordination, and logging.
- `src/EventLoading/Exception/EventSourceUnavailableException.php` – network failure marker.

## How to integrate in Symfony
1. Create concrete services for:
   - `SourceLeaseManagerInterface` (distributed lock, e.g., Redis, DB row lock, etc.).
   - `SourceRateLimiterInterface` (shared store of last request timestamp per source).
   - `SourceCursorStoreInterface` (shared store of `lastRequestedId` and `lastStoredId`).
   - `EventInboxInterface` (persist fetched events before advancing `lastRequestedId`).
   - `EventStorageInterface` (persist to DB).
   - `SourceRegistryInterface` (list available `EventSourceInterface` instances).
   - `LoggerInterface` (adapt to your logger or PSR-3).
2. Register `EventLoader` as a service and run it in a worker/command.

## Notes on correctness
- If a request is marked `failed`, it is not retried automatically to preserve the "never re-fetch" guarantee. You can reprocess manually if needed.

## Optional local test idea
Create in-memory fake implementations of the interfaces and run `EventLoader::run()` in a CLI command. Use a short `stop()` timer to end the loop after a few cycles and assert that each event ID is fetched exactly once.
