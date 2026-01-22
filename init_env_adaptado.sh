#!/usr/bin/env bash
# init_env_adaptado.sh
# Adaptado para /home/dubon/sites/drupal-11X/docker4drupal
# - añade reglas críticas a .gitignore sin duplicados
# - genera .env.example sanitizado (backup .env.example.bak)
# - inicializa git, crea rama assist/working-YYYYMMDD y opcionalmente hace commit
set -euo pipefail

ROOT_DIR="$(pwd)"
echo "== Init repo & env helper - directorio: $ROOT_DIR =="
echo

# comprobación rápida: asegurarnos de estar en el proyecto esperado
if [ ! -d web ]; then
  echo "ADVERTENCIA: no se detectó carpeta 'web' en el directorio actual."
  echo "Asegúrate de ejecutar este script desde /home/dubon/sites/drupal-11X/docker4drupal"
  read -p "Continuar de todos modos? (yes/no) " ok
  if [ "$ok" != "yes" ]; then
    echo "Cancelado."
    exit 1
  fi
fi

# Asegurar .gitignore existe
if [ ! -f .gitignore ]; then
  touch .gitignore
  echo ".gitignore creado vacío."
fi

# Función para añadir línea si falta
add_if_missing() {
  local line="$1"
  local file=".gitignore"
  # crear file si no existe (ya lo hicimos)
  grep -qxF "$line" "$file" || echo "$line" >> "$file"
}

echo "Asegurando entradas críticas en .gitignore..."
add_if_missing ".env"
add_if_missing "/vendor/"
add_if_missing "/mariadb/"
add_if_missing "mariadb-init"
add_if_missing "*.sql"
add_if_missing ".vscode/"
add_if_missing ".idea/"
add_if_missing "tmp/"
add_if_missing "logs/"
add_if_missing ".DS_Store"
add_if_missing "*.bak"
add_if_missing "*.log"
echo "Entradas añadidas (si faltaban). Revisa .gitignore."

# Manejo de .env -> .env.example sanitizado
if [ -f .env ]; then
  if [ -f .env.example ]; then
    echo ".env.example ya existe -> se creará backup .env.example.bak y se generará .env.example.sanitized"
    cp .env.example .env.example.bak
    cp .env.example .env.example.sanitized
  else
    echo "No se encontró .env.example -> se copiará .env -> .env.example.sanitizado (backup no aplica)."
    cp .env .env.example
    cp .env .env.example.sanitized
  fi

  # Sanitizar .env.example.sanitized: reemplazar valores sensibles por REPLACE_ME
  # Usamos sed para patrones comunes. También guardamos original en .env.example.bak si existe.
  echo "Sanitizando .env.example.sanitized (valores sensibles -> REPLACE_ME)..."

  sed -E -i.bak \
    -e 's/^[[:space:]]*(DB_PASSWORD|DB_ROOT_PASSWORD|MYSQL_ROOT_PASSWORD|MYSQL_PASSWORD|ROOT_PASSWORD|ADMIN_PASSWORD|PASSWORD)[[:space:]]*=.*/\1=REPLACE_ME/i' \
    -e 's/^[[:space:]]*(DRUPAL_HASH_SALT|SECRET_KEY|APP_SECRET|SECRET|API_KEY|APIKEY|AWS_SECRET_ACCESS_KEY|AWS_SECRET_KEY|GOOGLE_APPLICATION_CREDENTIALS|TOKEN)[[:space:]]*=.*/\1=REPLACE_ME/i' \
    -e 's/^[[:space:]]*(MAIL_PASSWORD|SMTP_PASSWORD)[[:space:]]*=.*/\1=REPLACE_ME/i' \
    -e 's/^[[:space:]]*([A-Za-z0-9_]*PASSWORD[A-Za-z0-9_]*)[[:space:]]*=.*/\1=REPLACE_ME/i' \
    -e 's/^[[:space:]]*([A-Za-z0-9_]*SECRET[A-Za-z0-9_]*)[[:space:]]*=.*/\1=REPLACE_ME/i' \
    -e 's/^[[:space:]]*PRIVATE_KEY[[:space:]]*=.*/PRIVATE_KEY=REPLACE_ME/i' \
    -e 's/^[[:space:]]*DB_NAME[[:space:]]*=.*/DB_NAME=REPLACE_ME/i' \
    -e 's/^[[:space:]]*DB_USER[[:space:]]*=.*/DB_USER=REPLACE_ME/i' \
    -e 's/^[[:space:]]*DB_HOST[[:space:]]*=.*/DB_HOST=REPLACE_ME/i' \
    .env.example.sanitized || true

  # eliminar backup .bak creado por sed (si existe)
  [ -f .env.example.sanitized.bak ] && rm -f .env.example.sanitized.bak

  echo ".env.example.sanitized creado. Se creó backup .env.example.bak si .env.example existía antes."
  echo "IMPORTANTE: revisa .env.example.sanitized y, si estás conforme, renómalo a .env.example manualmente:"
  echo "  mv .env.example.sanitized .env.example"
