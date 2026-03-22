# DBSync

## Debug: compare local vs production

`wp dbsync compare` needs a **working local database** (same as any `wp` command). On a Trellis setup, MySQL usually only exists **inside the VM**, not on your Mac—so run this **after `vagrant ssh`**, from the Bedrock directory that contains `wp-cli.yml`:

```bash
cd trellis
vagrant ssh

# Inside the VM (paths may match your site name):
cd /srv/www/brandonrp.test/current/site

wp dbsync compare --remote-ssh='root@64.23.203.166~/srv/www/brandonrp.com/current'
```

Adjust the path after `~` on production to the directory that contains `wp-cli.yml`.

Optional custom post type counts:

```bash
wp dbsync compare --remote-ssh='root@64.23.203.166~/srv/www/brandonrp.com/current' --check-post-type=project
```

**What it checks:** `table_prefix` (must match for imports), core version, post/page/CPT counts. If prefixes differ, align `DB_PREFIX` in production `.env` with local before syncing again.
