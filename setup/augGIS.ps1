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

function IfEmpty {
    param (
        $Value,
        [Parameter(Mandatory=$true)]
        $DefaultValue
    )
    if ($null -eq $Value -or $Value -eq "") {
        return $DefaultValue
    }
    return $Value
}

Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process -Force

# Change to the script's directory
Set-Location -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition)

$branch_name = "msp-ar"
if ($args.Count -gt 0) {
    $branch_name = $args[0]
}

Read-Host "Please switch and connect to the Wi-Fi network to be used for your 'augGIS' session and then press enter to continue"
# Get the IP address of the Wi-Fi network interface
$wifiAdapter = Get-NetIPAddress | Where-Object { $_.InterfaceAlias -like "*Wi-Fi*" -and $_.AddressFamily -eq "IPv4" }
$wifiAdapter.IPAddress
Write-Host "Gonna use $($wifiAdapter.IPAddress) for the MSP server connections"
Read-Host "If needed, switch to a network that has an internet connection and then press enter to continue"

docker run --name docker-api -d -p 2375:2375 -v /var/run/docker.sock:/var/run/docker.sock docker-hub.mspchallenge.info/cradlewebmaster/docker-api:latest
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.prod.yml" -OutFile "docker-compose.prod.yml"

if (-not $env:CADDY_MERCURE_JWT_SECRET) {
    $caddyMercureJwtSecret = -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $env:CADDY_MERCURE_JWT_SECRET = $caddyMercureJwtSecret
}

# Read existing .env.local variables
$envVars = @{}
if (Test-Path ".env.local") {
    Get-Content ".env.local" | ForEach-Object {
        if ($_ -match "^(.*?)=(.*)$") {
            $envVars[$matches[1]] = $matches[2]
        }
    }
}

# Override with environment variables if they exist
$geoServerDownloadsCacheLifetime = IfEmpty $env:GEO_SERVER_DOWNLOADS_CACHE_LIFETIME (IfEmpty $envVars['GEO_SERVER_DOWNLOADS_CACHE_LIFETIME'] "1209600")
$geoServerResultsCacheLifetime = IfEmpty $env:GEO_SERVER_RESULTS_CACHE_LIFETIME (IfEmpty $envVars['GEO_SERVER_RESULTS_CACHE_LIFETIME'] "1209600")

# Write variables to .env.local
Set-Content -Path ".env.local" -Value @"
SERVER_NAME=:80
URL_WEB_SERVER_HOST=$($wifiAdapter.IPAddress)
URL_WS_SERVER_HOST=$($wifiAdapter.IPAddress)
CADDY_MERCURE_JWT_SECRET=$env:CADDY_MERCURE_JWT_SECRET
GEO_SERVER_DOWNLOADS_CACHE_LIFETIME=$geoServerDownloadsCacheLifetime
GEO_SERVER_RESULTS_CACHE_LIFETIME=$geoServerResultsCacheLifetime
IMMERSIVE_TWINS_DOCKER_HUB_TAG=$tag
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
exit 0