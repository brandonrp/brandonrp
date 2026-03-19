#!/usr/bin/env bash
# Full project + database backup for brandonrp.com (Trellis/WordPress).
# Usage: ./backup-full.sh [destination_dir]
# Default destination: backups/YYYY-MM-DD-HHMM-full

set -e
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$REPO_ROOT"

STAMP="$(date +%Y-%m-%d-%H%M)"
DEST="${1:-backups/${STAMP}-full}"
mkdir -p "$DEST"
# Resolve to absolute path so subshells (e.g. cd trellis) can write to it
DEST_ABS="$(cd "$DEST" && pwd)"

echo "Backup destination: $DEST"

# Exclude paths we don't need or that are large/sensitive.
# Excluding 'backups' so we don't copy previous backups into this one.
echo "Copying project files..."
rsync -a \
  --exclude='.vagrant' \
  --exclude='.vagrant_home' \
  --exclude='node_modules' \
  --exclude='backups' \
  ./ "$DEST/"

# Database dump from Vagrant VM (development)
DB_FILE="$DEST_ABS/database.sql"
DB_ERR="$DEST_ABS/database-export-errors.txt"
if (cd trellis && vagrant status --machine-readable 2>/dev/null | grep -q 'state,running'); then
  echo "Dumping database from VM..."
  if (cd trellis && vagrant ssh -c 'cd /srv/www/brandonrp.test/current && wp db export /tmp/db-backup.sql && cat /tmp/db-backup.sql && rm -f /tmp/db-backup.sql' 2>"$DB_ERR") > "$DB_FILE"; then
    if [[ -s "$DB_FILE" ]]; then
      echo "Database saved to $DB_FILE"
      rm -f "$DB_ERR"
    else
      echo "Warning: database dump empty; see $DB_ERR"
      rm -f "$DB_FILE"
    fi
  else
    echo "Warning: database dump failed; see $DB_ERR"
    rm -f "$DB_FILE"
  fi
else
  echo "Vagrant VM not running; skipping database dump."
  echo "Start the VM and run this script again to include the database." > "$DEST/database-skipped.txt"
fi

echo "Done. Backup is in $DEST"
