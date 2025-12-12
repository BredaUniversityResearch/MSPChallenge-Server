param([switch]$elevated, [string]$tag = "latest", [string]$branch_name = "dev")

function Test-Admin {
    $currentUser = New-Object Security.Principal.WindowsPrincipal $([Security.Principal.WindowsIdentity]::GetCurrent())
    $currentUser.IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)
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

function Select-WifiAdapter {
    $wifiAdapter = $null
    while ($null -eq $wifiAdapter) {
        $wifiAdapters = @(Get-NetIPAddress | Where-Object { $_.InterfaceAlias -like "*Wi-Fi*" -and $_.AddressFamily -eq "IPv4" })
        if ($wifiAdapters.Count -eq 1) {
            $wifiAdapter = $wifiAdapters[0]
        } elseif ($wifiAdapters.Count -gt 1) {
            Write-Host "Multiple Wi-Fi networks detected. Please select one:"
            for ($i = 0; $i -lt $wifiAdapters.Count; $i++) {
                Write-Host "$($i+1): $($wifiAdapters[$i].InterfaceAlias) - $($wifiAdapters[$i].IPAddress)"
            }
            $selection = Read-Host "Enter the number of the Wi-Fi network to use"
            if ($selection -match '^\d+$' -and $selection -ge 1 -and $selection -le $wifiAdapters.Count) {
                $wifiAdapter = $wifiAdapters[$selection - 1]
            } else {
                Write-Host "Invalid selection. Please try again."
            }
        } else {
            Write-Host "No Wi-Fi network detected. Please connect to a Wi-Fi network and press enter to continue."
            Read-Host
        }
    }
    return $wifiAdapter
}

# Check for administrative privileges
if ((Test-Admin) -eq $false)  {
    if ($elevated) {
        Write-Host "Failed to elevate privileges. Exiting..."
    } else {
        Write-Host "This script requires administrative privileges. Relaunching as admin..."
        Start-Process powershell.exe -Verb RunAs -ArgumentList ('-noprofile -noexit -file "{0}" -elevated -tag {1} -branch_name {2}' -f ($myinvocation.MyCommand.Definition, $tag, $branch_name))
    }
    exit
}

Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope Process -Force

# Change to the script's directory
Set-Location -Path (Split-Path -Parent $MyInvocation.MyCommand.Definition)

Write-Host "Starting augGIS setup script with arguments:"
Write-Host "  elevated: $elevated"
Write-Host "  tag: $tag"
Write-Host "  branch_name: $branch_name"

Read-Host "Please switch and connect to the Wi-Fi network to be used for your 'augGIS' session and then press enter to continue"
$wifiAdapter = Select-WifiAdapter
$wifiIpEscaped = $wifiAdapter.IPAddress -replace '\.', '\.'
Write-Host "Gonna use $($wifiAdapter.IPAddress) for the MSP server connections"

$ip = $wifiAdapter.IPAddress
$hostsPath = 'C:\Windows\System32\drivers\etc\hosts'
if (Test-Path $hostsPath) {
    Write-Host "Found C:\Windows\System32\drivers\etc\hosts, reading:"
    try {
        $newHostsContent = (Get-Content $hostsPath) | ForEach-Object {
            if ($_ -match '^\d{1,3}(\.\d{1,3}){3}\s+host\.docker\.internal$') {
                $hostDockerFound = $true
                "$ip host.docker.internal"
            } elseif ($_ -match '^\d{1,3}(\.\d{1,3}){3}\s+gateway\.docker\.internal$') {
                "$ip gateway.docker.internal"
            } else {
                $_
            }
        }
        if (-not $hostDockerFound) {
            Write-Error "Error: host.docker.internal entry not found in hosts file."
            Write-Warning "Make sure to enable 'Add the *.docker.internal names to the host's /etc/hosts file' in Docker Desktop General settings"
            exit 1
        }
        Write-Host "Found host.docker.internal, updating hosts file with new IP $ip"
        Set-Content $hostsPath -Force -Value $newHostsContent
    } catch {
        Write-Error "Error processing hosts file: $($_.Exception.Message)"
        Write-Warning "Make sure C:\Windows\System32\drivers\etc\hosts is not in use by another program"
        Write-Host "Hosts file was not changed."
        exit 1
    }
}

Write-Host "Gonna use $($wifiAdapter.IPAddress) for the MSP server connections"
Read-Host "If needed, switch to a network that has an internet connection and then press enter to continue"

# pre-cache the auggis server image
docker pull docker-hub.mspchallenge.info/cradlewebmaster/auggis-unity-server:latest
docker run --name docker-api -d -p 2375:2375 -v /var/run/docker.sock:/var/run/docker.sock docker-hub.mspchallenge.info/cradlewebmaster/docker-api:latest
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$branch_name/docker-compose.auggis.yml" -OutFile "docker-compose.auggis.yml"

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
IMMERSIVE_SESSIONS_DOCKER_HUB_TAG=$tag
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1|$wifiIpEscaped)(:[0-9]+)?$'
CADDY_MERCURE_JWT_SECRET=$([guid]::NewGuid().ToString("N"))
APP_SECRET=$([guid]::NewGuid().ToString("N"))
DATABASE_PASSWORD=$([guid]::NewGuid().ToString("N"))
MY2_PASSWORD=$([guid]::NewGuid().ToString("N"))
JWT_PASSPHRASE=$([guid]::NewGuid().ToString("N"))
DATABASE_CREATOR_PASSWORD=$([guid]::NewGuid().ToString("N"))
"@

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.auggis.yml" up -d
exit 0
