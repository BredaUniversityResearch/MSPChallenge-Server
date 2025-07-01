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
    [ValidateSet("Direct", "Proxy", "LAN", "Local")]
    [string]$Preset = "Direct",
    [string]$ServerName = "www.example.com",
    [string]$UrlWebServerHost = "www.example.com",
    [string]$UrlWebServerScheme = "https",
    [int]$UrlWsServerPort = 443,
    [string]$UrlWsServerHost = "www.example.com",
    [string]$UrlWsServerScheme = "wss",
    [string]$UrlWsServerUri = "/ws/",
    [int]$ServerPort = 443,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
#    [int]$TestSwitch = 0,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$EnableGui = 1,
    [string]$DatabasePassword = -join ((48..57) + (65..90) + (97..122) + (33..47) | Get-Random -Count 20 | % {[char]$_}),
    [string]$CaddyMercureJwtSecret = ([guid]::NewGuid().ToString("N"))
)

$presetsConfiguration = @{
    Direct = @{
        Text = "Directly exposed"
        Desr = "The docker container running the MSP Challenge server is directly exposed to the internet on port 443 given the server name, e.g. www.example.com. Caddy will automatically obtain a TLS certificate for the server name."
    }
    Proxy  = @{
        Text = "Behind a proxy"
        Desr = "The docker container running the MSP Challenge server is behind a reverse proxy, e.g. Nginx, etc. The proxy is responsible for handling the server name requests on https, port 443, e.g. www.example.com, as well as the TLS certificate, and forwarding those to the MSP Challenge server running on http (port 80). Example nginx proxy setup: https://community.mspchallenge.info/wiki/Docker_server_installation#Nginx_proxy_example"
    }
    LAN = @{
        Text = "Network (LAN) setup"
        Desr = "The docker container running the MSP Challenge server is exposed to the local area network, by the machine's IP address."
    }
    Local = @{
        Text = "Local setup"
        Desr = "The docker container running MSP Challenge server and locally available on the host machine, e.g. host.docker.internal, or localhost."
    }
}

function Get-FirstLanIPv4Address {
    Get-NetIPInterface -AddressFamily IPv4 |
        Where-Object { $_.InterfaceAlias -match '^(Ethernet|Wi-Fi)' -and $_.ConnectionState -eq 'Connected' } |
        Sort-Object -Property InterfaceMetric |
        ForEach-Object {
            Get-NetIPAddress -InterfaceIndex $_.InterfaceIndex -AddressFamily IPv4 |
            Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' }
        } |
        Select-Object -First 1 -ExpandProperty IPAddress
}

