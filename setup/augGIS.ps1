$branch_name = "msp-ar"
if ($args.Count -gt 0) {
    $branch_name = $args[0]
}

# Check if .env exists and copy it to .env.local
if (Test-Path ".env") {
    Copy-Item -Path ".env" -Destination ".env.local" -Force
}

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

if (-not $env:NEXUS_CREDENTIALS) {
    $env:NEXUS_CREDENTIALS = Read-Host "Enter Nexus credentials"
}
if (-not $env:NEXUS_ANTI_CSRF_TOKEN ) {
    $env:NEXUS_ANTI_CSRF_TOKEN  = Read-Host "Enter Nexus anti-CSRF token"
}

if (-not $env:NEXUS_ANTI_CSRF_TOKEN -or -not $env:NEXUS_CREDENTIALS) {
    Write-Output "Nexus environment variables (NEXUS_ANTI_CSRF_TOKEN and NEXUS_CREDENTIALS) need to be set."
    exit 1
}

docker build --no-cache -t cradlewebmaster/docker-api:latest https://raw.githubusercontent.com/BredaUniversityResearch/ImmersiveTwins-UnityServer-Docker/refs/heads/main/api/Dockerfile
docker run --name docker-api -d -p 2375:2375 -v /var/run/docker.sock:/var/run/docker.sock cradlewebmaster/docker-api:latest
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.prod.yml" -OutFile "docker-compose.prod.yml"

if (-not $env:SERVER_NAME) {
    $env:SERVER_NAME = Read-Host "Enter your domain name (default: localhost)"
    if (-not $env:SERVER_NAME) {
        $env:SERVER_NAME = "localhost"
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
NEXUS_CREDENTIALS=$env:NEXUS_CREDENTIALS
NEXUS_ANTI_CSRF_TOKEN=$env:NEXUS_ANTI_CSRF_TOKEN
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
exit 0