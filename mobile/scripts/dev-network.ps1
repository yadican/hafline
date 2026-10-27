# Windows side of `npm run dev`: lets phones on the current network reach the dev servers.
# Makes the network profile Private, opens the API and Metro ports to the local subnet and adds the
# Metro port proxy into WSL. Settings come from ..\.env.local unless passed as parameters.
#
#   -Check    Only print the resolved IP; exit 0 if the network is ready, 3 if setup is needed.
#   (none)    Apply the setup; asks for admin rights itself.
param(
    [string]$HostIp,
    [int]$MetroPort = 0,
    [int]$ApiPort = 0,
    [switch]$Check,
    [switch]$Elevated
)

$ErrorActionPreference = 'Stop'

if (-not $HostIp) {
    $envFile = Join-Path $PSScriptRoot '..\.env.local'
    $vars = @{}
    if (Test-Path $envFile) {
        foreach ($line in Get-Content $envFile) {
            if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$') { $vars[$Matches[1]] = $Matches[2].Trim('"', "'") }
        }
    }
    $HostIp = $vars['DEV_HOST_IP']
    if (-not $MetroPort -and $vars['METRO_PORT']) { $MetroPort = [int]$vars['METRO_PORT'] }
    if (-not $ApiPort -and $vars['API_PORT']) { $ApiPort = [int]$vars['API_PORT'] }
}
if (-not $HostIp) { $HostIp = 'auto' }
if (-not $MetroPort) { $MetroPort = 8081 }
if (-not $ApiPort) { $ApiPort = 8010 }

if ($HostIp -eq 'auto') {
    $route = Get-NetRoute -DestinationPrefix 0.0.0.0/0 -PolicyStore ActiveStore -ErrorAction SilentlyContinue |
        Sort-Object { $_.RouteMetric + $_.InterfaceMetric } | Select-Object -First 1
    if (-not $route) {
        Write-Host 'No active network connection found (no default route).' -ForegroundColor Red
        exit 1
    }
    $HostIp = (Get-NetIPAddress -InterfaceIndex $route.ifIndex -AddressFamily IPv4 | Select-Object -First 1).IPAddress
}

$address = Get-NetIPAddress -AddressFamily IPv4 -IPAddress $HostIp -ErrorAction SilentlyContinue
if (-not $address) {
    Write-Host "DEV_HOST_IP=$HostIp is not an address of this PC. Current addresses:" -ForegroundColor Red
    Get-NetIPAddress -AddressFamily IPv4 |
        Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } |
        ForEach-Object { Write-Host "  $($_.InterfaceAlias): $($_.IPAddress)" }
    exit 1
}

$rules = @(
    @{ Name = 'Hafline-API-8010'; DisplayName = "Hafline API ($ApiPort)"; Port = $ApiPort },
    @{ Name = 'Hafline-Metro-8081'; DisplayName = "Hafline Expo Metro ($MetroPort)"; Port = $MetroPort }
)

if ($Check) {
    $profileReady = (Get-NetConnectionProfile -InterfaceIndex $address.InterfaceIndex -ErrorAction SilentlyContinue).NetworkCategory -eq 'Private'
    $proxyReady = [bool](netsh interface portproxy show v4tov6 | Select-String "^\s*$([regex]::Escape($HostIp))\s+$MetroPort\s")
    $rulesReady = @($rules | Where-Object {
            $rule = Get-NetFirewallRule -Name $_.Name -ErrorAction SilentlyContinue
            $rule -and (($rule | Get-NetFirewallAddressFilter).RemoteAddress -contains 'LocalSubnet') -and
                (($rule | Get-NetFirewallPortFilter).LocalPort -eq "$($_.Port)")
        }).Count -eq $rules.Count
    Write-Output $HostIp
    if ($profileReady -and $proxyReady -and $rulesReady) { exit 0 } else { exit 3 }
}

$principal = [Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    # Pass the script body so the elevated process does not depend on reading it from \\wsl.localhost.
    $body = Get-Content -Raw $PSCommandPath
    $command = "& { $body } -HostIp '$HostIp' -MetroPort $MetroPort -ApiPort $ApiPort -Elevated"
    $encoded = [Convert]::ToBase64String([Text.Encoding]::Unicode.GetBytes($command))
    $process = Start-Process powershell -Verb RunAs -Wait -PassThru `
        -ArgumentList '-NoProfile', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded
    if ($process.ExitCode -eq 0) {
        Write-Host "Network ready for $HostIp (Metro $MetroPort, API $ApiPort)"
    }
    exit $process.ExitCode
}

try {
    Set-NetConnectionProfile -InterfaceIndex $address.InterfaceIndex -NetworkCategory Private

    foreach ($rule in $rules) {
        if (Get-NetFirewallRule -Name $rule.Name -ErrorAction SilentlyContinue) {
            Set-NetFirewallRule -Name $rule.Name -NewDisplayName $rule.DisplayName -LocalPort $rule.Port `
                -RemoteAddress LocalSubnet -Profile Private
        } else {
            New-NetFirewallRule -Name $rule.Name -DisplayName $rule.DisplayName -Direction Inbound -Protocol TCP `
                -LocalPort $rule.Port -Action Allow -Profile Private -RemoteAddress LocalSubnet | Out-Null
        }
    }

    # WSL's relay listens for Metro on IPv6 ::1 only; forwarding to 127.0.0.1 drops the connection.
    netsh interface portproxy add v4tov6 listenaddress=$HostIp listenport=$MetroPort connectaddress=::1 connectport=$MetroPort | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "netsh portproxy add failed ($LASTEXITCODE)" }
} catch {
    Write-Host $_ -ForegroundColor Red
    if ($Elevated) { Read-Host 'Press Enter to close' }
    exit 1
}

if (-not $Elevated) {
    Write-Host "Network ready for $HostIp (Metro $MetroPort, API $ApiPort)"
}
