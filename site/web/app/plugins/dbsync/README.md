# DBSync

`wp dbsync` is a WP-CLI plugin that syncs your WordPress database between environments (local <-> production) and can also sync media uploads.

It’s designed to be safe by default:
- All commands run in **dry-run preview mode** unless you pass `--run`.
- The database import/export uses `wp db export` / `wp db import` (with `--add-drop-table`), so the destination tables are replaced.
- After import, `sync` runs a serialized-safe URL remap using `wp search-replace --precise`.

## Requirements

- WP-CLI available on the local machine.
- WP-CLI available on the production server (the plugin uses WP-CLI `--ssh` internally for remote core commands).
- `rsync` available on your local machine (used for transferring dump files and syncing uploads).
- `gzip` available on whichever side runs the DB export when `--compress` is enabled.

## Commands

### Status

```bash
# Local
wp dbsync status --env=local

# Production
wp dbsync status --env=prod --remote-ssh=root@prod.example.com~/srv/www/brandonrp.com/current/site
```

### Export (dump DB to a file)

```bash
# Preview
wp dbsync export --output=/tmp/site-dump.sql --compress

# Execute
wp dbsync export --output=/tmp/site-dump.sql --compress --run
```

For prod:

```bash
# Note: `dbsync export/import` are local-only.
# To export/import production and sync everything, use `wp dbsync sync`.
```

Optional table exclusions:

```bash
wp dbsync export --output=/tmp/site-dump.sql --exclude-tables=wp_options,wp_actions_log
```

### Import (load DB from a file)

```bash
# Preview
wp dbsync import --input=/tmp/site-dump.sql.gz

# Execute
wp dbsync import --input=/tmp/site-dump.sql.gz --run
```

### Sync database (prod <-> local)

Dry-run preview first:

```bash
wp dbsync sync --from=prod --to=local --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site --compress
```

Then execute:

```bash
wp dbsync sync --from=prod --to=local --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site --compress --run
```

For the opposite direction:

```bash
wp dbsync sync --from=local --to=prod --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site --compress
wp dbsync sync --from=local --to=prod --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site --compress --run
```

### Sync media uploads (push-only; uploads only)

Dry-run preview:

```bash
wp dbsync media-sync --from=prod --to=local --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site
```

Execute:

```bash
wp dbsync media-sync --from=prod --to=local --remote-ssh=user@prod.example.com~/srv/www/brandonrp.com/current/site --run
```

Notes:
- Media sync is **uploads-only** (it rsyncs `wp_upload_dir()["basedir"]`).
- It’s **push-only** (no deletions on the destination).

## Admin Panel (WP-CLI execution wrapper)

This plugin also adds an admin page at:

`wp-admin -> DB Sync` (menu slug `dbsync`)

From there you can:
- Preview (dry-run) which runs `wp dbsync ...` without `--run` and shows output in the page log panel
- Run which executes `wp dbsync ... --run` in the background and streams output to a log (viewable on the same page via the job status panel)


## Notes on `--remote-ssh`

`--remote-ssh` is the target used for production-side operations.

If you use the WP-CLI extended syntax like:

`--remote-ssh=root@prod.example.com~/srv/www/brandonrp.com/current/site`

the plugin will extract just the `root@prod.example.com` portion for `rsync`/scp-style transfers, and pass the full value to WP-CLI for remote execution.

