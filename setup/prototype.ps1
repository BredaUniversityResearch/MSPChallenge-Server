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
    [ValidateSet("ProdDirect", "ProdProxy")]
    [string]$Preset = "ProdDirect",
    [string]$ServerName = "www.example.com",
    [string]$UrlWebServerHost = "www.example.com",
    [string]$UrlWebServerScheme = "https",
    [int]$UrlWsServerPort = 443,
    [string]$UrlWsServerHost = "www.example.com",
    [string]$UrlWsServerScheme = "wss",
    [string]$UrlWsServerUri = "/ws/",
    [int]$ServerPort = 443,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$TestSwitch = 0,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$EnableGui = 1,
    [string]$DatabasePassword = -join ((48..57) + (65..90) + (97..122) + (33..47) | Get-Random -Count 20 | % {[char]$_}),
    [string]$CaddyMercureJwtSecret = ([guid]::NewGuid().ToString("N"))
)

# Define metadata for parameters
$parameterMetadata = @{
    ServerName = @{
        ForceInput = $true
        EnvVar = "SERVER_NAME"
        Tab = "MSP Challenge Server"
        Presets = @{
            ProdDirect = "www.example.com"
            ProdProxy = ":80"
        }
    }
    UrlWebServerHost = @{
        EnvVar = "URL_WEB_SERVER_HOST"
        Tab = "MSP Challenge Server"
    }
    UrlWsServerHost = @{
        EnvVar = "URL_WS_SERVER_HOST"
        Tab = "MSP Challenge Server"
    }
    UrlWebServerScheme = @{
        EnvVar = "URL_WEB_SERVER_SCHEME"
        Tab = "MSP Challenge Server"
    }
    $UrlWsServerPort = @{
        EnvVar = "URL_WS_SERVER_PORT"
        Tab = "MSP Challenge Server"
    }
    UrlWsServerScheme = @{
        EnvVar = "URL_WS_SERVER_SCHEME"
        Tab = "MSP Challenge Server"
    }
    UrlWsServerUri = @{
        EnvVar = "URL_WS_SERVER_URI"
        Tab = "MSP Challenge Server"
    }
    ServerPort = @{
        EnvVar = "WEB_SERVER_PORT"
        Tab = "MSP Challenge Server"
    }
    TestSwitch = @{
        ActAsBoolean = $true
        Tab = "Other"
    }
    EnableGui = @{
        ActAsBoolean = $true
    }
    DatabasePassword = @{
        EnvVar = "DATABASE_PASSWORD"
        Tab = "Database"
        Validate = @(
            @{ "Password cannot be empty." = { param($v) -not [string]::IsNullOrWhiteSpace($v) } },
            @{ "Password must be at least 8 characters." = { param($v) $v.Length -ge 8 } }
        )
    }
    CaddyMercureJwtSecret = @{
        EnvVar = "CADDY_MERCURE_JWT_SECRET"
        Tab = "MSP Challenge Server"
        Validate = @(
            @{ "JWT Secret cannot be empty." = { param($v) -not [string]::IsNullOrWhiteSpace($v) } },
            @{ "JWT Secret must be 32 characters." = { param($v) $v.Length -eq 32 } }
        )
    }
}

