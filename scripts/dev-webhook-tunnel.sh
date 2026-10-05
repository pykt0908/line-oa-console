#!/usr/bin/env bash
set -euo pipefail

# Expose local Laravel API so LINE can deliver webhooks during development.
# Requires cloudflared: https://developers.cloudflare.com/cloudflare-one/connections/connect-apps/install-and-setup/installation/
#
# Usage:
#   1) Start API:  cd backend && php artisan serve
#   2) Start queue (optional): php artisan queue:work
#   3) Run this script
#   4) Copy the https://*.trycloudflare.com URL into LINE Developers webhook:
#        {TUNNEL_URL}/api/webhooks/line/{lineAccountUuid}

PORT="${1:-8000}"

if ! command -v cloudflared >/dev/null 2>&1; then
  echo "cloudflared not found."
  echo "Install (macOS): brew install cloudflare/cloudflare/cloudflared"
  echo "Or download: https://github.com/cloudflare/cloudflared/releases"
  exit 1
fi

echo "Tunneling http://localhost:${PORT} ..."
echo "After it starts, set LINE webhook to:"
echo "  https://<tunnel-host>/api/webhooks/line/<lineAccountUuid>"
exec cloudflared tunnel --url "http://localhost:${PORT}"
