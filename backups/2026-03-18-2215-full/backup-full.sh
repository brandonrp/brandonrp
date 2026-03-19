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
DB_FILE="$DEST/database.sql"
if (cd trellis && vagrant status --machine-readable 2>/dev/null | grep -q 'state,running'); then
  echo "Dumping database from VM..."
  (cd trellis && vagrant ssh -c "cd /srv/www/brandonrp.test/current && wp db export - 2>/dev/null") > "$DB_FILE" 2>/dev/null || true
  if [[ -s "$DB_FILE" ]]; then
    echo "Database saved to $DB_FILE"
  else
    echo "Warning: database dump empty or failed; removing."
    rm -f "$DB_FILE"
  fi
else
  echo "Vagrant VM not running; skipping database dump."
  echo "Start the VM and run this script again to include the database." > "$DEST/database-skipped.txt"
fi

echo "Done. Backup is in $DEST"
