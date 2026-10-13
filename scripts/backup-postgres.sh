#!/bin/sh
set -eu

root="$(cd "$(dirname "$0")/.." && pwd)"
stamp="$(date +%Y%m%d-%H%M%S)"
out="$root/backups/postgres/hafline-$stamp.sql"

mkdir -p "$root/backups/postgres"
docker exec hafline-postgres pg_dump -U hafline -d hafline > "$out"
echo "$out"
