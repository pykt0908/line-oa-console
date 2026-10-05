#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${ROOT}/backend/.env"
BACKUP_DIR="${BACKUP_DIR:-${ROOT}/backups}"
MYSQL_BIN="${MYSQL_BIN:-/Applications/XAMPP/xamppfiles/bin/mysqldump}"
DATE="$(date +%Y%m%d_%H%M%S)"

mkdir -p "${BACKUP_DIR}"

DB_DATABASE=$(grep -E '^DB_DATABASE=' "${ENV_FILE}" | cut -d= -f2-)
DB_USERNAME=$(grep -E '^DB_USERNAME=' "${ENV_FILE}" | cut -d= -f2-)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' "${ENV_FILE}" | cut -d= -f2-)
DB_HOST=$(grep -E '^DB_HOST=' "${ENV_FILE}" | cut -d= -f2-)
DB_PORT=$(grep -E '^DB_PORT=' "${ENV_FILE}" | cut -d= -f2-)

OUT="${BACKUP_DIR}/line_chat_archive_${DATE}.sql.gz"

ARGS=(-h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-root}")
if [[ -n "${DB_PASSWORD}" ]]; then
  ARGS+=(-p"${DB_PASSWORD}")
fi

# --skip-routines avoids XAMPP MariaDB mysql.proc upgrade mismatch.
"${MYSQL_BIN}" "${ARGS[@]}" --single-transaction --skip-routines --skip-triggers "${DB_DATABASE}" | gzip > "${OUT}"
echo "Backup written: ${OUT}"
ls -lh "${OUT}"