# Function to update visible controls based on the selected environment
function Update-ControlsByPreset {
    param (
        [hashtable]$controls,
        [hashtable]$presetRadioButtons,
        [hashtable]$parameterMetadata
    )
    $selectedPreset = $presetRadioButtons.Keys | Where-Object { $presetRadioButtons[$_].Checked }
    foreach ($param in $controls.Keys) {
        $presetValues = $parameterMetadata[$param]["Presets"]
        $container = $controls[$param]
        # check if $presetValues is an array and contains the selected preset, if so assign the value
        if ($presetValues -and $presetValues.Count -gt 0) {
            if ($presetValues.ContainsKey($selectedPreset)) {
                $presetValue = $presetValues[$selectedPreset]
            } else {
                $presetValue = (Get-Variable -Name $param -ErrorAction SilentlyContinue).Value
            }
            if ($container.Controls[1] -is [System.Windows.Forms.TextBox]) {
                $container.Controls[1].Text = $presetValue
            } elseif ($container.Controls[1] -is [System.Windows.Forms.NumericUpDown]) {
                $container.Controls[1].Value = [int]$presetValue
            } elseif ($container.Controls[1] -is [System.Windows.Forms.CheckBox]) {
                $container.Controls[1].Checked = [bool]$presetValue
            }
        }
    }
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

function Initialize-Parameters {
    param (
        [hashtable]$parameters,
        [ref]$resolvedParameters,
        [hashtable]$parameterMetadata
    )
    foreach ($param in $parameters.Keys) {
        if (-not $resolvedParameters.Value[$param]) {
            $envVar = GetParamMetadataValue -param $param -metadata "EnvVar" -parameterMetadata $parameterMetadata
            if ($envVar) {
                $envValue = Get-ChildItem Env:$envVar -ErrorAction SilentlyContinue
                if ($envValue -and $envValue.Value -like "*ChangeThisMercureHubJWTSecretKey*") {
                    $resolvedParameters.Value[$param] = $null
                } elseif ($envValue) {
                    $resolvedParameters.Value[$param] = $envValue.Value
                } else {
                    $resolvedParameters.Value[$param] = $null
                }
            }
        }
        if (-not $resolvedParameters.Value[$param]) {
            $selectedPreset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
            $presetValues = GetParamMetadataValue -param $param -metadata "Presets" -parameterMetadata $parameterMetadata
            if ($presetValues -and $presetValues.Count -gt 0 -and $presetValues.ContainsKey($selectedPreset)) {
                 $resolvedParameters.Value[$param] = $presetValues[$selectedPreset]
            }
        }
        if (-not $resolvedParameters.Value[$param]) {
            $resolvedParameters.Value[$param] = (Get-Variable -Name $param -ErrorAction SilentlyContinue).Value
        }
    }
}

function Get-ParameterCategories {
    param (
        [hashtable]$parameters,
        [hashtable]$parameterMetadata
    )
    $categories = @{}
    foreach ($param in $parameters.Keys) {
        $tab = GetParamMetadataValue -param $param -metadata "Tab" -parameterMetadata $parameterMetadata
        if ($tab) {
            if (-not $categories.ContainsKey($tab)) { $categories[$tab] = @() }
            $categories[$tab] += $param
        }
    }
    return $categories
}

function New-ParameterControls {
    param (
        [hashtable]$categories,
        [hashtable]$parameters,
        [ref]$resolvedParameters,
        [hashtable]$parameterMetadata,
        [System.Windows.Forms.TabControl]$tabControl
    )
    $controls = @{}
    foreach ($category in $categories.Keys) {
        $tabPage = New-Object System.Windows.Forms.TabPage
        $tabPage.Text = $category
        $tabPage.Dock = [System.Windows.Forms.DockStyle]::Fill
        $tabControl.TabPages.Add($tabPage)

        $parentPanel = New-Object System.Windows.Forms.FlowLayoutPanel
        $parentPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
        $parentPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::TopDown
        $parentPanel.WrapContents = $false
        $parentPanel.AutoScroll = $true
        $tabPage.Controls.Add($parentPanel)

        $maxLabelWidth = 0
        foreach ($param in $categories[$category]) {
            $label = New-Object System.Windows.Forms.Label
            $label.Text = $param
            $textSize = [System.Windows.Forms.TextRenderer]::MeasureText($label.Text, $label.Font)
            if ($textSize.Width -gt $maxLabelWidth) { $maxLabelWidth = $textSize.Width }
        }

        foreach ($param in $categories[$category]) {
            $linePanel = New-Object System.Windows.Forms.FlowLayoutPanel
            $linePanel.FlowDirection = [System.Windows.Forms.FlowDirection]::LeftToRight
            $linePanel.WrapContents = $false
            $linePanel.Width = $parentPanel.ClientSize.Width - 20
            $linePanel.Height = 30
            $parentPanel.Controls.Add($linePanel)
            $controls[$param] = $linePanel

            $label = New-Object System.Windows.Forms.Label
            $label.Text = $param
            $label.Width = $maxLabelWidth + 10
            $label.Anchor = [System.Windows.Forms.AnchorStyles]::None
            $label.Margin = [System.Windows.Forms.Padding]::new(0, 5, 5, 5)
            $linePanel.Controls.Add($label)

            $isReadOnly = (GetParamMetadataValue -param $param -metadata "ReadOnly" -parameterMetadata $parameterMetadata)
            if (($resolvedParameters.Value[$param] -is [bool]) -or (GetParamMetadataValue -param $param -metadata "ActAsBoolean" -parameterMetadata $parameterMetadata)) {
                $checkbox = New-Object System.Windows.Forms.CheckBox
                $checkbox.Checked = $resolvedParameters.Value[$param]
                $checkbox.Enabled = !$isReadOnly
                $linePanel.Controls.Add($checkbox)
            } elseif ($resolvedParameters.Value[$param] -is [int]) {
                $numericUpDown = New-Object System.Windows.Forms.NumericUpDown
                $numericUpDown.Width = 200
                $numericUpDown.Minimum = 1
                $numericUpDown.Maximum = 65535
                $numericUpDown.Value = $resolvedParameters.Value[$param]
                $numericUpDown.Enabled = !$isReadOnly
                $linePanel.Controls.Add($numericUpDown)
            } else {
                $textbox = New-Object System.Windows.Forms.TextBox
                $textbox.Width = 200
                $textbox.Text = $resolvedParameters.Value[$param]
                $textbox.ReadOnly = $isReadOnly
                $linePanel.Controls.Add($textbox)
            }
        }
    }
    return $controls
}

function New-PresetRadioButtons {
    param (
        [hashtable]$parameters,
        [System.Windows.Forms.Form]$form,
        [hashtable]$parameterMetadata
    )
    $presetGroupBox = New-Object System.Windows.Forms.GroupBox
    $presetGroupBox.Text = "Preset Selection"
    $presetGroupBox.Dock = [System.Windows.Forms.DockStyle]::Top
    $presetGroupBox.Height = 60
    $form.Controls.Add($presetGroupBox)

    $presetRadioButtons = @{}
    $xOffset = 10

    $validateSetAttr = $parameters["Preset"].Attributes | Where-Object { $_ -is [System.Management.Automation.ValidateSetAttribute] }
    foreach ($p in $validateSetAttr.ValidValues) {
        $radioButton = New-Object System.Windows.Forms.RadioButton
        $radioButton.Text = $p
        $radioButton.Left = $xOffset
        $radioButton.Top = 20
        $radioButton.AutoSize = $true
        $presetGroupBox.Controls.Add($radioButton)
        $presetRadioButtons[$p] = $radioButton
        $xOffset += 100
    }
    $preset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
    $presetRadioButtons[$preset].Checked = $true

    return $presetRadioButtons
}

function Submit-SubmitClick {
    param (
        [hashtable]$controls,
        [ref]$resolvedParameters,
        [hashtable]$parameterMetadata,
        [System.Windows.Forms.ErrorProvider]$errorProvider,
        [System.Windows.Forms.Label]$errorLabel,
        [System.Windows.Forms.Form]$form
    )
    $hasError = $false
    $errorLabel.Text = ""
    $errorProvider.Clear()
    foreach ($param in $controls.Keys) {
        if ($controls[$param] -is [System.Windows.Forms.CheckBox]) {
            $resolvedParameters.Value[$param] = $controls[$param].Checked
            continue
        }
        if ($controls[$param] -is [System.Windows.Forms.NumericUpDown]) {
            $resolvedParameters.Value[$param] = $controls[$param].Value
            continue
        }
        $inputControl = $controls[$param].Controls | Where-Object { $_ -is [System.Windows.Forms.TextBox] }
        $value = $inputControl.Text
        $validateArr = GetParamMetadataValue -param $param -metadata "Validate" -parameterMetadata $parameterMetadata
        $errorMsg = ""
        if ($validateArr) {
            foreach ($validateObj in $validateArr) {
                foreach ($msg in $validateObj.Keys) {
                    $fn = $validateObj[$msg]
                    if (-not (& $fn $value)) {
                        $errorMsg = $msg
                        break
                    }
                }
                if ($errorMsg) { break }
            }
        }
        if ($errorMsg) {
            $errorProvider.SetError($inputControl, $errorMsg)
            $hasError = $true
        } else {
            $errorProvider.SetError($inputControl, "")
        }
        $resolvedParameters.Value[$param] = $value
    }
    if ($hasError) {
        $errorLabel.Text = "Please correct the highlighted errors."
        return
    }
    $form.Close()
}

function Show-GuiParameterForm {
    param (
        [hashtable]$parameters,
        [ref]$resolvedParameters,
        [hashtable]$parameterMetadata
    )

    Add-Type -AssemblyName System.Windows.Forms

    $form = New-Object System.Windows.Forms.Form
    $form.Text = "MSP Challenge Server setup"
    $form.Width = 1024
    $form.Height = 768

    $mainPanel = New-Object System.Windows.Forms.Panel
    $mainPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
    $form.Controls.Add($mainPanel)

    $tabControl = New-Object System.Windows.Forms.TabControl
    $tabControl.Dock = [System.Windows.Forms.DockStyle]::Fill
    $mainPanel.Controls.Add($tabControl)

    $categories = Get-ParameterCategories $parameters $parameterMetadata
    $controls = New-ParameterControls $categories $parameters ([ref]$resolvedParameters.Value) $parameterMetadata $tabControl

    $errorProvider = New-Object System.Windows.Forms.ErrorProvider
    $errorProvider.BlinkStyle = 'NeverBlink'
    $errorLabel = New-Object System.Windows.Forms.Label
    $errorLabel.Dock = [System.Windows.Forms.DockStyle]::Bottom
    $errorLabel.ForeColor = 'Red'
    $errorLabel.Height = 30
    $errorLabel.TextAlign = 'MiddleCenter'
    $mainPanel.Controls.Add($errorLabel)

    $button = New-Object System.Windows.Forms.Button
    $button.Text = "Submit"
    $button.Dock = [System.Windows.Forms.DockStyle]::Bottom
    $button.Height = 50

    $presetRadioButtons = New-PresetRadioButtons $parameters $form $parameterMetadata
    foreach ($radioButton in $presetRadioButtons.Values) {
       $radioButton.Add_CheckedChanged({ Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata })
    }
    Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata

    $button.Add_Click({
        Submit-SubmitClick $controls ([ref]$resolvedParameters.Value) $parameterMetadata $errorProvider $errorLabel $form
    })
    $mainPanel.Controls.Add($button)

    $form.ShowDialog()
}

# Make a copy of PSBoundParameters to avoid modifying the original
$resolvedParameters = @{}
foreach ($key in $PSBoundParameters.Keys) {
    $resolvedParameters[$key] = $PSBoundParameters[$key]
}

Initialize-Parameters $MyInvocation.MyCommand.Parameters ([ref]$resolvedParameters) $parameterMetadata

if ($EnableGui -and ($env:OS -eq "Windows_NT")) {
    Write-Host "Running in GUI mode..."
    Show-GuiParameterForm $MyInvocation.MyCommand.Parameters ([ref]$resolvedParameters) $parameterMetadata
} else {
    Write-Host "Running in non-GUI mode..."
    foreach ($param in $parameters.Keys) {
        if (-not (GetParamMetadataValue -param $param -metadata "Tab")) {
            continue
        }
        # Fallback to user input if no value is set
        $defaultValue = (Get-Variable -Name $param -EA SilentlyContinue).Value
        if (-not $resolvedParameters[$param] -or ($resolvedParameters[$param] -eq $defaultValue -and (GetParamMetadataValue -param $param -metadata "ForceInput"))) {
            $resolvedParameters[$param] = Read-InputWithDefault -prompt "Enter value for $param" -defaultValue $defaultValue
        }
    }
}

# Output resolved parameters
Write-Output "Resolved Parameters:"
$resolvedParameters
exit 0