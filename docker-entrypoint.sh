#!/bin/sh
set -e

# Create required directories if they don't exist
mkdir -p /Sites/byabsayee/storage/sessions \
         /Sites/byabsayee/uploads

chown -R www-data:www-data \
    /Sites/byabsayee/storage/sessions \
    /Sites/byabsayee/uploads

# =============================================================================
# Auto-import database schema on fresh install
# This only runs when the database has no tables yet (brand new install).
# On every subsequent restart it skips this step instantly.
# =============================================================================
echo "[Byabsayee] Waiting for database to be ready..."
RETRIES=30
until mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" >/dev/null 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "[Byabsayee] ERROR: Could not connect to database after 30 attempts. Exiting."
        exit 1
    fi
    sleep 2
done

TABLE_COUNT=$(mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -N -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null || echo 0)

if [ "$TABLE_COUNT" = "0" ]; then
    echo "[Byabsayee] Fresh database detected — importing schema..."
    mariadb -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < /Sites/byabsayee/schema.sql
    echo "[Byabsayee] Schema imported successfully. Byabsayee is ready!"
else
    echo "[Byabsayee] Database already set up (${TABLE_COUNT} tables found). Skipping schema import."
fi

exec "$@"
