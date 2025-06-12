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
    [int]$EnableGui = 1,
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
        EnvVar = "SERVER_NAME"
        Tab = "MSP Challenge Server"
        Environments = @("production")
    }
    ServerPort = @{
        EnvVar = "SERVER_PORT"
        Tab = "MSP Challenge Server"
    }
    TestSwitch = @{
        ActAsBoolean = $true
        Tab = "Other"
    }
    EnableGui = @{
        ActAsBoolean = $true
    }
    Var1 = @{ Tab="Test" }
    Var2 = @{ Tab="Test" }
    Var3 = @{ Tab="Test" }
    Var4 = @{ Tab="Test" }
    Var5 = @{ Tab="Test" }
    Var6 = @{ Tab="Test" }
    Var7 = @{ Tab="Test" }
    Var8 = @{ Tab="Test" }
    Var9 = @{ Tab="Test" }
    Var10 = @{ Tab="Test" }
    Var11 = @{ Tab="Test" }
    Var12 = @{ Tab="Test" }
    Var13 = @{ Tab="Test" }
    Var14 = @{ Tab="Test" }
    Var15 = @{ Tab="Test" }
    Var16 = @{ Tab="Test" }
    Var17 = @{ Tab="Test" }
    Var18 = @{ Tab="Test" }
    Var19 = @{ Tab="Test" }
    Var20 = @{ Tab="Test" }
    Var21 = @{ Tab="Test" }
    Var22 = @{ Tab="Test" }
    Var23 = @{ Tab="Test" }
    Var24 = @{ Tab="Test" }
    Var25 = @{ Tab="Test" }
    Var26 = @{ Tab="Test" }
    Var27 = @{ Tab="Test" }
    Var28 = @{ Tab="Test" }
    Var29 = @{ Tab="Test" }
    Var30 = @{ Tab="Test" }
    Var31 = @{ Tab="Test" }
    Var32 = @{ Tab="Test" }
    Var33 = @{ Tab="Test" }
    Var34 = @{ Tab="Test" }
    Var35 = @{ Tab="Test" }
    Var36 = @{ Tab="Test" }
    Var37 = @{ Tab="Test" }
    Var38 = @{ Tab="Test" }
    Var39 = @{ Tab="Test" }
    Var40 = @{ Tab="Test" }
}

