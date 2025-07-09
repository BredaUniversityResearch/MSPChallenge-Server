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
    #[int]$TestSwitch = 0,
    [ValidateRange(0, 1)] # in favor of boolean, giving issues in Linux command line
    [int]$EnableGui = 1,
    [string]$DatabasePassword = ([guid]::NewGuid().ToString("N")),
    [string]$CaddyMercureJwtSecret = ([guid]::NewGuid().ToString("N")),
    [string]$BranchName = "msp-ar",
    [string]$AppSecret = ([guid]::NewGuid().ToString("N"))
)

$presetsConfiguration = @{
    Direct = @{
        Text = "Directly exposed"
        Desr = @"
{\rtf1\ansi
The docker container running the MSP Challenge server is directly exposed to the internet on port 443 given the server name, e.g. https://www.example.com.\line
The MSP Challenge web server (Caddy) will automatically obtain a TLS certificate for the given server name.
}
"@
    }
    Proxy  = @{
        Text = "Behind a proxy"
        Desr = @"
{\rtf1\ansi
The docker container running the MSP Challenge server is behind a reverse proxy, like. Nginx, which is responsible for handling:\line
* the server name requests on https, port 443, e.g. https://www.example.com\line
* TLS certificates\line
* forwarding requests to the MSP Challenge server running on http (port 80).\line
For an example nginx proxy setup go to: https://community.mspchallenge.info/wiki/Docker_server_installation#Nginx_proxy_example
}
"@
    }
    LAN = @{
        Text = "Network (LAN) setup"
        Desr = @"
{\rtf1\ansi
The docker container running the MSP Challenge server is exposed to the local area network, by the machine's IP address.
}
"@
    }
    Local = @{
        Text = "Local setup"
        Desr = @"
{\rtf1\ansi
The docker container running MSP Challenge server is locally available on the host machine, e.g. host.docker.internal, or localhost.
}
"@
    }
}

function Get-FirstLanIPv4Address {
    # Cross-platform: Try to get the first non-loopback IPv4 address
    $ip = $null

    # Windows
    try {
        $ip = Get-NetIPInterface -AddressFamily IPv4 |
            Where-Object { $_.InterfaceAlias -match '^(Ethernet|Wi-Fi)' -and $_.ConnectionState -eq 'Connected' } |
            Sort-Object -Property InterfaceMetric |
            ForEach-Object {
                Get-NetIPAddress -InterfaceIndex $_.InterfaceIndex -AddressFamily IPv4 |
                Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' }
            } |
            Select-Object -First 1 -ExpandProperty IPAddress
    } catch {}

    # Try Get-NetIPAddress (some modern Linux)
    try {
        if (-not $ip) {
            $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop |
                Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' -and $_.PrefixOrigin -ne 'WellKnown' } |
                Select-Object -First 1 -ExpandProperty IPAddress
        }
    } catch {}


    # Fallback: Try ip command (Linux)
    if (-not $ip -and (Get-Command ip -ErrorAction SilentlyContinue)) {
        $ip = & ip -4 addr show | Select-String -Pattern 'inet (\d+\.\d+\.\d+\.\d+)' | ForEach-Object {
            if ($_ -match 'inet (\d+\.\d+\.\d+\.\d+)') { $matches[1] }
        } | Where-Object { $_ -ne '127.0.0.1' } | Select-Object -First 1
    }

    # Fallback: Try ifconfig (older Linux/macOS)
    if (-not $ip -and (Get-Command ifconfig -ErrorAction SilentlyContinue)) {
        $ip = & ifconfig | Select-String -Pattern 'inet (\d+\.\d+\.\d+\.\d+)' | ForEach-Object {
            if ($_ -match 'inet (\d+\.\d+\.\d+\.\d+)') { $matches[1] }
        } | Where-Object { $_ -ne '127.0.0.1' } | Select-Object -First 1
    }

    return $ip
}

