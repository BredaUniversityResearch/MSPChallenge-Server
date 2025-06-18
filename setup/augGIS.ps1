param([switch]$elevated, [string]$tag = "latest")

function Test-Admin {
    $currentUser = New-Object Security.Principal.WindowsPrincipal $([Security.Principal.WindowsIdentity]::GetCurrent())
    $currentUser.IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
}

# Check for administrative privileges
if ((Test-Admin) -eq $false)  {
    if ($elevated) {
        Write-Host "Failed to elevate privileges. Exiting..."
    } else {
        Write-Host "This script requires administrative privileges. Relaunching as admin..."
        Start-Process powershell.exe -Verb RunAs -ArgumentList ('-noprofile -noexit -file "{0}" -elevated -tag {1}' -f ($myinvocation.MyCommand.Definition, $tag))
    }
    exit
}

Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process -Force

# Change to the script's directory
Set-Location -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition)

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
        if ($_ -match "^(.*?)=(.*)$") {
            if ($matches.Count -ge 3) {
                [Environment]::SetEnvironmentVariable($matches[1], $matches[2])
            }
        }
    }
}

$deviceName = (Get-CimInstance -ClassName Win32_ComputerSystem).Name
Write-Host "Gonna use $deviceName for the MSP server connections"

docker run --name docker-api -d -p 2375:2375 -v /var/run/docker.sock:/var/run/docker.sock docker-hub.mspchallenge.info/cradlewebmaster/docker-api:latest
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.prod.yml" -OutFile "docker-compose.prod.yml"

if (-not $env:CADDY_MERCURE_JWT_SECRET) {
    $secret = -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $env:CADDY_MERCURE_JWT_SECRET = $secret
}

# Append variables to .env.local
Set-Content -Path ".env.local" -Value @"
SERVER_NAME=:80
URL_WEB_SERVER_HOST=$deviceName
URL_WS_SERVER_HOST=$deviceName
CADDY_MERCURE_JWT_SECRET=$env:CADDY_MERCURE_JWT_SECRET
GEO_SERVER_DOWNLOADS_CACHE_LIFETIME=1209600
GEO_SERVER_RESULTS_CACHE_LIFETIME=1209600
IMMERSIVE_TWINS_DOCKER_BRANCH=$tag
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
exit 0