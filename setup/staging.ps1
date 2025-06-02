$branch_name = "dev"
if ($args.Count -gt 0) {
    $branch_name = $args[0]
}

Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.staging.yml" -OutFile "docker-compose.staging.yml"

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

if (-not $env:CADDY_MERCURE_JWT_SECRET) {
    $secret = -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $env:CADDY_MERCURE_JWT_SECRET = $secret
}

# Append variables to .env.local
Set-Content -Path ".env.local" -Value @"
CADDY_MERCURE_JWT_SECRET=$env:CADDY_MERCURE_JWT_SECRET
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.staging.yml" up -d
