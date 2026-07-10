$branch_name = "dev"
if ($args.Count -gt 0) {
    $branch_name = $args[0]
}

New-Item -ItemType Directory -Path ".\docker\database\init" -Force | Out-Null
New-Item -ItemType Directory -Path ".\docker\adminer" -Force | Out-Null
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker/adminer/adminer.css" -OutFile ".\docker\adminer\adminer.css"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker/adminer/index.php" -OutFile ".\docker\adminer\index.php"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker/database/init/01-create-connection-tracker.sql" -OutFile ".\docker\database\init\01-create-connection-tracker.sql"

Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.adminer.yml" -OutFile "docker-compose.adminer.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.staging.yml" -OutFile "docker-compose.staging.yml"

if (-not $env:CADDY_MERCURE_JWT_SECRET) {
    $env:CADDY_MERCURE_JWT_SECRET = [guid]::NewGuid().ToString("N")
}
if (-not $env:APP_SECRET) {
    $env:APP_SECRET = [guid]::NewGuid().ToString("N")
}
if (-not $env:DATABASE_PASSWORD) {
    $env:DATABASE_PASSWORD = [guid]::NewGuid().ToString("N")
    $env:DATABASE_CREATOR_PASSWORD = $env:DATABASE_PASSWORD
}
if (-not $env:DATABASE_CREATOR_PASSWORD) {
    $env:DATABASE_CREATOR_PASSWORD = [guid]::NewGuid().ToString("N")
    $env:DATABASE_PASSWORD = $env:DATABASE_CREATOR_PASSWORD
}
if (-not $env:JWT_PASSPHRASE) {
    $env:JWT_PASSPHRASE = [guid]::NewGuid().ToString("N")
}
if (-not $env:MY2_PASSWORD) {
    $env:MY2_PASSWORD = [guid]::NewGuid().ToString("N")
}

# Append variables to .env.local
Set-Content -Path ".env.local" -Value @"
CADDY_MERCURE_JWT_SECRET=$env:CADDY_MERCURE_JWT_SECRET
APP_SECRET=$env:APP_SECRET
DATABASE_PASSWORD=$env:DATABASE_PASSWORD
DATABASE_CREATOR_PASSWORD=$env:DATABASE_CREATOR_PASSWORD
JWT_PASSPHRASE=$env:JWT_PASSPHRASE
MY2_PASSWORD=$env:MY2_PASSWORD
"@

docker compose --env-file .env.local -f docker-compose.yml -f docker-compose.staging.yml -f docker-compose.adminer.yml up -d

# Show the database password to the user
Write-Host "Database password: $env:DATABASE_PASSWORD"
