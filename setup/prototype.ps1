<#
.SYNOPSIS
This script demonstrates parameter handling with GUI and CLI modes.

.PARAMETER ServerName
The domain name of the server.

.PARAMETER ServerPort
The port number for the server.

.PARAMETER TestSwitch
Enable a switch as a test (default: 0).

.PARAMETER EnableGui
Enable GUI mode for input (default: 0).
#>

# Define parameters
param(
    [string]$ServerName = "localhost",
    [int]$ServerPort = 443,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$TestSwitch = 0,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$EnableGui = 0,
    [string]$Var1 = "default1",
    [string]$Var2 = "default2",
    [string]$Var3 = "default3",
    [string]$Var4 = "default4",
    [string]$Var5 = "default5",
    [string]$Var6 = "default6",
    [string]$Var7 = "default7",
    [string]$Var8 = "default8",
    [string]$Var9 = "default9",
    [string]$Var10 = "default10",
    [string]$Var11 = "default11",
    [string]$Var12 = "default12",
    [string]$Var13 = "default13",
    [string]$Var14 = "default14",
    [string]$Var15 = "default15",
    [string]$Var16 = "default16",
    [string]$Var17 = "default17",
    [string]$Var18 = "default18",
    [string]$Var19 = "default19",
    [string]$Var20 = "default20",
    [string]$Var21 = "default21",
    [string]$Var22 = "default22",
    [string]$Var23 = "default23",
    [string]$Var24 = "default24",
    [string]$Var25 = "default25",
    [string]$Var26 = "default26",
    [string]$Var27 = "default27",
    [string]$Var28 = "default28",
    [string]$Var29 = "default29",
    [string]$Var30 = "default30",
    [string]$Var31 = "default31",
    [string]$Var32 = "default32",
    [string]$Var33 = "default33",
    [string]$Var34 = "default34",
    [string]$Var35 = "default35",
    [string]$Var36 = "default36",
    [string]$Var37 = "default37",
    [string]$Var38 = "default38",
    [string]$Var39 = "default39",
    [string]$Var40 = "default40"
)

# Define metadata for parameters
$parameterMetadata = @{
    ServerName = @{
        ForceInput = $true
        MSPChallengeServerParam = $true
        EnvVar = "SERVER_NAME"
    }
    ServerPort = @{
        MSPChallengeServerParam = $true
        EnvVar = "SERVER_PORT"
    }
    TestSwitch = @{
        MSPChallengeServerParam = $true
        ActAsBoolean = $true
    }
    EnableGui = @{
        ActAsBoolean = $true
    }
    Var1 = @{ MSPChallengeServerParam = $true }
    Var2 = @{ MSPChallengeServerParam = $true }
    Var3 = @{ MSPChallengeServerParam = $true }
    Var4 = @{ MSPChallengeServerParam = $true }
    Var5 = @{ MSPChallengeServerParam = $true }
    Var6 = @{ MSPChallengeServerParam = $true }
    Var7 = @{ MSPChallengeServerParam = $true }
    Var8 = @{ MSPChallengeServerParam = $true }
    Var9 = @{ MSPChallengeServerParam = $true }
    Var10 = @{ MSPChallengeServerParam = $true }
    Var11 = @{ MSPChallengeServerParam = $true }
    Var12 = @{ MSPChallengeServerParam = $true }
    Var13 = @{ MSPChallengeServerParam = $true }
    Var14 = @{ MSPChallengeServerParam = $true }
    Var15 = @{ MSPChallengeServerParam = $true }
    Var16 = @{ MSPChallengeServerParam = $true }
    Var17 = @{ MSPChallengeServerParam = $true }
    Var18 = @{ MSPChallengeServerParam = $true }
    Var19 = @{ MSPChallengeServerParam = $true }
    Var20 = @{ MSPChallengeServerParam = $true }
    Var21 = @{ MSPChallengeServerParam = $true }
    Var22 = @{ MSPChallengeServerParam = $true }
    Var23 = @{ MSPChallengeServerParam = $true }
    Var24 = @{ MSPChallengeServerParam = $true }
    Var25 = @{ MSPChallengeServerParam = $true }
    Var26 = @{ MSPChallengeServerParam = $true }
    Var27 = @{ MSPChallengeServerParam = $true }
    Var28 = @{ MSPChallengeServerParam = $true }
    Var29 = @{ MSPChallengeServerParam = $true }
    Var30 = @{ MSPChallengeServerParam = $true }
    Var31 = @{ MSPChallengeServerParam = $true }
    Var32 = @{ MSPChallengeServerParam = $true }
    Var33 = @{ MSPChallengeServerParam = $true }
    Var34 = @{ MSPChallengeServerParam = $true }
    Var35 = @{ MSPChallengeServerParam = $true }
    Var36 = @{ MSPChallengeServerParam = $true }
    Var37 = @{ MSPChallengeServerParam = $true }
    Var38 = @{ MSPChallengeServerParam = $true }
    Var39 = @{ MSPChallengeServerParam = $true }
    Var40 = @{ MSPChallengeServerParam = $true }
}

