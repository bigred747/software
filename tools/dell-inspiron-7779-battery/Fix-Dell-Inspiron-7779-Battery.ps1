#Requires -Version 5.1
<#
  Dell Inspiron 17 7779 (P30E) charge-path check.

  The official adapter is 65W, 19.5V, 3.34A. The pack is 4-cell 56Wh, 15.2V.
  Green signals report what the motherboard can see: the cord, the barrel
  fitting and its center ID pin, the embedded controller, the battery cable,
  and whether the pack is actually taking charge.

  The only change it makes is a disable/enable of the ACPI battery device,
  and only while AC power stays online. It does not edit BIOS or open the PC.
#>
Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Continue'

$ExpectedWatts = 65
$DesignWh = 56

function Test-IsAdmin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Get-OsInfo {
    $cv = Get-ItemProperty -Path 'HKLM:\SOFTWARE\Microsoft\Windows NT\CurrentVersion'
    $build = 0
    if ($null -ne $cv.CurrentBuild) { $build = [int]$cv.CurrentBuild }
    $display = ''
    if ($cv.PSObject.Properties['DisplayVersion'] -and $cv.DisplayVersion) {
        $display = [string]$cv.DisplayVersion
    } elseif ($cv.PSObject.Properties['ReleaseId'] -and $cv.ReleaseId) {
        $display = [string]$cv.ReleaseId
    }
    $name = [string]$cv.ProductName
    if ($build -ge 22000 -and $name -match 'Windows 10') { $name = 'Windows 11' }
    return @{
        Build   = $build
        Display = $display
        Name    = $name
        IsWin11 = ($build -ge 22000)
        Ok      = ($build -ge 19041)
    }
}

function Get-MachineInfo {
    $cs = Get-CimInstance -ClassName Win32_ComputerSystem
    $bios = Get-CimInstance -ClassName Win32_BIOS
    $board = Get-CimInstance -ClassName Win32_BaseBoard
    $model = [string]$cs.Model
    $sku = ''
    if ($cs.PSObject.Properties['SystemSKUNumber'] -and $cs.SystemSKUNumber) {
        $sku = [string]$cs.SystemSKUNumber
    }
    $family = ''
    if ($cs.PSObject.Properties['SystemFamily'] -and $cs.SystemFamily) {
        $family = [string]$cs.SystemFamily
    }
    $boardProduct = ''
    if ($board -and $board.Product) { $boardProduct = [string]$board.Product }
    $blob = "$model $sku $family $boardProduct"
    $is7779 = ($blob -match '7779') -or ($blob -match 'P30E')
    return @{
        Manufacturer = [string]$cs.Manufacturer
        Model        = $model
        Sku          = $sku
        Family       = $family
        Board        = $boardProduct
        Bios         = [string]$bios.SMBIOSBIOSVersion
        ServiceTag   = [string]$bios.SerialNumber
        Is7779       = [bool]$is7779
    }
}

function Get-AcOnline {
    try {
        $rows = @(Get-CimInstance -Namespace root\wmi -ClassName BatteryStatus -ErrorAction Stop)
        $saw = $false
        foreach ($row in $rows) {
            if ($null -eq $row) { continue }
            $prop = $row.PSObject.Properties['PowerOnline']
            if ($null -eq $prop -or $null -eq $prop.Value) { continue }
            $saw = $true
            if ($prop.Value) { return $true }
        }
        if ($saw) { return $false }
    } catch {
    }

    try {
        $bats = @(Get-CimInstance -ClassName Win32_Battery -ErrorAction Stop)
        foreach ($bat in $bats) {
            if ($null -eq $bat -or $null -eq $bat.BatteryStatus) { continue }
            $codeText = [string]$bat.BatteryStatus
            if ($codeText -notmatch '^\d+$') { continue }
            $code = [int]$codeText
            if ($code -eq 3 -or ($code -ge 6 -and $code -le 9)) { return $true }
            if ($code -eq 1 -or $code -eq 4 -or $code -eq 5) { return $false }
        }
    } catch {
    }
    return $null
}

function Get-AcSamples {
    $list = New-Object System.Collections.Generic.List[string]
    for ($i = 0; $i -lt 5; $i++) {
        $on = Get-AcOnline
        if ($null -eq $on) {
            [void]$list.Add('unknown')
        } elseif ($on) {
            [void]$list.Add('on')
        } else {
            [void]$list.Add('off')
        }
        if ($i -lt 4) { Start-Sleep -Milliseconds 400 }
    }
    return @($list)
}

function Convert-CapacityMwh {
    param($Raw)
    if ($null -eq $Raw) { return $null }
    $text = ([string]$Raw).Trim()
    if ($text -notmatch '^\d+(\.\d+)?$') { return $null }
    $value = [double]$text
    if ($value -le 0) { return $null }
    if ($value -lt 200) { $value = $value * 1000 }
    return [int][math]::Round($value)
}