# Define metadata for parameters
$parameterMetadata = @{
    BranchName = @{
        ScriptArgument = $true
    }
    ServerName = @{
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
            Proxy = 45001
            Local = 45001
            LAN = 45001
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $false
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
        EmptyValuesAllowed = $true
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
            Proxy = 80
            Local = 80
            LAN = 80
        }
        ReadOnly = @{
            Direct = $true
            Proxy = $false
            LAN = $false
            Local = $false
        }
    }
#   TestSwitch = @{
#       ActAsBoolean = $true
#       Tab = "Advanced settings"
#   }
    EnableGui = @{
        ActAsBoolean = $true
        ScriptArgument = $true
    }
    DatabasePassword = @{
        EnvVar = "DATABASE_PASSWORD"
        Tab = "Basic setup"
        Validate = @(
            @{ "The database password must be at least 8 characters." = { param($v) $v.Length -ge 8 } }
        )
    }
    CaddyMercureJwtSecret = @{
        EnvVar = "CADDY_MERCURE_JWT_SECRET"
        Tab = "Basic setup"
        Validate = @(
            @{ "CADDY_MERCURE_JWT_SECRET must be 32 characters." = { param($v) $v.Length -eq 32 } }
        )
    }
    AppSecret = @{
        EnvVar = "APP_SECRET"
        Tab = "Basic setup"
        Validate = @(
            @{ "APP_SECRET must be 32 characters." = { param($v) $v.Length -eq 32 } }
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
        [hashtable]$parameterMetadata = @{}
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
        [hashtable]$resolvedParameters,
        [hashtable]$parameterMetadata
    )
    foreach ($param in $parameters.Keys) {
        if (-not $resolvedParameters[$param]) {
            $envVar = GetParamMetadataValue -param $param -metadata "EnvVar" -parameterMetadata $parameterMetadata
            if ($envVar) {
                $envValue = Get-ChildItem Env:$envVar -ErrorAction SilentlyContinue
                if ($envValue -and $envValue.Value -like "*ChangeThisMercureHubJWTSecretKey*") {
                    $resolvedParameters[$param] = $null
                } elseif ($envValue) {
                    $resolvedParameters[$param] = $envValue.Value
                } else {
                    $resolvedParameters[$param] = $null
                }
            }
        }
        if (-not $resolvedParameters[$param]) {
            $selectedPreset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
            $presetValues = GetParamMetadataValue -param $param -metadata "Presets" -parameterMetadata $parameterMetadata
            if ($presetValues -and $presetValues.Count -gt 0 -and $presetValues.ContainsKey($selectedPreset)) {
                 $resolvedParameters[$param] = $presetValues[$selectedPreset]
            }
        }
        if (-not $resolvedParameters[$param]) {
            $resolvedParameters[$param] = (Get-Variable -Name $param -ErrorAction SilentlyContinue).Value
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
        [hashtable]$resolvedParameters,
        [hashtable]$parameterMetadata,
        [System.Windows.Forms.TabControl]$tabControl
    )
    $controls = @{}
    foreach ($category in $categories.Keys) {
        $tabPageInnerPanel = New-Object System.Windows.Forms.FlowLayoutPanel
        $tabPageInnerPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
        $tabPageInnerPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::TopDown
        $tabPageInnerPanel.WrapContents = $false
        $tabPageInnerPanel.AutoScroll = $true

        $tabPage = New-Object System.Windows.Forms.TabPage
        $tabPage.Text = $category
        $tabPage.Dock = [System.Windows.Forms.DockStyle]::Fill
        $tabPage.Controls.Add($tabPageInnerPanel)
        $tabControl.TabPages.Add($tabPage)

        foreach ($param in $categories[$category]) {
            $linePanel = New-Object System.Windows.Forms.FlowLayoutPanel
            $linePanel.Anchor = [System.Windows.Forms.AnchorStyles]::Right -bor [System.Windows.Forms.AnchorStyles]::Top
            $linePanel.AutoSize = $true
            $linePanel.Padding = New-Object System.Windows.Forms.Padding(0, 0, 30, 0)
            $tabPageInnerPanel.Controls.Add($linePanel)
            $controls[$param] = $linePanel

            $label = New-Object System.Windows.Forms.Label
            $labelText = (GetParamMetadataValue -param $param -metadata "Text" -parameterMetadata $parameterMetadata)
            if (-not $labelText) {
                $labelText = (GetParamMetadataValue -param $param -metadata "EnvVar" -parameterMetadata $parameterMetadata)
            }
            if (-not $labelText) {
                $labelText = $param
            }
            $label.AutoSize = $true
            $label.Anchor = [System.Windows.Forms.AnchorStyles]::Left
            $label.Text = $labelText
            $linePanel.Controls.Add($label)

            $controlWidth = 400
            if (($resolvedParameters[$param] -is [bool]) -or (GetParamMetadataValue -param $param -metadata "ActAsBoolean" -parameterMetadata $parameterMetadata)) {
                $checkbox = New-Object System.Windows.Forms.CheckBox
                $checkbox.Width = $controlWidth
                $checkbox.Checked = $resolvedParameters[$param]
                $linePanel.Controls.Add($checkbox)
            } elseif ($resolvedParameters[$param] -is [int]) {
                $numericUpDown = New-Object System.Windows.Forms.NumericUpDown
                $numericUpDown.Minimum = 1
                $numericUpDown.Maximum = 65535
                $numericUpDown.Width = $controlWidth
                $numericUpDown.Value = $resolvedParameters[$param]
                $linePanel.Controls.Add($numericUpDown)
            } else {
                $textbox = New-Object System.Windows.Forms.TextBox
                $textbox.Text = $resolvedParameters[$param]
                $textbox.Width = $controlWidth
                $handler = {
                    param($src)
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

function Get-RootControl {
    param (
        [System.Windows.Forms.Control]$control
    )
    # Traverse up to the root control
    $root = $control
    while ($root.Parent) {
        $root = $root.Parent
    }
    return $root
}

function Search-ChildControl {
    param (
        [System.Windows.Forms.Control]$parent,
        [string]$name
    )
    foreach ($child in $parent.Controls) {
        if ($child.Name -eq $name) {
            return $child
        }
        $result = Search-ChildControl $child $name
        if ($result) { return $result }
    }
    return $null
}

function New-PresetRadioButtons {
    param (
        [System.Windows.Forms.FlowLayoutPanel]$parent,
        [System.Windows.Forms.RichTextBox]$textBox,
        [hashtable]$parameters,
        [hashtable]$parameterMetadata
    )
    $presetRadioButtons = @{}
    $validateSetAttr = $parameters["Preset"].Attributes | Where-Object { $_ -is [System.Management.Automation.ValidateSetAttribute] }
    $presetsConfiguration = (Get-Variable -Name "presetsConfiguration" -ErrorAction SilentlyContinue).Value
    foreach ($p in $validateSetAttr.ValidValues) {
        $radioButton = New-Object System.Windows.Forms.RadioButton
        $radioButton.AutoSize = $true
        $radioButton.Text = $presetsConfiguration[$p].Text
        $radioButton.Tag = $p
        $parent.Controls.Add($radioButton)
        $presetRadioButtons[$p] = $radioButton

        $radioButton.Add_CheckedChanged({
            if ($this.Checked) {
                $presetsConfiguration = (Get-Variable -Name "presetsConfiguration" -ErrorAction SilentlyContinue).Value
                $descriptionTextBox = Search-ChildControl (Get-RootControl $this) "DescriptionTextBox"
                if (-not $descriptionTextBox) {
                    Write-Host "Description text box not found." -ForegroundColor Red
                    return
                }
                $descriptionTextBox.Rtf = $presetsConfiguration[$this.Tag].Desr
                Set-Variable -Name "Preset" -Value $this.Tag -Scope Global -Force
            }
        })
    }
    $preset = (Get-Variable -Name "Preset" -ErrorAction SilentlyContinue).Value
    $presetRadioButtons[$preset].Checked = $true
    $descriptionTextBox.Rtf = $presetsConfiguration[$preset].Desr
    return $presetRadioButtons
}

function Submit-SubmitClick {
    param (
        [hashtable]$controls,
        [hashtable]$resolvedParameters,
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
            $resolvedParameters[$param] = $inputControl.Checked
            continue
        }
        if ($inputControl -is [System.Windows.Forms.NumericUpDown]) {
            $resolvedParameters[$param] = $inputControl.Value
            continue
        }
        # System.Windows.Forms.TextBox
        $value = $inputControl.Text
        $validateArr = GetParamMetadataValue -param $param -metadata "Validate" -parameterMetadata $parameterMetadata
        $errorMsg = ""

        # Default validation: empty values not allowed unless EmptyValuesAllowed is $true
        $emptyAllowed = $false
        if ($parameterMetadata.ContainsKey($param) -and $parameterMetadata[$param].ContainsKey("EmptyValuesAllowed")) {
            $emptyAllowed = $parameterMetadata[$param]["EmptyValuesAllowed"]
        }
        if (-not $emptyAllowed -and ([string]::IsNullOrWhiteSpace($value))) {
            $errorMsg = "Value cannot be empty."
        }

        # Custom validation from Validate property
        if (-not $errorMsg -and $validateArr) {
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
        $resolvedParameters[$param] = $value
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
        [hashtable]$resolvedParameters,
        [hashtable]$parameterMetadata
    )

    Add-Type -AssemblyName System.Windows.Forms

    $form = New-Object System.Windows.Forms.Form
    $form.Text = "MSP Challenge Server setup"
    $form.Width = 1024
    $form.Height = 768

    $mainPanel = New-Object System.Windows.Forms.FlowLayoutPanel
    $mainPAnel.AutoSizeMode = [System.Windows.Forms.AutoSizeMode]::GrowAndShrink
    $mainPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
    $mainPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::TopDown
    $form.Controls.Add($mainPanel)

    $presetGroupBox = New-Object System.Windows.Forms.GroupBox
    $presetGroupBox.Text = "Preset Selection"
    $presetGroupBox.AutoSize = $true
    $presetGroupBox.Dock = [System.Windows.Forms.DockStyle]::Top
    $mainPanel.Controls.Add($presetGroupBox)

    $presetGroupBoxInnerPanel = New-Object System.Windows.Forms.FlowLayoutPanel
    $presetGroupBoxInnerPanel.AutoSize = $true
    $presetGroupBoxInnerPanel.Dock = [System.Windows.Forms.DockStyle]::None
    $presetGroupBoxInnerPanel.Height = 23
    $presetGroupBoxInnerPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::LeftToRight
    $presetGroupBoxInnerPanel.Margin = New-Object System.Windows.Forms.Padding(3, 3, 3, 3)
    $presetGroupBoxInnerPanel.Location = New-Object System.Drawing.Point(3, 16)
    $presetGroupBoxInnerPanel.WrapContents = $false
    $presetGroupBox.Controls.Add($presetGroupBoxInnerPanel)

    # Add a multi-line, wrappable, scrollable text area for the description
    $descriptionTextBox = New-Object System.Windows.Forms.RichTextBox
    $descriptionTextBox.Name = "DescriptionTextBox"
    $descriptionTextBox.Dock = [System.Windows.Forms.DockStyle]::Top
    $descriptionTextBox.Multiline = $true
    $descriptionTextBox.ScrollBars = [System.Windows.Forms.RichTextBoxScrollBars]::Vertical
    $descriptionTextBox.WordWrap = $true
    $descriptionTextBox.ReadOnly = $true
    $mainPanel.Controls.Add($descriptionTextBox)

    $presetRadioButtons = New-PresetRadioButtons $presetGroupBoxInnerPanel $descriptionTextBox $parameters $parameterMetadata
    foreach ($radioButton in $presetRadioButtons.Values) {
       $radioButton.Add_CheckedChanged({ Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata })
    }
    Update-ControlsByPreset $controls $presetRadioButtons $parameterMetadata

    $tabControl = New-Object System.Windows.Forms.TabControl
    # note that the width and height will automatically adjust based on the main panel size below
    $tabControl.Width = 600
    $tabControl.Height = 200
    $resizeHandler = {
        $usedHeight = 0
        foreach ($ctrl in $mainPanel.Controls) {
            if (($ctrl -ne $tabControl) -and $ctrl.Visible) {
                $usedHeight += $ctrl.Height + $ctrl.Margin.Top + $ctrl.Margin.Bottom
            }
        }
        $tabControl.Width = [Math]::Max(0, $mainPanel.ClientSize.Width - $tabControl.Margin.Left - $tabControl.Margin.Right)
        $tabControl.Height = [Math]::Max(0, $mainPanel.ClientSize.Height - $usedHeight - $tabControl.Margin.Top - $tabControl.Margin.Bottom)
    }
    $mainPanel.Add_Resize($resizeHandler)
    $form.Add_Shown({
        $resizeHandler.Invoke()
    })
    $mainPanel.Controls.Add($tabControl)
    $categories = Get-ParameterCategories $parameters $parameterMetadata
    $controls = New-ParameterControls $categories $parameters $resolvedParameters $parameterMetadata $tabControl

    $errorProvider = New-Object System.Windows.Forms.ErrorProvider
    $errorProvider.BlinkStyle = 'NeverBlink'
    $errorLabel = New-Object System.Windows.Forms.Label
    $errorLabel.Dock = [System.Windows.Forms.DockStyle]::Top
    $errorLabel.ForeColor = 'Red'
    $errorLabel.TextAlign = [System.Drawing.ContentAlignment]::MiddleCenter
    $mainPanel.Controls.Add($errorLabel)

    $button = New-Object System.Windows.Forms.Button
    $button.Dock = [System.Windows.Forms.DockStyle]::Top
    $button.Text = "Submit"
    $mainPanel.Controls.Add($button)

    $button.Add_Click({
        Submit-SubmitClick $controls $resolvedParameters $parameterMetadata $errorProvider $errorLabel $form
    })

    $form.ShowDialog()
}

function Invoke-NonGuiMode {
    param(
        [hashtable]$parameters,
        [hashtable]$parameterMetadata,
        [hashtable]$resolvedParameters,
        [hashtable]$boundParameters
    )
    Write-Host "Running in non-GUI mode..."
    $presetParam = "Preset"
    $defaultValue = (Get-Variable -Name $presetParam -EA SilentlyContinue).Value
    $selectedPreset = $resolvedParameters[$presetParam] = Read-InputWithDefault -prompt "Enter value for $presetParam" -defaultValue $defaultValue -paramDef $parameters[$presetParam]
    Set-Variable -Name "Preset" -Value $selectedPreset -Scope Global -Force
    Initialize-Parameters $parameters ([ref]$resolvedParameters) $parameterMetadata
    foreach ($param in $parameters.Keys) {
        # param is already set from command line, skip it
        if ($boundParameters.ContainsKey($param)) {
            continue
        }
        # if param is Preset, skip it
        if ($param -eq $presetParam) {
            continue
        }
        if (GetParamMetadataValue -param $param -metadata "ScriptArgument") {
            continue
        }
        # if the param is set to read-only for the current preset, skip it
        $readOnly = GetParamMetadataValue -param $param -metadata "ReadOnly" -parameterMetadata $parameterMetadata
        if ($readOnly -and $readOnly.ContainsKey($selectedPreset) -and $readOnly[$selectedPreset]) {
            Write-Host "$param is read-only for preset '$selectedPreset'. Skipping..."
            continue
        }
        # Check if any previous param links to this param for the current preset
        $linked = $false
        foreach ($prevParam in $parameters.Keys) {
            if ($prevParam -eq $param) { break }
            $linkMeta = GetParamMetadataValue -param $prevParam -metadata "Link"
            if ($linkMeta -and $linkMeta.ContainsKey($selectedPreset) -and $linkMeta[$selectedPreset] -contains $param) {
                $resolvedParameters[$param] = $resolvedParameters[$prevParam]
                Write-Host "Linked $param to $prevParam value: $($resolvedParameters[$param])"
                $linked = $true
                break
            }
        }
        if ($linked) {
            continue
        }
        # Prompt for input
        $resolvedParameters[$param] = Read-InputWithDefault -prompt "Enter value for $param" -defaultValue $resolvedParameters[$param] -paramDef $parameters[$param] -parameterMetadata $parameterMetadata[$param]
    }
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

# Make a copy of PSBoundParameters to avoid modifying the original
$resolvedParameters = @{}

foreach ($key in $PSBoundParameters.Keys) {
    $resolvedParameters[$key] = $PSBoundParameters[$key]
}

if ($EnableGui -and ($env:OS -eq "Windows_NT")) {
    Write-Host "Running in GUI mode..."
    Initialize-Parameters $MyInvocation.MyCommand.Parameters $resolvedParameters $parameterMetadata
    Show-GuiParameterForm $MyInvocation.MyCommand.Parameters $resolvedParameters $parameterMetadata
} else {
    Invoke-NonGuiMode $MyInvocation.MyCommand.Parameters $parameterMetadata $resolvedParameters $PSBoundParameters
}

# Remove from $resolvedParameters those params that do not have an EnvVar metadata
# Build all lines first, then write at once with Set-Content (avoids BOM issues in PowerShell Core and always writes the file)
$envLines = @()
foreach ($param in $resolvedParameters.Keys.Clone()) {
    $envVar = GetParamMetadataValue -param $param -metadata "EnvVar" -parameterMetadata $parameterMetadata
    if (-not $envVar) {
        $resolvedParameters.Remove($param)
        continue
    }
    $envLines += "$envVar=$($resolvedParameters[$param])"
}
Set-Content -Path ".env.local" -Value $envLines -Encoding UTF8

Write-Host "Written .env.local:" -ForegroundColor Green
Get-Content ".env.local" | ForEach-Object { Write-Host $_ -ForegroundColor Cyan }
Write-Host "Do you want to continue with the installation? (Y/N)" -ForegroundColor Yellow
$continue = Read-Host
if ($continue -ne "Y" -and $continue -ne "y") {
    Write-Host "Installation aborted." -ForegroundColor Red
    exit 1
}
# Check if Docker is installed
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Host "Docker is not installed. Please install Docker and try again." -ForegroundColor Red
    exit 1
}
Write-Host "Downloading docker-compose files for branch '$BranchName'..." -ForegroundColor Cyan
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$BranchName/docker-compose.yml" -OutFile "docker-compose.yml"
Invoke-WebRequest -Uri "https://raw.githubusercontent.com/BredaUniversityResearch/MSPChallenge-Server/refs/heads/$BranchName/docker-compose.prod.yml" -OutFile "docker-compose.prod.yml"

# Check if docker-compose.yml and docker-compose.prod.yml exist
if (-not (Test-Path "docker-compose.yml") -or -not (Test-Path "docker-compose.prod.yml")) {
    Write-Host "Error: docker-compose files not found. Please check the branch name." -ForegroundColor Red
    exit 1
}
Write-Host "Starting Docker containers..." -ForegroundColor Cyan

docker compose --env-file .env.local -f docker-compose.yml -f "docker-compose.prod.yml" up -d
exit 0
