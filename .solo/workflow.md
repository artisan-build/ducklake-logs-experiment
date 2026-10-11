# Workflow: ducklake-logs-experiment

**THROWAWAY EXPERIMENT (Ed, 2026-10-11).** The question it answers: *can we build a central log sink for an org with
many Laravel apps (a log driver in each app that ships to one ingest endpoint, plus an MCP so agents can explore every
app's logs) on Laravel Cloud, with the logs stored as Parquet on a Cloud bucket through DuckLake and a Postgres catalog?*
It is also a probe for a good architecture for ingest-heavy products in general, including future versions of Hone.
If it works, we name it and REBUILD it from what we learned, so this code is not expected to survive. Learnings are
harvested into `~/Herd/brain/projects/ducklake-logs-experiment/` and an `ideas/` note. "Not right now, because X" is a
valid outcome.

## Phase & mode
- phase: experiment
- default mode: A-autonomous. **Commit directly to `main`** in small commits and push. No PRs (Ed, 2026-10-11).
- **Deploy on push:** the Cloud app deploys automatically from `main`.
- The repo is PUBLIC and TEMPORARY. Nothing secret goes in it, ever.
- Deploy target: the Artisan Build Laravel Cloud org (`org-9e4a1722-9442-404e-abdb-1ca55f845597`). Ed has
  pre-authorised creating and deploying this experiment's OWN Cloud app(s), environments and resources. Nothing else
  in the org may be touched.

## Hard gate
- `php artisan test` for anything you add tests for, plus `vendor/bin/pint --test`.
- Behaviour that matters is LIVE behaviour on Cloud. Tests are secondary in an experiment.

## Agent-role constraints
- none. Fleet bindings come from `~/Herd/brain/agents.json`.

## Hard rules for this repo
- Never set a Cloud env var for a Cloud-provisioned resource (DB, bucket, queue and cache credentials are injected;
  load the `laravel-cloud-deploy` skill). App secrets may be set by hand. Secrets never go on disk or into git.
- `.cloud/config.json` is committed (brain standing policy).
- Scaffolded from `artisan-build/laravel-nodeless` (no Node).
