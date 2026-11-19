#!/bin/sh
set -eu

cd /app

if [ ! -d node_modules/react ]; then
  npm ci
fi

exec "$@"