else
  echo "No existe .env en este directorio: no se creó .env.example.sanitizado."
fi

echo
# Inicializar git si no existe
if [ -d .git ]; then
  echo "Repositorio git ya inicializado."
else
  echo "Inicializando repositorio git..."
  git init
  echo "Repo git inicializado."
fi

BRANCH="assist/working-$(date +%Y%m%d)"
# crear / cambiar a la rama de trabajo
if git rev-parse --verify "$BRANCH" >/dev/null 2>&1; then
  git checkout "$BRANCH"
  echo "Cambiado a rama existente: $BRANCH"
else
  git checkout -b "$BRANCH"
  echo "Rama de trabajo creada: $BRANCH"
fi

echo
echo "PREVIEW: Archivos que se agregarían al commit (según .gitignore actual)."
echo "Se listarán los cambios unstaged (si los hubiera) y luego se mostrará el contenido a commitear."
echo
# Mostrar status actual
git status --porcelain
echo
echo "Archivos que potencialmente añadiremos (lista corta):"
echo "- composer.json"
echo "- composer.lock"
echo "- README.md, compose.yml, traefik.yml, docker.mk, .github/"
echo "- .gitignore"
echo "- .env.example (si decides usar la sanitized)"
echo "- archivos de configuración y código del proyecto (web/, modules/, themes/, recipes/, tests/)"
echo
read -p "¿Quieres que prepare el commit inicial (git add . && git commit -m 'chore: initial import (sanitized)')? (yes/no) " confirm_commit

if [ "$confirm_commit" = "yes" ]; then
  # Asegurarse que .gitignore contenga /vendor/ antes de git add
  grep -qxF "/vendor/" .gitignore || echo "/vendor/" >> .gitignore
  echo "Añadidos cambios y archivos a staging..."
  git add .
  echo "Se añadieron todos los archivos (respeta .gitignore)."
  git commit -m "chore: initial import (sanitized)"
  echo "Commit creado en la rama $BRANCH"
  echo
  read -p "¿Deseas añadir un remote y hacer push ahora? (yes/no) " do_remote
  if [ "$do_remote" = "yes" ]; then
    read -p "Introduce la URL del remote (ej. git@github.com:usuario/repo.git): " remote_url
    git remote add origin "$remote_url"
    git push -u origin "$BRANCH"
    echo "Push realizado a $remote_url -> $BRANCH"
  else
    echo "No se añadió remote ni se hizo push."
  fi
else
  echo "No se realizó commit. Revisa .env.example.sanitized y .gitignore antes de commitear."
fi

echo
echo "Tareas recomendadas a continuación:"
echo "1) Revisa .env.example.sanitized y reemplaza la original si estás seguro:"
echo "   mv .env.example.sanitized .env.example"
echo "2) Revisa .gitignore y añade reglas adicionales si necesitas (p.ej. archivos temporales locales)."
echo "3) Cuando estés listo, crea el commit inicial (si no lo hiciste en este script)."
echo "4) No incluyas dumps (.sql), credenciales ni vendor/ al push público."
echo
echo "Script finalizado."
