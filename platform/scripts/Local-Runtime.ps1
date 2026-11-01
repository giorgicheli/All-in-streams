Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$script:ProjectDirectory = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$script:WorkspaceDirectory = [IO.Path]::GetFullPath((Join-Path $script:ProjectDirectory '..'))
$script:RuntimeDirectory = [IO.Path]::GetFullPath((Join-Path $script:WorkspaceDirectory '.runtime'))
$script:MySqlDirectory = Join-Path $script:RuntimeDirectory 'mysql'
$script:DatabasePort = 3307
$script:WebPort = 8000

function Assert-LocalRuntimePath {
    param([Parameter(Mandatory)][string] $Path)
    $absolutePath = [IO.Path]::GetFullPath($Path)
    $allowedPrefix = $script:RuntimeDirectory.TrimEnd('\') + '\'
    if ($absolutePath -ne $script:RuntimeDirectory -and -not $absolutePath.StartsWith($allowedPrefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Runtime path is outside the workspace runtime directory: $absolutePath"
    }
    return $absolutePath
}

function Get-LocalProcessRecord {
    param([Parameter(Mandatory)][string] $Name)
    $recordPath = Assert-LocalRuntimePath (Join-Path $script:RuntimeDirectory "$Name.process.json")
    if (-not (Test-Path -LiteralPath $recordPath)) { return $null }
    return Get-Content -LiteralPath $recordPath -Raw | ConvertFrom-Json
}

function Get-OwnedLocalProcess {
    param([Parameter(Mandatory)][string] $Name)
    $record = Get-LocalProcessRecord $Name
    if ($null -eq $record) { return $null }
    $ownedProcess = Get-Process -Id $record.ProcessId -ErrorAction SilentlyContinue
    if ($null -eq $ownedProcess) { return $null }
    $actualStart = $ownedProcess.StartTime.ToUniversalTime()
    $recordedStart = [DateTime]::Parse($record.StartedAt).ToUniversalTime()
    if ([Math]::Abs(($actualStart - $recordedStart).TotalSeconds) -gt 2) {
        throw "Process ID $($record.ProcessId) was reused; refusing to control it."
    }
    if (-not [string]::Equals($ownedProcess.Path, $record.Executable, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Process executable no longer matches the saved $Name runtime."
    }
    $expectedMarker = if ($Name -eq 'mysql') { Join-Path $script:RuntimeDirectory 'mysql.cnf' } else { Join-Path $script:ProjectDirectory 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php' }
    if (-not [string]::Equals($record.CommandMarker, $expectedMarker, [StringComparison]::OrdinalIgnoreCase)) {
        throw "Saved process does not belong to this workspace's $Name runtime."
    }
    return $ownedProcess
}

function Save-LocalProcessRecord {
    param([Parameter(Mandatory)] $Process, [Parameter(Mandatory)][string] $Name, [Parameter(Mandatory)][string] $CommandMarker)
    $recordPath = Assert-LocalRuntimePath (Join-Path $script:RuntimeDirectory "$Name.process.json")
    @{
        ProcessId = $Process.Id
        StartedAt = $Process.StartTime.ToUniversalTime().ToString('o')
        Executable = $Process.Path
        CommandMarker = $CommandMarker
    } | ConvertTo-Json | Set-Content -LiteralPath $recordPath -Encoding UTF8
}

function Test-LocalPort {
    param([Parameter(Mandatory)][int] $Port)
    $tcpClient = New-Object Net.Sockets.TcpClient
    try {
        $connection = $tcpClient.ConnectAsync('127.0.0.1', $Port)
        if (-not $connection.Wait(250)) { return $false }
        return $tcpClient.Connected
    } catch { return $false } finally { $tcpClient.Dispose() }
}

function Wait-LocalPort {
    param([Parameter(Mandatory)][int] $Port, [Parameter(Mandatory)][int] $ProcessId)
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($null -eq (Get-Process -Id $ProcessId -ErrorAction SilentlyContinue)) {
            throw "Local process $ProcessId exited. Inspect logs in $script:RuntimeDirectory."
        }
        if (Test-LocalPort $Port) { return }
        Start-Sleep -Milliseconds 500
    }
    throw "Local port $Port did not become ready. Inspect logs in $script:RuntimeDirectory."
}
