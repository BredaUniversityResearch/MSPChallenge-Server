#!/bin/sh

# Note: This script is intended to be run on a Linux system with PowerShell installed.
# It installs PowerShell if not already installed, and then runs the specified PowerShell script with any additional arguments passed to this script.
# Make sure to run this script with sudo privileges, usage:
#  sudo ./run-ps1.sh <path_to_powershell_script> [additional_arguments]

# Source the /etc/os-release file
. /etc/os-release

# Detect if the script is running on a supported Linux distribution
case "$ID" in
    ubuntu|debian)
        echo "The system is running $ID."
        ;;
    *)
        echo "Unsupported Linux distribution: $ID"
        exit 1
        ;;
esac

# check if pwsh is installed
if ! command -v pwsh >/dev/null 2>&1; then
    echo "PowerShell is not installed. Installing PowerShell..."
    sudo apt-get update # Update the list of packages

    # Install pre-requisite packages.
    sudo apt-get install -y wget
    if [ "$ID" = "ubuntu" ]; then
        sudo apt-get install -y apt-transport-https software-properties-common
    fi

    wget -q https://packages.microsoft.com/config/"$ID"/"$VERSION_ID"/packages-microsoft-prod.deb # Download the Microsoft repository keys
    sudo dpkg -i packages-microsoft-prod.deb # Register the Microsoft repository keys
    rm packages-microsoft-prod.deb # Delete the Microsoft repository keys file
    sudo apt-get update # Update the list of packages
    sudo apt-get install -y powershell # Install PowerShell
fi

# Run powerShell script given by command line arguments
if [ $# -eq 0 ]; then
    echo "No arguments provided. Please provide the path to the PowerShell script."
    exit 1
fi
script_path=$1
if [ ! -f "$script_path" ]; then
    echo "The specified PowerShell script does not exist: $script_path"
    exit 1
fi
pwsh -f "$script_path" "$(shift 1; echo "$@")"
if ! pwsh -f "$script_path" "$(shift 1; echo "$@")"; then
    echo "PowerShell script execution failed."
    exit 1
fi
echo "PowerShell script executed successfully."
# Exit the script successfully
exit 0
# End of script
