param([switch]$elevated, [string]$tag = "dev")

function Test-Admin {
    $currentUser = New-Object Security.Principal.WindowsPrincipal $([Security.Principal.WindowsIdentity]::GetCurrent())
    $currentUser.IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
}

# always run another instance as admin, also to loose env vars from parent process
if (-not $elevated) {
    Write-Host "This script requires administrative privileges. Relaunching as admin..."
    Start-Process powershell.exe -Verb RunAs -ArgumentList ('-noprofile -noexit -file "{0}" -elevated -tag {1}' -f ($myinvocation.MyCommand.Definition, $tag))
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

# check if docker-compose.override.yml is in the current working directory, if not try to find it in the parent directory
if (-not (Test-Path "docker-compose.override.yml")) {
    if (Test-Path "..\docker-compose.override.yml") {
        Set-Location ..
    } else {
        Write-Host "Could not find docker-compose.override.yml in the current or parent directory. Exiting..."
        exit 1
    }
}

if (Test-Path ".env.local") {
    # output content of .env.local
    Write-Host ".env.local already exists with the following content:"
    Get-Content ".env.local"
    # Ask user confirmation to overwrite existing .env.local
    $confirmation = Read-Host "The .env.local file already exists. Do you want to overwrite it? (y/n)"
    # If no, exit the script
    if ($confirmation -ne 'y') {
        Write-Host "Exiting without changes."
        exit 0
    }
    # make a backup of the existing .env.local with a timestamp
    $timestamp = Get-Date -Format "yyyyMMddHHmmss"
    $backupFile = ".env.local.$timestamp.bak"
    Copy-Item -Path ".env.local" -Destination $backupFile
    Write-Host "Backup of .env.local created as $backupFile"
}

Read-Host "Please switch and connect to the Wi-Fi network to be used for your 'augGIS' session and then press enter to continue"

# Get the IP address of the Wi-Fi network interface
$netAdapter = Get-NetIPAddress -AddressFamily IPv4 -InterfaceAlias Wi-Fi,Ethernet -AddressState Preferred | Select-Object -First 1
$netAdapter.IPAddress
$wifiIpEscaped = $netAdapter.IPAddress -replace '\.', '\.'
Write-Host "Gonna use $($netAdapter.IPAddress) for the MSP server connections"

# pre-cache the auggis server image
docker pull docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server:$tag
docker run --name docker-api -d -p 2375:2375 -v /var/run/docker.sock:/var/run/docker.sock docker-hub.mspchallenge.info/cradlewebmaster/docker-api:latest

# Write variables to .env.local
Set-Content -Path ".env.local" -Value @"
URL_WEB_SERVER_HOST=$($netAdapter.IPAddress)
URL_WS_SERVER_HOST=$($netAdapter.IPAddress)
IMMERSIVE_SESSIONS_DOCKER_HUB_TAG=$tag
IMMERSIVE_SESSIONS_HEALTHCHECK_WRITE_MODE=1
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|$wifiIpEscaped)(:[0-9]+)?$'
"@

$hostsPath = 'C:\Windows\System32\drivers\etc\hosts'
if (Test-Path $hostsPath) {
    Write-Host "Found C:\Windows\System32\drivers\etc\hosts, editing:"
    $ip = $netAdapter.IPAddress
    (Get-Content $hostsPath) |
        ForEach-Object {
            if ($_ -match '^\d{1,3}(\.\d{1,3}){3}\s+host\.docker\.internal$') {
                "$ip host.docker.internal"
                Write-Host "* Updated host.docker.internal to $ip"
            } elseif ($_ -match '^\d{1,3}(\.\d{1,3}){3}\s+gateway\.docker\.internal$') {
                "$ip gateway.docker.internal"
                Write-Host "* Updated gateway.docker.internal to $ip"
            } else {
                $_
            }
        } | Set-Content $hostsPath -Force
}

docker compose --env-file .env.local -f docker-compose.yml -f docker-compose.override.yml -f docker-compose.adminer.yml up -d --remove-orphans
exit 0