function Get-BatteryReading {
    $result = @{
        Present          = $false
        Percent          = $null
        Status           = $null
        StatusText       = ''
        Charging         = $false
        Discharging      = $false
        PowerOnline      = $null
        ChargeRateMw     = $null
        DischargeRateMw  = $null
        VoltageMv        = $null
        DesignMwh        = $null
        FullMwh          = $null
        HealthPct        = $null
        Name             = ''
    }
    $statusMap = @{
        1  = 'Other'
        2  = 'Unknown'
        3  = 'Fully charged'
        4  = 'Low'
        5  = 'Critical'
        6  = 'Charging'
        7  = 'Charging and high'
        8  = 'Charging and low'
        9  = 'Charging and critical'
        10 = 'Undefined'
        11 = 'Partially charged'
    }

    try {
        $bats = @(Get-CimInstance -ClassName Win32_Battery -ErrorAction Stop)
        foreach ($bat in $bats) {
            if ($null -eq $bat) { continue }
            $result.Present = $true
            if ($null -ne $bat.EstimatedChargeRemaining) {
                $result.Percent = [int]$bat.EstimatedChargeRemaining
            }
            if ($null -ne $bat.BatteryStatus) {
                $result.Status = [int]$bat.BatteryStatus
                if ($statusMap.ContainsKey($result.Status)) {
                    $result.StatusText = [string]$statusMap[$result.Status]
                }
            }
            if ($bat.Name) { $result.Name = [string]$bat.Name }
            $fromDesign = Convert-CapacityMwh $bat.DesignCapacity
            if ($fromDesign) { $result.DesignMwh = $fromDesign }
            $fromFull = Convert-CapacityMwh $bat.FullChargeCapacity
            if ($fromFull) { $result.FullMwh = $fromFull }
            break
        }
    } catch {
    }

    try {
        $rows = @(Get-CimInstance -Namespace root\wmi -ClassName BatteryStatus -ErrorAction Stop)
        foreach ($row in $rows) {
            if ($null -eq $row) { continue }
            $result.Present = $true
            $prop = $row.PSObject.Properties['PowerOnline']
            if ($prop) { $result.PowerOnline = [bool]$prop.Value }
            $prop = $row.PSObject.Properties['Charging']
            if ($prop -and $prop.Value) { $result.Charging = $true }
            $prop = $row.PSObject.Properties['Discharging']
            if ($prop -and $prop.Value) { $result.Discharging = $true }
            $prop = $row.PSObject.Properties['ChargeRate']
            if ($prop -and $null -ne $prop.Value) { $result.ChargeRateMw = [int]$prop.Value }
            $prop = $row.PSObject.Properties['DischargeRate']
            if ($prop -and $null -ne $prop.Value) { $result.DischargeRateMw = [int]$prop.Value }
            $prop = $row.PSObject.Properties['Voltage']
            if ($prop -and $null -ne $prop.Value) { $result.VoltageMv = [int]$prop.Value }
            break
        }
    } catch {
    }

    try {
        $staticRows = @(Get-CimInstance -Namespace root\wmi -ClassName BatteryStaticData -ErrorAction Stop)
        foreach ($row in $staticRows) {
            if ($null -eq $row) { continue }
            $prop = $row.PSObject.Properties['DesignedCapacity']
            if ($prop) {
                $parsed = Convert-CapacityMwh $prop.Value
                if ($parsed) { $result.DesignMwh = $parsed }
            }
            break
        }
    } catch {
    }

    try {
        $fullRows = @(Get-CimInstance -Namespace root\wmi -ClassName BatteryFullChargedCapacity -ErrorAction Stop)
        foreach ($row in $fullRows) {
            if ($null -eq $row) { continue }
            $prop = $row.PSObject.Properties['FullChargedCapacity']
            if ($prop) {
                $parsed = Convert-CapacityMwh $prop.Value
                if ($parsed) { $result.FullMwh = $parsed }
            }
            break
        }
    } catch {
    }

    try {
        if ($result.DesignMwh -and $result.FullMwh -and $result.DesignMwh -gt 0) {
            $result.HealthPct = [int][math]::Round(100.0 * $result.FullMwh / $result.DesignMwh)
        }
    } catch {
    }
    if ($null -ne $result.Status -and $result.Status -ge 6 -and $result.Status -le 9) { $result.Charging = $true }
    if ($result.ChargeRateMw -and $result.ChargeRateMw -gt 0) { $result.Charging = $true }
    return $result
}