# Function to update visible controls based on the selected environment
function Update-VisibleControls {
    $selectedEnv = $envRadioButtons.Keys | Where-Object { $envRadioButtons[$_].Checked }
    foreach ($param in $controls.Keys) {
        $validEnvs = $parameterMetadata[$param]["Environments"]
        $container = $controls[$param]
        if ($validEnvs -and -not ($validEnvs -contains $selectedEnv)) {
            $container.Visible = $false
        } else {
            $container.Visible = $true
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

$parameters = $MyInvocation.MyCommand.Parameters
foreach ($param in $parameters.Keys) {
    if (-not $PSBoundParameters[$param]) {
        $envVar = GetParamMetadataValue -param $param -metadata "EnvVar"
        if ($envVar) {
            $envValue = Get-ChildItem Env:$envVar -ErrorAction SilentlyContinue
            $PSBoundParameters[$param] = if ($envValue) { $envValue.Value } else { $null }
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

    # Create the main form
    $form = New-Object System.Windows.Forms.Form
    $form.Text = "Input Parameters"
    $form.Width = 1024
    $form.Height = 768

    # Create a Panel to hold the TabControl and Button
    $mainPanel = New-Object System.Windows.Forms.Panel
    $mainPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
    $form.Controls.Add($mainPanel)

    # Create a TabControl
    $tabControl = New-Object System.Windows.Forms.TabControl
    $tabControl.Dock = [System.Windows.Forms.DockStyle]::Fill
    $mainPanel.Controls.Add($tabControl)

    # Define categories for grouping parameters
    $categories = @{}

    foreach ($param in $parameters.Keys) {
        if (-not (GetParamMetadataValue -param $param -metadata "Tab")) {
            continue
        }
        $category = (GetParamMetadataValue -param $param -metadata "Tab")
        if (-not $categories.ContainsKey($category)) {
            $categories[$category] = @()
        }
        $categories[$category] += $param
    }    

    # Initialize the controls hashtable
    $controls = @{}

    foreach ($category in $categories.Keys) {
        # Create a TabPage for each category
        $tabPage = New-Object System.Windows.Forms.TabPage
        $tabPage.Text = $category
        $tabPage.Dock = [System.Windows.Forms.DockStyle]::Fill
        $tabControl.TabPages.Add($tabPage)

        # Create a parent FlowLayoutPanel for all parameters
        $parentPanel = New-Object System.Windows.Forms.FlowLayoutPanel
        $parentPanel.Dock = [System.Windows.Forms.DockStyle]::Fill
        $parentPanel.FlowDirection = [System.Windows.Forms.FlowDirection]::TopDown
        $parentPanel.WrapContents = $false
        $parentPanel.AutoScroll = $true
        $tabPage.Controls.Add($parentPanel)

        # calculate the max. width the label
        # Initialize a variable to track the maximum label width
        $maxLabelWidth = 0
        foreach ($param in $categories[$category])
        {
            if (-not (GetParamMetadataValue -param $param -metadata "Tab")) {
                continue
            }
            $label = New-Object System.Windows.Forms.Label
            $label.Text = $param
            # Measure the width of the label text
            $textSize = [System.Windows.Forms.TextRenderer]::MeasureText($label.Text, $label.Font)
            $currentLabelWidth = $textSize.Width
            # Update the maximum label width
            if ($currentLabelWidth -gt $maxLabelWidth) {
                $maxLabelWidth = $currentLabelWidth
            }
        }

        foreach ($param in $categories[$category]) {
            if (-not (GetParamMetadataValue -param $param -metadata "Tab")) {
                continue
            }

            # Create a container FlowLayoutPanel for each parameter
            $linePanel = New-Object System.Windows.Forms.FlowLayoutPanel
            $linePanel.FlowDirection = [System.Windows.Forms.FlowDirection]::LeftToRight
            $linePanel.WrapContents = $false
            $linePanel.Width = $parentPanel.ClientSize.Width - 20
            $linePanel.Height = 30
            $parentPanel.Controls.Add($linePanel)

            # Store the container for visibility updates
            $controls[$param] = $linePanel

            # Create a Label
            $label = New-Object System.Windows.Forms.Label
            $label.Text = $param
            $label.Width = $maxLabelWidth+10
            $label.Anchor = [System.Windows.Forms.AnchorStyles]::None
            $label.Margin = [System.Windows.Forms.Padding]::new(0, 5, 5, 5) # Adjust vertical alignment
            $linePanel.Controls.Add($label)

            if (($PSBoundParameters[$param] -is [bool]) -or (GetParamMetadataValue -param $param -metadata "ActAsBoolean")) {
                $checkbox = New-Object System.Windows.Forms.CheckBox
                $checkbox.Checked = $PSBoundParameters[$param]
                $linePanel.Controls.Add($checkbox)
            } elseif ($PSBoundParameters[$param] -is [int]) {
                $numericUpDown = New-Object System.Windows.Forms.NumericUpDown
                $numericUpDown.Width = 200
                $numericUpDown.Minimum = 1
                $numericUpDown.Maximum = 65535
                $numericUpDown.Value = $PSBoundParameters[$param]
                $linePanel.Controls.Add($numericUpDown)
            } else {
                # Create a TextBox
                $textbox = New-Object System.Windows.Forms.TextBox
                $textbox.Width = 200
                $textbox.Text = $PSBoundParameters[$param]
                $linePanel.Controls.Add($textbox)
            }
        }
    }

    # Create a Button
    $button = New-Object System.Windows.Forms.Button
    $button.Text = "Submit"
    $button.Dock = [System.Windows.Forms.DockStyle]::Bottom
    $button.Height = 50
    $button.Add_Click({
        foreach ($param in $controls.Keys) {
            if ($controls[$param].Visible) { # exclude hidden parameters
                if ($controls[$param] -is [System.Windows.Forms.CheckBox]) {
                    $PSBoundParameters[$param] = $controls[$param].Checked
                } else {
                    $PSBoundParameters[$param] = $controls[$param].Text
                }
            }
        }
        $form.Close()
    })
    $mainPanel.Controls.Add($button)

    # Add a GroupBox for environment selection
    $envGroupBox = New-Object System.Windows.Forms.GroupBox
    $envGroupBox.Text = "Target Environment"
    $envGroupBox.Dock = [System.Windows.Forms.DockStyle]::Top
    $envGroupBox.Height = 60
    $form.Controls.Add($envGroupBox)

    # Add radio buttons for environments
    $environments = @("dev", "staging", "production")
    $envRadioButtons = @{}
    $xOffset = 10
    foreach ($env in $environments) {
        $radioButton = New-Object System.Windows.Forms.RadioButton
        $radioButton.Text = $env
        $radioButton.Left = $xOffset
        $radioButton.Top = 20
        $radioButton.AutoSize = $true
        $envGroupBox.Controls.Add($radioButton)
        $envRadioButtons[$env] = $radioButton
        $xOffset += 100
    }
    $envRadioButtons["dev"].Checked = $true # Default to "dev"

    # Attach event handlers to radio buttons
    foreach ($radioButton in $envRadioButtons.Values) {
        $radioButton.Add_CheckedChanged({ Update-VisibleControls })
    }

    # Call the function initially to set visibility
    Update-VisibleControls

    # Show the form
    $form.ShowDialog()

    # Output resolved parameters
    Write-Output "Resolved Parameters:"
    $PSBoundParameters
    exit 0
}

Write-Host "Running in non-GUI mode..."

# non-GUI Mode
foreach ($param in $parameters.Keys) {
    if (-not (GetParamMetadataValue -param $param -metadata "Tab")) {
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
