#!/usr/bin/env bash
set -e
echo "=== INFO: fecha y usuario ==="
date
whoami
echo
echo "=== GIT: estado y ramas ==="
git rev-parse --abbrev-ref HEAD || true
git status --porcelain || true
git log -1 --pretty=format:'%h %an %ad %s' || true
echo
echo "=== DOCKER: containers ==="
docker compose ps --services --filter "status=running" || docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}'
echo
echo "=== DOCKER LOGS: php (ultimas 200 lineas) ==="
docker compose logs --tail=200 php || true
echo
echo "=== PHP (dentro del contenedor php) ==="
docker compose exec php php -v || true
echo
echo "=== COMPOSER (dependencias no-dev) ==="
docker compose exec php composer show --no-dev || true
echo
echo "=== DRUSH STATUS ==="
if [ -x vendor/bin/drush ]; then
  docker compose exec php vendor/bin/drush status || true
else
  echo "No se detectó vendor/bin/drush en el workspace"
fi
echo
echo "=== PERMISOS docroot/web ==="
ls -la | sed -n '1,50p' || true
if [ -d web ]; then ls -la web | sed -n '1,200p'; fi
echo
echo "=== composer.json (primeras 200 líneas) ==="
sed -n '1,200p' composer.json || true
echo
echo "=== docker-compose.yml (primeras 200 líneas) ==="
sed -n '1,200p' docker-compose.yml || true
echo
echo "=== FIN DIAGNOSTICO ==="