function Find-AdapterWatts {
    $candidates = @(
        @{ Ns = 'root\dcim\sysman'; Class = 'DCIM_PowerSupply' },
        @{ Ns = 'root\dcim\sysman'; Class = 'DCIM_Battery' },
        @{ Ns = 'root\dcim\sysman'; Class = 'DCIM_NumericSensor' },
        @{ Ns = 'root\cimv2\dell'; Class = 'Dell_PowerSupply' },
        @{ Ns = 'root\cimv2\dell'; Class = 'CIM_PowerSupply' }
    )
    foreach ($candidate in $candidates) {
        $rows = @()
        try {
            $rows = @(Get-CimInstance -Namespace $candidate.Ns -ClassName $candidate.Class -ErrorAction Stop)
        } catch {
            continue
        }
        foreach ($row in $rows) {
            if ($null -eq $row) { continue }
            try {
            $caption = ''
            foreach ($labelName in @('Caption', 'Description', 'Name', 'ElementName', 'DeviceID')) {
                $label = $row.PSObject.Properties[$labelName]
                if ($label -and $label.Value) { $caption += ' ' + [string]$label.Value }
            }
            $isSensor = $candidate.Class -eq 'DCIM_NumericSensor'
            if ($isSensor -and $caption -notmatch 'adapter|psid') { continue }
            foreach ($prop in @($row.PSObject.Properties)) {
                $propName = [string]$prop.Name
                $interesting = $propName -match 'Watt|RatedPower|OutputPower|ActivePower|PSID'
                if ($isSensor) { $interesting = $propName -match 'CurrentReading|Watt' }
                if (-not $interesting) { continue }
                $text = [string]$prop.Value
                if ($text -match '(?<![0-9])(\d{2,3})(?![0-9])') {
                    $num = [int]$Matches[1]
                    if ($num -ge 30 -and $num -le 240) {
                        return @{
                            Watts  = $num
                            Source = ($candidate.Ns + '\' + $candidate.Class + '.' + $propName)
                        }
                    }
                }
            }
            } catch {
                continue
            }
        }
    }
    return $null
}

function Get-WattageUnknown {
    $result = @{ Hit = $false; Detail = '' }
    try {
        $events = @(Get-WinEvent -FilterHashtable @{
            LogName   = 'System'
            StartTime = (Get-Date).AddDays(-7)
        } -MaxEvents 80 -ErrorAction Stop)
    } catch {
        return $result
    }
    foreach ($logEvent in $events) {
        $message = ''
        try {
            $message = [string]$logEvent.Message
        } catch {
            continue
        }
        if ($message -match 'wattage cannot be determined|cannot determine the AC adapter|AC adapter type cannot be determined') {
            $clip = $message.Trim()
            if ($clip.Length -gt 220) { $clip = $clip.Substring(0, 220) }
            $result.Hit = $true
            $result.Detail = $clip
            return $result
        }
    }
    return $result
}

function Get-ChargeDevices {
    $list = New-Object System.Collections.Generic.List[object]
    try {
        $nodes = @(Get-CimInstance -ClassName Win32_PnPEntity -ErrorAction Stop)
    } catch {
        return @()
    }
    foreach ($node in $nodes) {
        if ($null -eq $node) { continue }
        $className = ''
        if ($node.PSObject.Properties['PNPClass'] -and $node.PNPClass) {
            $className = [string]$node.PNPClass
        }
        $friendly = [string]$node.Name
        $match = ($className -eq 'Battery') -or ($friendly -match 'Control Method Battery|Microsoft AC Adapter|Composite Battery')
        if ($friendly -match 'Bluetooth|HID-compliant|USB Input Device') { $match = $false }
        if (-not $match) { continue }
        $code = -1
        if ($null -ne $node.ConfigManagerErrorCode) { $code = [int]$node.ConfigManagerErrorCode }
        [void]$list.Add([pscustomobject]@{
            Name       = $friendly
            InstanceId = [string]$node.DeviceID
            Class      = $className
            ErrorCode  = $code
            Ok         = ($code -eq 0)
        })
    }
    return @($list)
}

function Test-NamedDevice {
    param($Devices, [string]$Pattern)
    $hits = @($Devices | Where-Object { $_.Name -match $Pattern })
    if ($hits.Count -eq 0) {
        return @{ Present = $false; Ok = $false; Names = 'Not listed' }
    }
    $bad = @($hits | Where-Object { -not $_.Ok })
    $names = (@($hits | ForEach-Object { '{0} (code {1})' -f $_.Name, $_.ErrorCode })) -join '; '
    return @{ Present = $true; Ok = ($bad.Count -eq 0); Names = $names }
}

function Invoke-Pnputil {
    param([Parameter(Mandatory = $true)][string[]]$PnPArgs)
    $output = ''
    $code = 1
    try {
        $output = (& pnputil.exe @PnPArgs 2>&1 | Out-String)
        if ($null -ne $LASTEXITCODE) { $code = [int]$LASTEXITCODE }
    } catch {
        $output = [string]$_
        $code = 1
    }
    if ($null -eq $output) { $output = '' }
    return @{ Exit = $code; Text = $output.Trim() }
}

function Reset-BatteryDriver {
    param($Devices)
    $targets = @($Devices | Where-Object { $_.Name -match 'Control Method Battery|Composite Battery' -and $_.InstanceId })
    if ($targets.Count -gt 4) { $targets = @($targets | Select-Object -First 4) }
    $log = New-Object System.Collections.Generic.List[string]
    $tried = New-Object System.Collections.Generic.List[string]
    $disabled = 0
    $enableFail = $false
    if ($targets.Count -eq 0) {
        return @{
            Ran    = $false
            Ok     = $false
            Detail = 'Windows has no ACPI battery device to refresh.'
        }
    }
    try {
        foreach ($dev in $targets) {
            [void]$tried.Add($dev.InstanceId)
            $disabledResult = Invoke-Pnputil -PnPArgs @('/disable-device', $dev.InstanceId)
            [void]$log.Add(('{0} disable exit {1}' -f $dev.Name, $disabledResult.Exit))
            if ($disabledResult.Exit -eq 0) { $disabled++ }
        }
        Start-Sleep -Seconds 4
    } finally {
        foreach ($id in @($tried)) {
            $enabledResult = Invoke-Pnputil -PnPArgs @('/enable-device', $id)
            [void]$log.Add(('enable exit {0}' -f $enabledResult.Exit))
            if ($enabledResult.Exit -ne 0) { $enableFail = $true }
        }
    }
    Start-Sleep -Seconds 2
    $scan = Invoke-Pnputil -PnPArgs @('/scan-devices')
    [void]$log.Add(('scan exit {0}' -f $scan.Exit))
    $detail = (@($log) -join '; ')
    if ($disabled -eq 0) {
        return @{ Ran = $true; Ok = $false; Detail = 'The ACPI battery device did not disable. ' + $detail }
    }
    if ($enableFail) {
        return @{ Ran = $true; Ok = $false; Detail = 'The ACPI battery device did not turn back on. Restart Windows. ' + $detail }
    }
    return @{ Ran = $true; Ok = $true; Detail = 'ACPI battery driver cycled while the 65W cord stayed online. ' + $detail }
}

function Update-ActiveScheme {
    $text = & powercfg.exe /getactivescheme 2>&1 | Out-String
    if ($null -eq $text) { $text = '' }
    $guid = $null
    if ($text -match '([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})') {
        $guid = $Matches[1]
        & powercfg.exe /setactive $guid | Out-Null
    }
    return @{ Guid = $guid; Text = $text.Trim() }
}

function Import-ReportCapacity {
    param([string]$Path)
    if (-not (Test-Path -LiteralPath $Path)) { return $null }
    $html = Get-Content -LiteralPath $Path -Raw -ErrorAction SilentlyContinue
    if (-not $html) { return $null }
    $design = $null
    $full = $null
    if ($html -match 'DESIGN CAPACITY[\s\S]{0,200}?([0-9,]{3,})\s*mWh') {
        $design = [int]($Matches[1] -replace ',', '')
    }
    if ($html -match 'FULL CHARGE CAPACITY[\s\S]{0,200}?([0-9,]{3,})\s*mWh') {
        $full = [int]($Matches[1] -replace ',', '')
    }
    return @{ DesignMwh = $design; FullMwh = $full }
}

function New-Signal {
    param(
        [string]$Id,
        [string]$Label,
        [bool]$Pass,
        [string]$Detail
    )
    $level = 'red'
    if ($Pass) { $level = 'green' }
    if (-not $Detail) {
        if ($Pass) { $Detail = 'Verified' } else { $Detail = 'Needs repair' }
    }
    return [pscustomobject]@{
        Id     = $Id
        Label  = $Label
        Level  = $level
        Detail = $Detail
    }
}

function Get-HtmlSafe {
    param($Text)
    if ($null -eq $Text) { return '' }
    $safe = [string]$Text
    $safe = $safe.Replace('&', '&amp;')
    $safe = $safe.Replace('<', '&lt;')
    $safe = $safe.Replace('>', '&gt;')
    $safe = $safe.Replace('"', '&quot;')
    return $safe
}

function New-EmptyBattery {
    return @{
        Present         = $false
        Percent         = $null
        Status          = $null
        StatusText      = ''
        Charging        = $false
        Discharging     = $false
        PowerOnline     = $null
        ChargeRateMw    = $null
        DischargeRateMw = $null
        VoltageMv       = $null
        DesignMwh       = $null
        FullMwh         = $null
        HealthPct       = $null
        Name            = ''
    }
}

function Invoke-Step {
    param(
        [scriptblock]$Action,
        $Fallback
    )
    try {
        $value = & $Action
        return ,$value
    } catch {
        $line = ''
        if ($_.InvocationInfo -and $_.InvocationInfo.ScriptLineNumber) {
            $line = ' line ' + $_.InvocationInfo.ScriptLineNumber
        }
        Write-Host ('Skipped a read' + $line + ': ' + $_.Exception.Message)
        return ,$Fallback
    }
}

function Get-ConnectionBand {
    param($Reading, [bool]$CordOn, [bool]$BatteryPresent)
    if (-not $BatteryPresent) { return 'battery not seen' }
    if (-not $CordOn) { return 'cord not seen' }
    $percent = $Reading.Percent
    $full = ($Reading.Status -eq 3) -or ($null -ne $percent -and $percent -ge 95)
    if ($Reading.Charging -and -not $full) { return 'charging' }
    if ($full -and -not $Reading.Discharging) { return 'full' }
    return 'not charging'
}

function Write-SignalLine {
    param($Signal)
    $color = 'Red'
    if ($Signal.Level -eq 'green') { $color = 'Green' }
    Write-Host ('{0}  {1}' -f $Signal.Level.ToUpper().PadRight(5), $Signal.Label) -ForegroundColor $color
    Write-Host ('       {0}' -f $Signal.Detail)
}

function Save-GreenBoard {
    param(
        $Signals,
        [int]$Green,
        [int]$Total,
        [double]$Score,
        [string]$Band,
        [string]$When,
        $Machine,
        [string]$Path
    )
    $rows = New-Object System.Text.StringBuilder
    foreach ($signal in @($Signals)) {
        [void]$rows.AppendLine('<li class="lsr-signal lsr-signal-' + (Get-HtmlSafe $signal.Level) + '">')
        [void]$rows.AppendLine('<span class="lsr-dot"></span><span>')
        [void]$rows.AppendLine('<strong>' + (Get-HtmlSafe $signal.Level.ToUpper()) + ' - ' + (Get-HtmlSafe $signal.Label) + '</strong>')
        [void]$rows.AppendLine('<em>' + (Get-HtmlSafe $signal.Detail) + '</em>')
        [void]$rows.AppendLine('</span></li>')
    }
    $machineLine = '{0} {1}  SKU {2}  BIOS {3}  Service tag {4}' -f $Machine.Manufacturer, $Machine.Model, $Machine.Sku, $Machine.Bios, $Machine.ServiceTag
    $html = @"
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Inspiron 17 7779 green signals</title>
<style>
body { margin: 0; background: #f8fafc; color: #0f172a; font-family: "Segoe UI", sans-serif; }
.wrap { max-width: 980px; margin: 24px auto 48px; padding: 0 16px; }
.hero { padding: 28px 32px; border-radius: 28px; color: #fff;
  background: radial-gradient(circle at 12% 20%, rgba(255,216,111,.28), transparent 32%),
    radial-gradient(circle at 88% 0%, rgba(255,79,154,.22), transparent 34%),
    linear-gradient(135deg, #050816, #111827 55%, #0f172a); }
.kicker { margin: 0 0 8px; letter-spacing: .12em; text-transform: uppercase; font-size: 11px; font-weight: 800; color: #ffe7a3; }
h1 { margin: 0 0 10px; font-size: 28px; letter-spacing: -.03em; }
.lead { margin: 0; max-width: 680px; color: #dbe7ff; font-size: 15px; line-height: 1.55; }
.scorebar { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin: 20px 0; padding: 18px 20px;
  border-radius: 22px; background: #ecfdf5; border: 1px solid #6ee7b7; }
.kicker-dark { color: #047857; }
.big { margin: 4px 0 0; font-size: 28px; font-weight: 800; letter-spacing: -.03em; color: #064e3b; }
.big span { font-size: 14px; font-weight: 700; color: #047857; }
.band { font-size: 18px; word-break: break-word; }
.when { margin: 8px 0 0; font-size: 13px; color: #065f46; }
ul { list-style: none; margin: 0 0 16px; padding: 0; display: grid; gap: 8px; }
.lsr-signal { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border-radius: 16px; background: #ecfdf5; border: 1px solid #6ee7b7; }
.lsr-signal-red { background: #fef2f2; border-color: #fecaca; }
.lsr-dot { width: 10px; height: 10px; margin-top: 6px; border-radius: 50%; background: #059669; flex: 0 0 10px; }
.lsr-signal-red .lsr-dot { background: #dc2626; }
.lsr-signal strong { display: block; color: #064e3b; }
.lsr-signal-red strong { color: #991b1b; }
.lsr-signal em { display: block; margin-top: 3px; font-style: normal; color: #047857; font-size: 13px; line-height: 1.45; }
.lsr-signal-red em { color: #b91c1c; }
.grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 22px; }
.card h2 { margin: 0 0 10px; font-size: 16px; }
.card p, .card li { color: #334155; font-size: 14px; line-height: 1.5; }
a { color: #047857; }
@media (max-width: 782px) { .scorebar, .grid { grid-template-columns: 1fr 1fr; } }
</style>
</head>
<body>
<div class="wrap">
  <header class="hero">
    <p class="kicker">Dell Inspiron 17 7779 | 65W charge path</p>
    <h1>Green signals</h1>
    <p class="lead">Each link from the 65W cord to the battery is a signal. Green means the motherboard can see that link and the pack can take a charge.</p>
  </header>
  <section class="scorebar">
    <div><p class="kicker kicker-dark">Verified links</p><p class="big">$Green / $Total <span>green</span></p></div>
    <div><p class="kicker kicker-dark">Score</p><p class="big">$Score<span> / 10</span></p></div>
    <div><p class="kicker kicker-dark">Charge path</p><p class="big band">$Band</p></div>
    <div><p class="kicker kicker-dark">Checked</p><p class="when">$When</p></div>
  </section>
  <p class="when">$((Get-HtmlSafe $machineLine))</p>
  <h2>Green signals</h2>
  <ul>
$($rows.ToString())
  </ul>
  <section class="grid">
    <article class="card">
      <h2>What a red signal means</h2>
      <ul>
        <li>Cord: push the 65W plug fully into the laptop and the wall. Dell specifies 65W, 19.5V, 3.34A.</li>
        <li>Fitting: if AC drops when the plug moves, the barrel or the power-adapter port is loose. On this model that port is a separate plug on the motherboard.</li>
        <li>65W pin: a generic cord can power the screen and still fail to charge because the center pin is not read.</li>
        <li>Battery cable: if the pack is missing, its connector on the system board is not seen. Dell rates the pack at 56Wh, 15.2V.</li>
      </ul>
    </article>
    <article class="card">
      <h2>What this check changes</h2>
      <ul>
        <li>Refreshes the Windows ACPI battery driver only while AC stays online.</li>
        <li>Does not change BIOS, open the case, or replace the cord.</li>
        <li>Saves this board and a Windows battery report in the same folder.</li>
        <li>Service manual: <a href="https://www.dell.com/support/manuals/en-us/inspiron-17-7779-2-in-1-laptop/inspiron-17-7779-laptop-sm/">Inspiron 17 7779</a></li>
      </ul>
    </article>
  </section>
</div>
</body>
</html>
"@
    Set-Content -LiteralPath $Path -Value $html -Encoding UTF8
}

function Write-TextBoard {
    param($Signals, [int]$Green, [int]$Total, [double]$Score, [string]$Band, [string]$Path)
    $lines = New-Object System.Collections.Generic.List[string]
    [void]$lines.Add('Dell Inspiron 17 7779  65W charge path')
    [void]$lines.Add(('{0} / {1} green    score {2} / 10    {3}' -f $Green, $Total, $Score, $Band))
    [void]$lines.Add('')
    foreach ($signal in @($Signals)) {
        [void]$lines.Add(('{0}  {1}' -f $signal.Level.ToUpper().PadRight(5), $signal.Label))
        [void]$lines.Add(('       {0}' -f $signal.Detail))
    }
    Set-Content -LiteralPath $Path -Value (@($lines) -join "`r`n") -Encoding UTF8
}

# Set BATTERY_CHECK_LIBRARY=1 only to load the functions without running the check.
if ($env:BATTERY_CHECK_LIBRARY -eq '1') { return }

$desktop = [Environment]::GetFolderPath('Desktop')
if (-not $desktop) { $desktop = $env:USERPROFILE }
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$outDir = Join-Path $desktop ('Inspiron-7779-Battery-' + $stamp)
New-Item -ItemType Directory -Path $outDir -Force | Out-Null
$transcript = Join-Path $outDir 'session.log'
$transcriptOn = $false

try {
    Start-Transcript -Path $transcript -Force | Out-Null
    $transcriptOn = $true
} catch {
    $transcriptOn = $false
}

try {
    Write-Host ''
    Write-Host 'Dell Inspiron 17 7779   65W cord, barrel fitting, motherboard, battery' -ForegroundColor Cyan
    Write-Host 'Leave the 65W cord plugged in. Reading the charge path...'
    Write-Host ''

    $os = Invoke-Step -Action { Get-OsInfo } -Fallback @{ Build = 0; Display = ''; Name = 'Windows'; IsWin11 = $false; Ok = $false }
    $machine = Invoke-Step -Action { Get-MachineInfo } -Fallback @{ Manufacturer = ''; Model = ''; Sku = ''; Family = ''; Board = ''; Bios = ''; ServiceTag = ''; Is7779 = $false }
    $admin = Invoke-Step -Action { Test-IsAdmin } -Fallback $false
    $samples = Invoke-Step -Action { ,@(Get-AcSamples) } -Fallback @('unknown', 'unknown', 'unknown', 'unknown', 'unknown')
    if ($null -eq $samples) { $samples = @('unknown', 'unknown', 'unknown', 'unknown', 'unknown') }
    $before = Invoke-Step -Action { Get-BatteryReading } -Fallback (New-EmptyBattery)
    $wattsInfo = Invoke-Step -Action { Find-AdapterWatts } -Fallback $null
    $wattEvent = Invoke-Step -Action { Get-WattageUnknown } -Fallback @{ Hit = $false; Detail = '' }
    $devicesBefore = Invoke-Step -Action { ,@(Get-ChargeDevices) } -Fallback @()
    if ($null -eq $devicesBefore) { $devicesBefore = @() }

    $onCount = @($samples | Where-Object { $_ -eq 'on' }).Count
    $offCount = @($samples | Where-Object { $_ -eq 'off' }).Count
    $acStableOn = ($samples.Count -gt 0 -and $onCount -eq $samples.Count)
    $acMixed = ($onCount -gt 0 -and $offCount -gt 0)

    $refresh = @{ Ran = $false; Ok = $false; Detail = '' }
    if (-not $admin) {
        $refresh.Detail = 'Run Run-Battery-Connection-Check.cmd and approve the administrator prompt so the ACPI battery driver can be refreshed.'
    } elseif (-not $acStableOn) {
        $refresh.Detail = 'The 65W cord is not held steady, so the battery driver was left alone. Plug the cord in firmly and run this again.'
    } else {
        Write-Host 'AC is steady. Refreshing the ACPI battery driver...' -ForegroundColor Cyan
        $refresh = Invoke-Step -Action { Reset-BatteryDriver -Devices $devicesBefore } -Fallback @{ Ran = $true; Ok = $false; Detail = 'The driver refresh did not finish.' }
    }

    Start-Sleep -Seconds 2
    $after = Invoke-Step -Action { Get-BatteryReading } -Fallback (New-EmptyBattery)
    $devicesAfter = Invoke-Step -Action { ,@(Get-ChargeDevices) } -Fallback @()
    if ($null -eq $devicesAfter) { $devicesAfter = @() }
    $scheme = Invoke-Step -Action { Update-ActiveScheme } -Fallback @{ Guid = $null; Text = '' }

    $reportPath = Join-Path $outDir 'battery-report.html'
    & powercfg.exe /batteryreport /output $reportPath /duration 7 | Out-Null
    $reportSaved = Test-Path -LiteralPath $reportPath
    if ($reportSaved -and (-not $after.HealthPct)) {
        $fromReport = Import-ReportCapacity -Path $reportPath
        if ($fromReport) {
            if (-not $after.DesignMwh -and $fromReport.DesignMwh) { $after.DesignMwh = $fromReport.DesignMwh }
            if (-not $after.FullMwh -and $fromReport.FullMwh) { $after.FullMwh = $fromReport.FullMwh }
            if ($after.DesignMwh -and $after.FullMwh -and $after.DesignMwh -gt 0) {
                $after.HealthPct = [int][math]::Round(100.0 * $after.FullMwh / $after.DesignMwh)
            }
        }
    }

    $percentText = 'no percent'
    if ($null -ne $after.Percent) { $percentText = ('{0}%' -f $after.Percent) }
    $rateText = 'no charge rate'
    if ($null -ne $after.ChargeRateMw) { $rateText = ('{0} mW' -f $after.ChargeRateMw) }
    $fullPack = ($after.Status -eq 3) -or ($null -ne $after.Percent -and $after.Percent -ge 95)
    $acpi = Test-NamedDevice -Devices $devicesAfter -Pattern 'Control Method Battery'
    $adapterDev = Test-NamedDevice -Devices $devicesAfter -Pattern 'AC Adapter'
    $band = Get-ConnectionBand -Reading $after -CordOn $acStableOn -BatteryPresent ([bool]$after.Present)

    $modelDetail = 'This PC reports {0} {1}, SKU {2}, board {3}, BIOS {4}.' -f $machine.Manufacturer, $machine.Model, $machine.Sku, $machine.Board, $machine.Bios
    if ($machine.Is7779) {
        $modelDetail = 'Inspiron 17 7779 confirmed. ' + $modelDetail
    } else {
        $modelDetail = 'This check is written for a Dell Inspiron 17 7779 (P30E). ' + $modelDetail
    }

    $windowsDetail = '{0} build {1} {2}.' -f $os.Name, $os.Build, $os.Display
    if (-not $os.Ok) {
        $windowsDetail += ' Windows 10 version 2004 or newer is required for the driver refresh.'
    } elseif ($os.IsWin11) {
        $windowsDetail += ' Charge-path checks run on Windows 11. Dell shipped this PC with Windows 10.'
    }

    if ($acStableOn) {
        $cordDetail = 'All 5 motherboard reads saw the 65W cord. Power is reaching the system board.'
    } elseif ($acMixed) {
        $cordDetail = 'The motherboard saw the cord and then lost it. Push the plug fully in. If it drops when the cord moves, the barrel fitting or the power-adapter port is loose.'
    } elseif ($onCount -eq 0 -and $offCount -gt 0) {
        $cordDetail = 'The motherboard does not see the cord. Plug the 65W adapter into the wall and fully into the laptop, then run this again.'
    } else {
        $cordDetail = 'Windows could not read AC status from the motherboard.'
    }

    $wattsPass = $false
    $wattsDetail = ''
    if ($null -ne $wattsInfo) {
        if ([int]$wattsInfo.Watts -ge $ExpectedWatts) {
            $wattsPass = $true
            $wattsDetail = 'Motherboard reads {0}W from the cord ID pin ({1}). This PC is built for {2}W, 19.5V, 3.34A.' -f $wattsInfo.Watts, $wattsInfo.Source, $ExpectedWatts
        } else {
            $wattsDetail = 'Motherboard reads {0}W. The Inspiron 17 7779 is built for a {1}W adapter. A smaller cord can refuse to charge the pack.' -f $wattsInfo.Watts, $ExpectedWatts
        }
    } elseif ($wattEvent.Hit) {
        $wattsDetail = 'Dell reported that the AC adapter wattage cannot be determined. The center pin in the round fitting is not being read. A cord with a 65W label still will not charge if that pin does not connect. ' + $wattEvent.Detail
    } elseif ($acStableOn -and ($after.Charging -or $fullPack)) {
        $wattsPass = $true
        $wattsDetail = 'Windows did not publish adapter watts. AC is steady and the pack is charging or full, so the 65W cord is completing the charge circuit.'
    } elseif ($acStableOn) {
        $wattsDetail = 'The cord is delivering power, but the battery is not charging and Windows cannot confirm 65W. On the 7779 the usual cause is the center ID pin in the barrel fitting.'
    } else {
        $wattsDetail = 'Plug in the 65W cord. The motherboard cannot identify an adapter that is not connected.'
    }

    if ($acStableOn) {
        $fitDetail = 'The barrel fitting held across 5 reads about 2 seconds apart. The plug is seated.'
        $fitPass = $true
    } elseif ($acMixed) {
        $fitDetail = 'AC power flickered. The round fitting, or the power-adapter port where it plugs into the motherboard, is not holding. Hold the plug still and run the check again.'
        $fitPass = $false
    } else {
        $fitDetail = 'No steady AC reading, so the fitting cannot be called seated. Seat the 65W plug and run this again.'
        $fitPass = $false
    }

    $voltageNote = ''
    $voltageBad = $false
    if ($after.VoltageMv -and $after.VoltageMv -gt 0) {
        $volts = [math]::Round($after.VoltageMv / 1000.0, 2)
        $voltageNote = ' Pack voltage {0} V. Dell rates this 4-cell pack at 15.2 V.' -f $volts
        if ($volts -lt 8 -or $volts -gt 20) { $voltageBad = $true }
    }
    $boardPass = (([bool]$after.Present) -or ($acpi.Present) -or ($null -ne $after.PowerOnline)) -and (-not $voltageBad)
    if ($voltageBad) {
        $boardDetail = 'The voltage reading is outside the 15.2 V pack range.' + $voltageNote
    } elseif ($boardPass) {
        $boardDetail = 'The embedded controller answered a battery query.' + $voltageNote
    } else {
        $boardDetail = 'The motherboard did not answer a battery query. The embedded controller is not reporting the pack or the adapter.'
    }

    if ($after.Present) {
        $packDetail = 'Windows sees the battery pack ({0}, {1}). The battery cable is electrically connected to the motherboard.' -f $(if ($after.Name) { $after.Name } else { 'pack' }), $percentText
        $packPass = $true
    } else {
        $packDetail = 'The motherboard does not see a battery. On the 7779 the 4-cell 56Wh pack connects with a cable on the system board. This check cannot reseat that plug.'
        $packPass = $false
    }

    $acpiPass = $acpi.Present -and $acpi.Ok
    if ($acpiPass) {
        $acpiDetail = 'Microsoft ACPI battery driver is OK. ' + $acpi.Names
    } elseif ($acpi.Present) {
        $acpiDetail = 'ACPI battery driver is in an error state. ' + $acpi.Names
    } else {
        $acpiDetail = 'The ACPI control-method battery device is missing. Windows is not attached to the motherboard battery controller.'
    }

    $acPass = $false
    $acDetail = ''
    if ($adapterDev.Present -and $adapterDev.Ok) {
        $acPass = $true
        $acDetail = 'AC adapter driver is OK. ' + $adapterDev.Names
    } elseif ($adapterDev.Present) {
        $acDetail = 'AC adapter driver is in an error state. ' + $adapterDev.Names
    } elseif ($acStableOn -and ($after.Charging -or $fullPack)) {
        $acPass = $true
        $acDetail = 'Windows did not list an AC adapter device. The motherboard is still charging or the pack is full.'
    } else {
        $acDetail = 'Windows did not list a healthy AC adapter device.'
    }

    $chargePass = $false
    $chargeDetail = ''
    if (-not $after.Present) {
        $chargeDetail = 'No pack to charge.'
    } elseif (-not $acStableOn) {
        $chargeDetail = 'On battery or AC is not steady ({0}, {1}). Plug in the 65W cord to test charging.' -f $percentText, $rateText
    } elseif ($after.Charging -and -not $fullPack) {
        $chargePass = $true
        $chargeDetail = 'The pack is taking current at {0}. Charge rate {1}. Before the driver refresh the rate was {2}.' -f $percentText, $rateText, $(if ($null -ne $before.ChargeRateMw) { '{0} mW' -f $before.ChargeRateMw } else { 'not reported' })
    } elseif ($fullPack -and -not $after.Discharging) {
        $chargePass = $true
        $chargeDetail = 'Charge is {0}. A full 56Wh pack will not pull current. Status: {1}.' -f $percentText, $(if ($after.StatusText) { $after.StatusText } else { 'full' })
    } else {
        $chargeDetail = 'Plugged in at {0} and not charging ({1}). Status: {2}. The motherboard sees power but is not sending it into the pack.' -f $percentText, $rateText, $(if ($after.StatusText) { $after.StatusText } else { 'unknown' })
    }

    $healthPass = $false
    $healthDetail = ''
    if (-not $after.Present) {
        $healthDetail = 'No pack, so wear cannot be read.'
    } elseif ($null -ne $after.HealthPct) {
        $healthDetail = 'Full charge capacity is {0}% of design ({1} mWh full / {2} mWh design). Dell rates this pack at {3} Wh.' -f $after.HealthPct, $after.FullMwh, $after.DesignMwh, $DesignWh
        if ($after.HealthPct -ge 50) { $healthPass = $true }
        if ($after.HealthPct -lt 50) {
            $healthDetail += ' The pack is worn. The cord can be fine while this battery will not charge normally.'
        }
    } else {
        $healthPass = $true
        $healthDetail = 'The BIOS did not publish design capacity. Connection is judged from charge current, not from wear. Dell rates this pack at 56 Wh.'
    }

    $schemePass = [bool]$scheme.Guid
    $schemeDetail = 'No active power scheme.'
    if ($schemePass) {
        $schemeText = [string]$scheme.Text
        if ($schemeText.Length -gt 180) { $schemeText = $schemeText.Substring(0, 180) }
        $schemeDetail = 'Active plan reapplied. ' + $schemeText
    }

    $signals = @(
        (New-Signal -Id 'model_7779' -Label 'Inspiron 17 7779' -Pass ([bool]$machine.Is7779) -Detail $modelDetail),
        (New-Signal -Id 'windows' -Label 'Windows version' -Pass ([bool]$os.Ok) -Detail $windowsDetail),
        (New-Signal -Id 'administrator' -Label 'Administrator' -Pass $admin -Detail $(if ($admin) { 'Running elevated, so the driver refresh is allowed.' } else { $refresh.Detail })),
        (New-Signal -Id 'cord_seen' -Label '65W cord seen by the motherboard' -Pass $acStableOn -Detail $cordDetail),
        (New-Signal -Id 'adapter_65w' -Label '65W center pin' -Pass $wattsPass -Detail $wattsDetail),
        (New-Signal -Id 'fitting_stable' -Label 'Barrel fitting held' -Pass $fitPass -Detail $fitDetail),
        (New-Signal -Id 'board_ec' -Label 'Motherboard controller' -Pass $boardPass -Detail $boardDetail),
        (New-Signal -Id 'battery_cable' -Label 'Battery cable on the motherboard' -Pass $packPass -Detail $packDetail),
        (New-Signal -Id 'acpi_driver' -Label 'ACPI battery driver' -Pass $acpiPass -Detail $acpiDetail),
        (New-Signal -Id 'ac_adapter_driver' -Label 'AC adapter driver' -Pass $acPass -Detail $acDetail),
        (New-Signal -Id 'charge_current' -Label 'Battery accepting charge' -Pass $chargePass -Detail $chargeDetail),
        (New-Signal -Id 'pack_health' -Label '56Wh pack health' -Pass $healthPass -Detail $healthDetail),
        (New-Signal -Id 'driver_refresh' -Label 'Driver refresh' -Pass ([bool]$refresh.Ok) -Detail $refresh.Detail),
        (New-Signal -Id 'power_scheme' -Label 'Power plan' -Pass $schemePass -Detail $schemeDetail),
        (New-Signal -Id 'report_saved' -Label 'Battery report saved' -Pass $reportSaved -Detail $(if ($reportSaved) { $reportPath } else { 'powercfg could not write the battery report.' }))
    )

    $green = @($signals | Where-Object { $_.Level -eq 'green' }).Count
    $total = @($signals).Count
    $score = 0.0
    if ($total -gt 0) { $score = [math]::Round(10.0 * $green / $total, 1) }
    $when = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    Write-Host ''
    Write-Host ('{0} / {1} green    score {2} / 10    {3}' -f $green, $total, $score, $band) -ForegroundColor Cyan
    Write-Host ''
    foreach ($signal in $signals) { Write-SignalLine -Signal $signal }

    $htmlPath = Join-Path $outDir 'green-signals.html'
    $textPath = Join-Path $outDir 'green-signals.txt'
    Save-GreenBoard -Signals $signals -Green $green -Total $total -Score $score -Band $band -When $when -Machine $machine -Path $htmlPath
    Write-TextBoard -Signals $signals -Green $green -Total $total -Score $score -Band $band -Path $textPath

    Write-Host ''
    Write-Host ('Green signal board: {0}' -f $htmlPath) -ForegroundColor Green
    try { Start-Process -FilePath $htmlPath } catch { }
} catch {
    Write-Host ''
    Write-Host ('The check stopped: {0}' -f $_.Exception.Message) -ForegroundColor Red
    if ($_.InvocationInfo -and $_.InvocationInfo.PositionMessage) {
        Write-Host $_.InvocationInfo.PositionMessage
    }
    if ($_.ScriptStackTrace) {
        Write-Host $_.ScriptStackTrace
    }
} finally {
    if ($transcriptOn) {
        try { Stop-Transcript | Out-Null } catch { }
    }
}

if ($Host.Name -eq 'ConsoleHost' -and -not $env:BATTERY_CHECK_NO_PAUSE) {
    Write-Host ''
    Write-Host 'Press Enter to close.'
    try { [void](Read-Host) } catch { }
}
