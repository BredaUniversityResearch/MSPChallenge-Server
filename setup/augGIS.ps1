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
    $secret = -join ((65..90) + (97..122) + (48..57) | Get-Random -Count 32 | ForEach-Object {[char]$_})
    $env:CADDY_MERCURE_JWT_SECRET = $secret
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
$envVars["SERVER_NAME"] = $env:SERVER_NAME ?? ":80"
$envVars["URL_WEB_SERVER_HOST"] = $env::URL_WEB_SERVER_HOST ?? $wifiAdapter.IPAddress
$envVars["URL_WS_SERVER_HOST"] = $env::URL_WS_SERVER_HOST ?? $wifiAdapter.IPAddress
$envVars["CADDY_MERCURE_JWT_SECRET"] = $env:CADDY_MERCURE_JWT_SECRET
$envVars["GEO_SERVER_DOWNLOADS_CACHE_LIFETIME"] = $env::GEO_SERVER_DOWNLOADS_CACHE_LIFETIME ?? "1209600"
$envVars["GEO_SERVER_RESULTS_CACHE_LIFETIME"] = $env::GEO_SERVER_RESULTS_CACHE_LIFETIME ?? "1209600"
$envVars["IMMERSIVE_TWINS_DOCKER_HUB_TAG"] = $env::IMMERSIVE_TWINS_DOCKER_HUB_TAG ?? $tag

# Write updated variables to .env.local
$envVars.GetEnumerator() | ForEach-Object {
    "$($_.Key)=$($_.Value)"
} | Set-Content -Path ".env.local"

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
exit 0