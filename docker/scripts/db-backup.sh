#!/bin/bash
# Postgres dump — günde 2 kez (00:00 ve 12:00 Europe/Istanbul)
# Dosya: /backup/hafline_YYYY-MM-DD_HH-MM.sql.gz
# 90 günden eski yedekler silinir.
set -eu

export TZ="${TZ:-Europe/Istanbul}"
HOST="${PGHOST:-postgres}"
PORT="${PGPORT:-5432}"
USER="${PGUSER:-hafline}"
PASS="${PGPASSWORD:-hafline}"
DB="${PGDATABASE:-hafline}"
KEEP_DAYS="${KEEP_DAYS:-90}"
DIR=/backup

mkdir -p "$DIR"
export PGPASSWORD="$PASS"

do_backup() {
  local stamp out
  stamp=$(date +%Y-%m-%d_%H-%M)
  out="$DIR/hafline_${stamp}.sql.gz"
  echo "[backup] $(date '+%Y-%m-%d %H:%M:%S %Z') -> $out"
  pg_dump -h"$HOST" -p"$PORT" -U"$USER" -d"$DB" \
    --no-owner --no-acl --clean --if-exists \
    | gzip -9 > "$out"
  echo "[backup] ok ($(wc -c < "$out") bytes)"
  # 90 günden eski dump'ları sil
  find "$DIR" -name 'hafline_*.sql.gz' -type f -mtime +"$KEEP_DAYS" -print -delete 2>/dev/null || true
}

# Bir sonraki 00:00 veya 12:00'a kalan saniye
seconds_until_next_slot() {
  local now noon tomorrow_midnight
  now=$(date +%s)
  noon=$(date -d "today 12:00:00" +%s)
  if [ "$now" -lt "$noon" ]; then
    echo $((noon - now))
  else
    tomorrow_midnight=$(date -d "tomorrow 00:00:00" +%s)
    echo $((tomorrow_midnight - now))
  fi
}

echo "[backup] started TZ=$TZ host=$HOST db=$DB keep_days=$KEEP_DAYS"
echo "[backup] next run in $(seconds_until_next_slot)s (00:00 ve 12:00)"

while true; do
  sleep "$(seconds_until_next_slot)"
  do_backup || echo "[backup] FAILED exit=$?"
  # Aynı dakikada iki kez tetiklenmesin
  sleep 60
done
