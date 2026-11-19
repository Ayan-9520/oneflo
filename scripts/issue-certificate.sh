#!/bin/sh
set -eu

if [ "$#" -lt 1 ]; then
  echo "Usage: scripts/issue-certificate.sh app.oneflo.in api.oneflo.in oneflo.in" >&2
  exit 1
fi

root=$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)
cd "$root"
domains=""
for domain in "$@"; do
  domains="$domains -d $domain"
done

docker compose -f docker-compose.prod.yml stop nginx
docker compose -f docker-compose.prod.yml --profile tools run --rm --service-ports certbot certonly --standalone --agree-tos --register-unsafely-without-email $domains
docker compose -f docker-compose.prod.yml start nginx
