#!/bin/bash
# deploy.sh — Deploy / update Byabsayee on this server.
#
# Usage:
#   ./deploy.sh           — full build + restart (use after Dockerfile changes)
#   ./deploy.sh reload    — reload nginx config without rebuilding
#   ./deploy.sh composer  — re-run composer install inside the running container
#
# No GitHub required. Just run this from the byabsayee folder.

set -e
cd "$(dirname "$0")"

case "${1:-}" in

  reload)
    echo "=== Reloading nginx config ==="
    docker exec byabsayee_nginx nginx -s reload
    echo "Done."
    ;;

  composer)
    echo "=== Running composer install ==="
    docker exec byabsayee composer install \
      --working-dir=/Sites/byabsayee \
      --no-dev --optimize-autoloader --no-interaction
    echo "Done."
    ;;

  *)
    echo "=== Building Byabsayee image ==="
    docker compose build byabsayee

    echo "=== Starting all services ==="
    docker compose up -d

    echo ""
    echo "✓  Byabsayee is running at http://$(hostname -I | awk '{print $1}'):1021"
    echo "   phpMyAdmin : http://$(hostname -I | awk '{print $1}'):8093"
    echo ""
    echo "Tip: edit any PHP/view/CSS/JS file and refresh — no rebuild needed."
    ;;

esac
