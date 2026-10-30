#!/bin/sh
set -e
echo "RMM server starting on :8080 (root=/app/public)"
# Route everything through index.php so /api/* paths are handled by our router.
exec php -S 0.0.0.0:8080 -t /app/public /app/public/index.php
