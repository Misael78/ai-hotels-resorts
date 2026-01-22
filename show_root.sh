#!/usr/bin/env bash
cd /home/dubon/sites/drupal-11X/docker4drupal
pwd
echo "=== LISTA ROOT ==="
ls -la
echo
echo "=== ESTRUCTURA (niveles 1-2) ==="
if command -v tree >/dev/null 2>&1; then
  tree -a -L 2
else
  find . -maxdepth 2 -type d -print | sed 's|^\./|./|'
fi
echo
echo "=== ARCHIVOS IMPORTANTES (existencia y primeras líneas) ==="
for f in docker-compose.yml docker-compose.yaml composer.json .env .gitignore; do
  if [ -f "$f" ]; then
    echo "----- $f (existe) -----"
    sed -n '1,20p' "$f"
  else
    echo "----- $f (NO existe) -----"
  fi
done
echo
echo "=== GIT (si existe) ==="
if [ -d .git ]; then
  git rev-parse --abbrev-ref HEAD 2>/dev/null || true
  git status --porcelain 2>/dev/null || true
else
  echo "No hay repo git (.git no existe en este directorio)"
fi
