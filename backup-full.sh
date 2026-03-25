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

# Database dump from Vagrant VM (development).
# Export on the guest, copy with scp (fast); avoid streaming the whole SQL through one SSH session.
DB_FILE="$DEST_ABS/database.sql"
DB_ERR="$DEST_ABS/database-export-errors.txt"
REMOTE_TMP="/tmp/db-backup-full-$$.sql"
if (cd trellis && vagrant status --machine-readable 2>/dev/null | grep -q 'state,running'); then
  echo "Dumping database from VM..."
  rm -f "$DB_FILE" "$DB_ERR"
  SSH_CFG="$(mktemp "${TMPDIR:-/tmp}/backup-full-ssh.XXXXXX")"
  cleanup_ssh() { rm -f "$SSH_CFG"; }
  trap cleanup_ssh EXIT
  if !(cd trellis && vagrant ssh-config >"$SSH_CFG" 2>>"$DB_ERR"); then
    echo "Warning: could not read vagrant ssh-config; see $DB_ERR"
  elif ! ssh -F "$SSH_CFG" -o LogLevel=ERROR default \
      "cd /srv/www/brandonrp.test/current && wp db export $(printf %q "$REMOTE_TMP") --add-drop-table" \
      >>"$DB_ERR" 2>&1; then
    echo "Warning: database export on VM failed; see $DB_ERR"
  elif ! scp -F "$SSH_CFG" -o LogLevel=ERROR "default:${REMOTE_TMP}" "$DB_FILE" >>"$DB_ERR" 2>&1; then
    echo "Warning: could not copy database dump from VM; see $DB_ERR"
    rm -f "$DB_FILE"
  elif [[ -s "$DB_FILE" ]]; then
    echo "Database saved to $DB_FILE"
    rm -f "$DB_ERR"
  else
    echo "Warning: database dump empty; see $DB_ERR"
    rm -f "$DB_FILE"
  fi
  ssh -F "$SSH_CFG" -o LogLevel=ERROR -o ConnectTimeout=10 default \
    "rm -f -- $(printf %q "$REMOTE_TMP")" >/dev/null 2>&1 || true
  trap - EXIT
  cleanup_ssh
else
  echo "Vagrant VM not running; skipping database dump."
  echo "Start the VM and run this script again to include the database." > "$DEST/database-skipped.txt"
fi

echo "Done. Backup is in $DEST"
