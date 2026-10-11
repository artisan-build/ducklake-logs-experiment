# DuckLake logs experiment

A throwaway Laravel Cloud prototype for a central log sink: Laravel applications install the path
package in `packages/log-driver`, authenticated HTTP batches are durably staged in Postgres, and a
scheduled DuckDB CLI process commits them into DuckLake (Postgres catalog, Parquet in a Cloud
bucket). The app provides a small operator UI and a first-party `laravel/mcp` server with fixed log
search tools.

This is evidence-producing experiment code, not a production package.

Self-registration is disabled. Provision or rotate an operator login with `php artisan
logs:create-admin operator@example.test`; the generated password is displayed once.

## Raw-log warning

The driver sends logs **unmodified by default**, including message text, nested context, exception
traces, and extra fields. Those values can contain secrets, PII, tokens, cookies, and user-supplied
prompt injection. Bind the payload-filter contract before enabling the channel if that is not
acceptable. See [the agent redaction guide](docs/agent-redaction-guide.md).

## Documents

- [Ingest contract and bucket layout](docs/ingest-contract.md)
- [Agent redaction guide](docs/agent-redaction-guide.md)

## Local proof

```sh
./scripts/install-duckdb.sh
php artisan migrate
php artisan logs:bootstrap-lake
php artisan test
vendor/bin/pint --test
```

The MCP endpoint is `/mcp`; every tool response labels log fields as untrusted content. There is no
tool that accepts raw SQL.
TEMPORARY experiment: central Laravel log sink on DuckLake (Parquet on a Cloud bucket + Postgres catalog) with an MCP for agents. Will be deleted/renamed.
