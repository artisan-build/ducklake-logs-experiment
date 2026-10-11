# Ingest contract and bucket layout

This contract deliberately separates the PHP ingest edge from the DuckLake writer. A future Rust
endpoint can replace PHP by changing only the configured base URL in the driver; it must preserve
the HTTP and staging semantics below.

## HTTP contract

`POST /api/v1/logs` with `Authorization: Bearer <per-app ingest token>` and JSON:

```json
{
  "idempotency_key": "unique for this app and batch",
  "logs": [
    {
      "id": "UUID",
      "timestamp": "2026-10-11T10:00:00Z",
      "level": "error",
      "channel": "stack",
      "message": "raw message",
      "context": {"any": "JSON value"},
      "extra": {"any": "JSON value"}
    }
  ]
}
```

One request contains 1–500 records. The token selects the app; clients cannot choose an app ID.
The server hashes tokens with SHA-256 and stores only the hash. `(app, idempotency_key)` is unique.

- `202`: newly accepted into the durable Postgres staging table. Body:
  `{"batch_id":"UUID","accepted":123,"duplicate":false}`.
- `200`: the same app and idempotency key was accepted earlier. The response repeats the original
  batch ID/count with `duplicate:true`.
- `401`: missing or invalid bearer token.
- `422`: validation failure. A body over the platform limit may be rejected before Laravel.

`202` means durable acceptance, not immediate searchability. The scheduled flusher claims batches
with `FOR UPDATE SKIP LOCKED`, writes one DuckLake snapshot, and leaves only the idempotency receipt
and counters in Postgres. Target visibility is under two minutes; the experiment records the actual
round-trip latency.

## Storage contract

The catalog lives in attached Cloud Postgres. Long-term log rows do not. The data path is the
attached bucket under:

```text
s3://<cloud-bucket>/ducklake/logs/
```

DuckLake owns filenames. The `logs` table is partitioned by `app_slug` and `event_date`; current
DuckLake emits Hive-style paths. The runtime creates temporary DuckDB secrets from Laravel's
resolved Postgres and S3 configuration for each CLI process. Credentials are sent to DuckDB on
stdin and are never persisted in DuckDB secret storage or a file.

The flusher disables data inlining (`DATA_INLINING_ROW_LIMIT 0`) because the product requirement is
that log rows land in Parquet, not the catalog. A daily maintenance command explicitly merges
adjacent small files, then runs `CHECKPOINT` to rewrite data files, expire eligible snapshots, and
clean old or orphaned files. Thirty days is the experiment's safety window for snapshot/file
deletion.

## Compatibility boundary for a Rust ingest edge

A Rust service must implement the same auth, validation limits, idempotency key, response codes,
and durable-before-`202` guarantee. It may write the same Postgres staging rows, or write compatible
NDJSON objects to a documented future staging prefix. It must not write Parquet outside DuckLake's
catalog transaction. Nothing in the current driver, lake schema, bucket path, or MCP query surface
requires PHP.
