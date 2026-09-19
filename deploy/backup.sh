#!/usr/bin/env bash
# Daily database backup. Keeps 14 daily copies and 8 weekly copies, and checks each file it writes.
#
# The database holds everything that matters: payments, the wallet ledger, customer logins.
# Put the MySQL credentials in /root/.my.cnf (mode 600) so no password appears on the command line:
#
#   [client]
#   user=trinetpay_backup
#   password=...
#
# Copy the folder to another machine as well. A backup on the same disk is lost with the disk.
set -euo pipefail

DB_NAME="captiveportalapi"
BACKUP_DIR="/var/backups/trinetpay"
KEEP_DAILY=14
KEEP_WEEKLY=8

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly"
stamp="$(date +%Y-%m-%d_%H%M)"
file="$BACKUP_DIR/daily/${DB_NAME}_${stamp}.sql.gz"

# --single-transaction gives a consistent copy without locking the site.
mysqldump --single-transaction --routines --triggers --quick "$DB_NAME" | gzip -9 > "$file"

# An empty or truncated file is worse than none: fail loudly.
gzip -t "$file"
if [ "$(stat -c %s "$file")" -lt 2048 ]; then
    echo "Backup is suspiciously small: $file" >&2
    exit 1
fi

# Sundays also become a weekly copy.
if [ "$(date +%u)" = "7" ]; then
    cp "$file" "$BACKUP_DIR/weekly/"
fi

# Remove old copies.
ls -1t "$BACKUP_DIR"/daily/*.sql.gz 2>/dev/null | tail -n +$((KEEP_DAILY + 1)) | xargs -r rm --
ls -1t "$BACKUP_DIR"/weekly/*.sql.gz 2>/dev/null | tail -n +$((KEEP_WEEKLY + 1)) | xargs -r rm --

# Optional: copy off the machine. Uncomment and set up rclone or rsync.
# rclone copy "$BACKUP_DIR" remote:trinetpay-backups

echo "Backup written: $file"