function GetParamMetadataValue {
    param (
        [string]$param,
        [string]$metadata
    )
    if (-not $parameterMetadata[$param] -contains $metadata) {
        return $null
    }
    return $parameterMetadata[$param][$metadata]
}

function Read-InputWithDefault {
    param (
        [string]$prompt,
        [string]$defaultValue
    )
    if ($defaultValue -eq "") {
        $inputValue = Read-Prompt "${prompt}:"
    } else {
        $inputValue = Read-Prompt "$prompt ($defaultValue): "
    }
    if ($inputValue -eq "") {
        return $defaultValue
    }
    return $inputValue
}

function Read-Prompt
{
    param (
        [string]$message
    )
    Write-Host $message -ForegroundColor Blue -nonewline
    return Read-Host
}

$parameters = $MyInvocation.MyCommand.Parameters
foreach ($param in $parameters.Keys) {
    if (-not $PSBoundParameters[$param]) {
        $envVar = GetParamMetadataValue -param $param -metadata "EnvVar"
        if ($envVar) {
            $PSBoundParameters[$param] = (Get-ChildItem Env:$envVar -ErrorAction SilentlyContinue)?.Value
        }
    }
    if (-not $PSBoundParameters[$param]) {
        $PSBoundParameters[$param] = (Get-Variable -Name $param -ErrorAction SilentlyContinue).Value
    }
}

# GUI Mode
if ($EnableGui -and ($env:OS -eq "Windows_NT")) {
    Write-Host "Running in GUI mode..."
    Add-Type -AssemblyName System.Windows.Forms

    $form = New-Object System.Windows.Forms.Form
    $form.Text = "Input Parameters"
    $form.Width = 1024
    $form.Height = 768

    # Create a panel with AutoScroll enabled
    $panel = New-Object System.Windows.Forms.Panel
    $panel.Dock = [System.Windows.Forms.DockStyle]::Fill
    $panel.AutoScroll = $true
    $form.Controls.Add($panel)

    $controls = @{}
    $yOffset = 20

    foreach ($param in $parameters.Keys) {
        if (-not (GetParamMetadataValue -param $param -metadata "MSPChallengeServerParam")) {
            continue
        }

        $label = New-Object System.Windows.Forms.Label
        $label.Text = $param
        $label.Top = $yOffset
        $label.Left = 10
        $panel.Controls.Add($label)

        if (($PSBoundParameters[$param] -is [bool]) -or (GetParamMetadataValue -param $param -metadata "ActAsBoolean")) {
            $checkbox = New-Object System.Windows.Forms.CheckBox
            $checkbox.Top = $yOffset
            $checkbox.Left = 150
            $checkbox.Checked = $PSBoundParameters[$param]
            $panel.Controls.Add($checkbox)
            $controls[$param] = $checkbox
        } elseif ($PSBoundParameters[$param] -is [int]) {
            $numericUpDown = New-Object System.Windows.Forms.NumericUpDown
            $numericUpDown.Top = $yOffset
            $numericUpDown.Left = 150
            $numericUpDown.Width = 200
            $numericUpDown.Minimum = 1
            $numericUpDown.Maximum = 65535
            $numericUpDown.Value = $PSBoundParameters[$param]
            $panel.Controls.Add($numericUpDown)
            $controls[$param] = $numericUpDown
        } else {
            $textbox = New-Object System.Windows.Forms.TextBox
            $textbox.Top = $yOffset
            $textbox.Left = 150
            $textbox.Width = 200
            $textbox.Text = $PSBoundParameters[$param]
            $panel.Controls.Add($textbox)
            $controls[$param] = $textbox
        }

        $yOffset += 30
    }

    $button = New-Object System.Windows.Forms.Button
    $button.Text = "Submit"
    $button.Top = $yOffset
    $button.Left = 150
    $button.Add_Click({
        foreach ($param in $controls.Keys) {
            if ($controls[$param] -is [System.Windows.Forms.CheckBox]) {
                $PSBoundParameters[$param] = $controls[$param].Checked
            } else {
                $PSBoundParameters[$param] = $controls[$param].Text
            }
        }
        $form.Close()
    })
    $panel.Controls.Add($button)

    $form.ShowDialog()

    # Output resolved parameters
    Write-Output "Resolved Parameters:"
    $PSBoundParameters
    exit 0
}

Write-Host "Running in non-GUI mode..."

# non-GUI Mode
foreach ($param in $parameters.Keys) {
    if (-not (GetParamMetadataValue -param $param -metadata "MSPChallengeServerParam")) {
        continue
    }
    # Fallback to user input if no value is set
    $defaultValue = (Get-Variable -Name $param -EA SilentlyContinue).Value
    if (-not $PSBoundParameters[$param] -or ($PSBoundParameters[$param] -eq $defaultValue -and (GetParamMetadataValue -param $param -metadata "ForceInput"))) {
        $PSBoundParameters[$param] = Read-InputWithDefault -prompt "Enter value for $param" -defaultValue $defaultValue
    }
}

# Output resolved parameters
Write-Output "Resolved Parameters:"
$PSBoundParameters
