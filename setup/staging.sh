#!/bin/bash

BRANCH_NAME="dev"
if [ $# -gt 0 ]; then
  BRANCH_NAME="$1"
fi

curl -O "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/${BRANCH_NAME}/docker-compose.yml"
curl -O "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/${BRANCH_NAME}/docker-compose.staging.yml"

# Load environment variables from .env.local if it exists
if [ -f ".env.local" ]; then
  source .env.local
fi

if [ -z "${CADDY_MERCURE_JWT_SECRET}" ]; then
  CADDY_MERCURE_JWT_SECRET=$(echo $RANDOM | md5sum | head -c 32)
fi

# Append variables to .env.local
cat <<EOF > .env.local
CADDY_MERCURE_JWT_SECRET=${CADDY_MERCURE_JWT_SECRET}
EOF

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.staging.yml" up -d
