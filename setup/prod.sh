#!/bin/bash

BRANCH_NAME="main"
if [ $# -gt 0 ]; then
  BRANCH_NAME="$1"
fi

curl -O "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/${BRANCH_NAME}/docker-compose.yml"
curl -O "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/${BRANCH_NAME}/docker-compose.prod.yml"

# Load environment variables from .env.local if it exists
if [ -f ".env.local" ]; then
  source .env.local
fi

if [ -z "${SERVER_NAME}" ]; then
  read -p "Enter your domain name (default: localhost): " SERVER_NAME
fi

if [ -z "${CADDY_MERCURE_JWT_SECRET}" ]; then
  CADDY_MERCURE_JWT_SECRET=$(echo $RANDOM | md5sum | head -c 32)
fi

# Append variables to .env.local
cat <<EOF > .env.local
SERVER_NAME=${SERVER_NAME:-localhost}
URL_WEB_SERVER_HOST=${SERVER_NAME}
URL_WS_SERVER_HOST=${SERVER_NAME}
URL_WS_SERVER_URI=/ws/
URL_WS_SERVER_SCHEME=wss
URL_WEB_SERVER_SCHEME=https
URL_WS_SERVER_PORT=443
URL_WEB_SERVER_PORT=443
CADDY_MERCURE_JWT_SECRET=${CADDY_MERCURE_JWT_SECRET}
EOF

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