# Define metadata for parameters
$parameterMetadata = @{
    Preset = @{
        ForceInput = $true # in non-gui mode, always prompt for input
    }
    ServerName = @{
        ForceInput = $true # in non-gui mode, always prompt for input
        EnvVar = "SERVER_NAME"
        Tab = "Basic setup"
        Presets = @{
            Direct = "www.example.com"
            Proxy = ":80"
            LAN = ":80"
            Local = ":80"
        }
        ReadOnly = @{
            Direct = $false
            Proxy = $true
            LAN = $true
            Local = $true
        }
        Link = @{
            Direct = @("UrlWebServerHost", "UrlWsServerHost")            
        }
    }
    UrlWebServerHost = @{
        EnvVar = "URL_WEB_SERVER_HOST"
        Tab = "Basic setup"
        Presets = @{
            Local = "host.docker.internal"
            LAN = (Get-FirstLanIPv4Address)
        }
        Link = @{
            Direct = @("ServerName", "UrlWsServerHost")
            Proxy = @("UrlWsServerHost")
            LAM = @("UrlWsServerHost")
            Local = @("UrlWsServerHost")
        }        
    }
    UrlWsServerHost = @{
        EnvVar = "URL_WS_SERVER_HOST"
        Tab = "Basic setup"
        Presets = @{
            Local = "host.docker.internal"
            LAN = (Get-FirstLanIPv4Address)
        }
        Link = @{
            Direct = @("UrlWebServerHost", "ServerName")
            Proxy = @("UrlWebServerHost")
            LAN = @("UrlWebServerHost")
            Local = @("UrlWebServerHost")
        }        
    }
    UrlWebServerScheme = @{
        EnvVar = "URL_WEB_SERVER_SCHEME"
        Tab = "Advanced settings"
        Presets = @{
            Local = "http"
            LAN = "http"
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $true
            LAN = $true
            Local = $true
        }        
    }
    UrlWsServerPort = @{
        EnvVar = "URL_WS_SERVER_PORT"
        Tab = "Advanced settings"
        Presets = @{
            Local = 45001
            LAN = 45001            
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $true
            LAN = $false
            Local = $false
        }          
    }
    UrlWsServerScheme = @{
        EnvVar = "URL_WS_SERVER_SCHEME"
        Tab = "Advanced settings"
        Presets = @{
            Local = "ws"
            LAN = "ws"           
        }        
        ReadOnly = @{
            Direct = $true
            Proxy = $true
            LAN = $true
            Local = $true
        }  
    }
    UrlWsServerUri = @{
        EnvVar = "URL_WS_SERVER_URI"
        Tab = "Advanced settings"
        Presets = @{
            Local = ""
            LAN = ""
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $true
            LAN = $false
            Local = $false
        }        
    }
    ServerPort = @{
        EnvVar = "WEB_SERVER_PORT"
        Tab = "Advanced settings"
        Presets = @{
            Local = 80
            LAN = 80
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $true
            LAN = $false
            Local = $false
        }        
    }
#    TestSwitch = @{
#        ActAsBoolean = $true
#        Tab = "Advanced settings"
#    }
    EnableGui = @{
        ActAsBoolean = $true
        ScriptArgument = $true
    }
    DatabasePassword = @{
        EnvVar = "DATABASE_PASSWORD"
        Tab = "Basic setup"
        Validate = @(
            @{ "Password cannot be empty." = { param($v) -not [string]::IsNullOrWhiteSpace($v) } },
            @{ "Password must be at least 8 characters." = { param($v) $v.Length -ge 8 } }
        )
    }
    CaddyMercureJwtSecret = @{
        EnvVar = "CADDY_MERCURE_JWT_SECRET"
        Tab = "Basic setup"
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
        $presetValue = (Get-Variable -Name $param -ErrorAction SilentlyContinue).Value
        $readOnly = (GetParamMetadataValue -param $param -metadata "ReadOnly" -parameterMetadata $parameterMetadata)
        $isReadOnly = $readOnly -and $readOnly.ContainsKey($selectedPreset) -and $readOnly[$selectedPreset]

        # check if $presetValues is an array and contains the selected preset, if so assign the value
        if ($presetValues -and $presetValues.Count -gt 0 -and $presetValues.ContainsKey($selectedPreset)) {
            $presetValue = $presetValues[$selectedPreset]
        }
        if ($container.Controls[1] -is [System.Windows.Forms.TextBox]) {
            $container.Controls[1].Text = $presetValue
            $container.Controls[1].ReadOnly = $isReadOnly
        } elseif ($container.Controls[1] -is [System.Windows.Forms.NumericUpDown]) {            
            $container.Controls[1].Value = [int]$presetValue
            $container.Controls[1].Enabled = -not $isReadOnly
        } elseif ($container.Controls[1] -is [System.Windows.Forms.CheckBox]) {
            $container.Controls[1].Checked = [bool]$presetValue
            $container.Controls[1].Enabled = -not $isReadOnly
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
        [string]$defaultValue,
        [System.Management.Automation.ParameterMetadata]$paramDef,
        [hashtable]$parameterMetadata
    )
    while ($true) {
        if ($defaultValue -eq "") {
            $inputValue = Read-Prompt "${prompt}:"
        } else {
            $inputValue = Read-Prompt "$prompt ($defaultValue): "
        }

        if ($inputValue -eq "") {
            $inputValue = $defaultValue
        }

        if ($inputValue -eq "") {
            continue
        }

        # ValidateSet/ValidateRange from parameter definition
        foreach ($attr in $paramDef.Attributes) {
            if ($attr -is [System.Management.Automation.ValidateSetAttribute]) {
                if ($attr.ValidValues -notcontains $inputValue) {
                    Write-Host "Invalid value. Allowed values: $($attr.ValidValues -join ', ')" -ForegroundColor Red
                    $retry = $true
                    break
                }
            }
            if ($attr -is [System.Management.Automation.ValidateRangeAttribute]) {
                $num = $inputValue -as [int]
                if ($num -lt $attr.MinRange -or $num -gt $attr.MaxRange) {
                    Write-Host "Value must be between $($attr.MinRange) and $($attr.MaxRange)." -ForegroundColor Red
                    $retry = $true
                    break
                }
            }
        }
        if ($retry) { continue }

        # Custom validation from parameterMetadata
        if ($parameterMetadata.ContainsKey("Validate")) {
            $validateArr = $parameterMetadata["Validate"]
            foreach ($validateObj in $validateArr) {
                foreach ($msg in $validateObj.Keys) {
                    $fn = $validateObj[$msg]
                    if (-not (& $fn $inputValue)) {
                        Write-Host $msg -ForegroundColor Red
                        $retry = $true
                        break 2
                    }
                }
            }
        }
        if ($retry) { continue }

        return $inputValue
    }
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
    $categories = [ordered]@{}
    foreach ($param in $parameters.Keys) {
        $tab = GetParamMetadataValue -param $param -metadata "Tab" -parameterMetadata $parameterMetadata
        if ($tab) {
            if (-not ($categories.Keys -contains $tab)) { $categories[$tab] = @() }
            $categories[$tab] += $param
        }
    }
    return $categories
}

function New-ParameterControls {
    param (
        [System.Collections.Specialized.OrderedDictionary]$categories,
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

        $labels = @{}
        $maxLabelWidth = 0
        foreach ($param in $categories[$category]) {
            $label = New-Object System.Windows.Forms.Label
            $labelText = (GetParamMetadataValue -param $param -metadata "Text" -parameterMetadata $parameterMetadata)
            if (-not $labelText) {
                $labelText = (GetParamMetadataValue -param $param -metadata "EnvVar" -parameterMetadata $parameterMetadata)
            }            
            $label.Text = $labelText
            $label.Anchor = [System.Windows.Forms.AnchorStyles]::None
            $label.Margin = [System.Windows.Forms.Padding]::new(0, 5, 5, 5)
            $textSize = [System.Windows.Forms.TextRenderer]::MeasureText($label.Text, $label.Font)
            if ($textSize.Width -gt $maxLabelWidth) { $maxLabelWidth = $textSize.Width }
            $labels[$param] = $label
        }

        foreach ($param in $categories[$category]) {
            $linePanel = New-Object System.Windows.Forms.FlowLayoutPanel
            $linePanel.FlowDirection = [System.Windows.Forms.FlowDirection]::LeftToRight
            $linePanel.WrapContents = $false
            $linePanel.Width = $parentPanel.ClientSize.Width - 20
            $linePanel.Height = 30
            $parentPanel.Controls.Add($linePanel)
            $controls[$param] = $linePanel

            $label = $labels[$param]
            $label.Width = $maxLabelWidth + 10
            $linePanel.Controls.Add($label)
            
            if (($resolvedParameters.Value[$param] -is [bool]) -or (GetParamMetadataValue -param $param -metadata "ActAsBoolean" -parameterMetadata $parameterMetadata)) {
                $checkbox = New-Object System.Windows.Forms.CheckBox
                $checkbox.Checked = $resolvedParameters.Value[$param]
                $linePanel.Controls.Add($checkbox)
            } elseif ($resolvedParameters.Value[$param] -is [int]) {
                $numericUpDown = New-Object System.Windows.Forms.NumericUpDown
                $numericUpDown.Width = 200
                $numericUpDown.Minimum = 1
                $numericUpDown.Maximum = 65535
                $numericUpDown.Value = $resolvedParameters.Value[$param]
                $linePanel.Controls.Add($numericUpDown)
            } else {
                $textbox = New-Object System.Windows.Forms.TextBox
                $textbox.Width = 200
                $textbox.Text = $resolvedParameters.Value[$param]
                $handler = {
                    param($src, $args)
                    $selectedPreset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
                    if ($parameterMetadata[$param].ContainsKey("Link") -and $parameterMetadata[$param]["Link"].ContainsKey($selectedPreset)) {
                        $linkedParams = $parameterMetadata[$param]["Link"][$selectedPreset]
                        foreach ($linkedParam in $linkedParams) {
                            if ($controls.ContainsKey($linkedParam)) {
                                $controls[$linkedParam].Controls[1].Text = $src.Text
                            }
                        }                        
                    }
                }.GetNewClosure();
                $textbox.Add_Leave($handler)
                $textbox.Add_KeyDown({
                    if ($_.KeyCode -eq 'Enter') {
                        $handler.Invoke($this, $null)
                    }
                }.GetNewClosure())
                $linePanel.Controls.Add($textbox)                
            }
        }
    }
    return $controls
}

function New-PresetRadioButtons {
    param (
        [hashtable]$parameters,
        [System.Windows.Forms.Panel]$panel,
        [hashtable]$parameterMetadata
    )
    $presetGroupBox = New-Object System.Windows.Forms.GroupBox
    $presetGroupBox.Text = "Preset Selection"
    $presetGroupBox.Dock = [System.Windows.Forms.DockStyle]::Fill
    $presetGroupBox.Height = 120
    $panel.Controls.Add($presetGroupBox)

    $presetRadioButtons = @{}
    $xOffset = 10

    $validateSetAttr = $parameters["Preset"].Attributes | Where-Object { $_ -is [System.Management.Automation.ValidateSetAttribute] }

    # Add a multi-line, wrappable, scrollable text area for the description
    $descriptionTextBox = New-Object System.Windows.Forms.TextBox
    $descriptionTextBox.Top = 45
    $descriptionTextBox.Left = 10
    $descriptionTextBox.Anchor = 'Top,Left,Right'
    $descriptionTextBox.Width = $presetGroupBox.ClientSize.Width - 20
    $descriptionTextBox.Height = 50
    $descriptionTextBox.Multiline = $true
    $descriptionTextBox.ScrollBars = 'Vertical'
    $descriptionTextBox.WordWrap = $true
    $descriptionTextBox.ReadOnly = $true
    $presetGroupBox.Controls.Add($descriptionTextBox)
    # Handle resizing to keep width in sync with parent
    $presetGroupBox.Add_Resize({
        $descriptionTextBox.Width = $presetGroupBox.ClientSize.Width - 20
    })

    $presetsConfiguration = (Get-Variable -Name "presetsConfiguration" -ErrorAction SilentlyContinue).Value
    foreach ($p in $validateSetAttr.ValidValues) {
        $radioButton = New-Object System.Windows.Forms.RadioButton
        $radioButton.Text = $presetsConfiguration[$p].Text
        $radioButton.Tag = $p
        $radioButton.Left = $xOffset
        $radioButton.Top = 20
        $radioButton.AutoSize = $true
        $presetGroupBox.Controls.Add($radioButton)
        $presetRadioButtons[$p] = $radioButton
        $xOffset += $radioButton.Width + 20
 
        $radioButton.Add_CheckedChanged({
            if ($this.Checked) {
                $presetsConfiguration = (Get-Variable -Name "presetsConfiguration" -ErrorAction SilentlyContinue).Value
                $this.Parent.Controls[0].Text = $presetsConfiguration[$this.Tag].Desr
                Set-Variable -Name "Preset" -Value $this.Tag -Scope Global -Force
            }
        })
    }
    $preset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
    $presetRadioButtons[$preset].Checked = $true
    $descriptionTextBox.Text = $presetsConfiguration[$preset].Desr

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
        $inputControl = $controls[$param].Controls[1]
        if ($inputControl -is [System.Windows.Forms.CheckBox]) {
            $resolvedParameters.Value[$param] = $inputControl.Checked
            continue
        }
        if ($inputControl -is [System.Windows.Forms.NumericUpDown]) {
            $resolvedParameters.Value[$param] = $inputControl.Value
            continue
        }
        # System.Windows.Forms.TextBox
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

    # Use a FlowLayoutPanel to stack all elements vertically (no overlap)
    $mainPanel = New-Object System.Windows.Forms.FlowLayoutPanel
    $mainPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
    $mainPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::TopDown
    $mainPanel.WrapContents = $false
    $mainPanel.AutoScroll = $true
    $form.Controls.Add($mainPanel)

    # Create a top panel for the preset group (radio + description)
    $topPanel = New-Object System.Windows.Forms.Panel
    $topPanel.Width = 980
    $topPanel.Height = 130
    $mainPanel.Controls.Add($topPanel)

    $tabControl = New-Object System.Windows.Forms.TabControl
    $tabControl.Width = 980
    $tabControl.Height = 500
    $mainPanel.Controls.Add($tabControl)

    $categories = Get-ParameterCategories $parameters $parameterMetadata
    $controls = New-ParameterControls $categories $parameters ([ref]$resolvedParameters.Value) $parameterMetadata $tabControl

    $errorProvider = New-Object System.Windows.Forms.ErrorProvider
    $errorProvider.BlinkStyle = 'NeverBlink'
    $errorLabel = New-Object System.Windows.Forms.Label
    $errorLabel.Width = 980
    $errorLabel.ForeColor = 'Red'
    $errorLabel.Height = 30
    $errorLabel.TextAlign = 'MiddleCenter'
    $mainPanel.Controls.Add($errorLabel)

    $button = New-Object System.Windows.Forms.Button
    $button.Text = "Submit"
    $button.Width = 980
    $button.Height = 50
    $mainPanel.Controls.Add($button)

    $presetRadioButtons = New-PresetRadioButtons $parameters $topPanel $parameterMetadata
    foreach ($radioButton in $presetRadioButtons.Values) {
       $radioButton.Add_CheckedChanged({ Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata })
    }
    Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata

    $button.Add_Click({
        Submit-SubmitClick $controls ([ref]$resolvedParameters.Value) $parameterMetadata $errorProvider $errorLabel $form
    })

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
    foreach ($param in $MyInvocation.MyCommand.Parameters.Keys) {
        if (GetParamMetadataValue -param $param -metadata "ScriptArgument") {
            continue
        }
        # Fallback to user input if no value is set
        $defaultValue = (Get-Variable -Name $param -EA SilentlyContinue).Value
        if (-not $resolvedParameters[$param] -or ($resolvedParameters[$param] -eq $defaultValue -and (GetParamMetadataValue -param $param -metadata "ForceInput"))) {
            $resolvedParameters[$param] = Read-InputWithDefault -prompt "Enter value for $param" -defaultValue $defaultValue -paramDef $MyInvocation.MyCommand.Parameters[$param] -parameterMetadata $parameterMetadata[$param]
        }
    }
}

# Output resolved parameters
Write-Output "Resolved Parameters:"
$resolvedParameters
exit 0