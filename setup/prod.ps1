$branch_name = "main"
if ($args.Count -gt 0) {
    $branch_name = $args[0]
}

Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.prod.yml" -OutFile "docker-compose.prod.yml"

# Load environment variables from .env.local if it exists
if (Test-Path ".env.local") {
    Get-Content ".env.local" | ForEach-Object {
        if ($_ -match "^(.*)=(.*)$") {
            if ($matches.Count -ge 3) {
                [Environment]::SetEnvironmentVariable($matches[1], $matches[2])
            }
        }
    }
}

if (-not $env:SERVER_NAME) {
    $env:SERVER_NAME = Read-Host "Enter your domain name (default: localhost)"
    if (-not $env:SERVER_NAME) {
        $env:SERVER_NAME = "localhost"
    }
}

if (-not $env:CADDY_MERCURE_JWT_SECRET) {
    $secret = -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $env:CADDY_MERCURE_JWT_SECRET = $secret
}

# Append variables to .env.local
Set-Content -Path ".env.local" -Value @"
SERVER_NAME=$env:SERVER_NAME
URL_WEB_SERVER_HOST=$env:SERVER_NAME
URL_WS_SERVER_HOST=$env:SERVER_NAME
URL_WS_SERVER_URI=/ws/
URL_WS_SERVER_SCHEME=wss
URL_WEB_SERVER_SCHEME=https
URL_WS_SERVER_PORT=443
URL_WEB_SERVER_PORT=443
CADDY_MERCURE_JWT_SECRET=$env:CADDY_MERCURE_JWT_SECRET
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
