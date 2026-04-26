#!/bin/bash
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Vérifier que Docker tourne
if ! docker info > /dev/null 2>&1; then
    echo -e "${RED}Docker n'est pas démarré. Lance Docker Desktop puis réessaie.${NC}"
    exit 1
fi

# Installer les dépendances PHP si vendor/ manque
if [ ! -f "vendor/autoload.php" ]; then
    echo -e "${YELLOW}Installation des dépendances PHP (première fois, peut prendre quelques minutes)...${NC}"
    docker run --rm \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_PROCESS_TIMEOUT=0 \
        laravelsail/php84-composer:latest \
        php -d default_socket_timeout=3600 /usr/bin/composer install \
            --ignore-platform-reqs --no-interaction --prefer-dist --no-scripts
    echo -e "${GREEN}Dépendances installées.${NC}"
fi

# Lancer les conteneurs (passe les args à docker compose, ex: -d, --build)
echo -e "${GREEN}Démarrage des conteneurs...${NC}"
docker compose up --build "$@"
