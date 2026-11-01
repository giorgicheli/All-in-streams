[CmdletBinding()]
param()

. (Join-Path $PSScriptRoot 'Local-Runtime.ps1')

$webProcess = Get-OwnedLocalProcess 'web'
if ($null -ne $webProcess) {
    # The direct PHP HTTP server has no artisan child process to clean up.
    Stop-Process -Id $webProcess.Id -ErrorAction Stop
    $webProcess.WaitForExit(5000) | Out-Null
    Write-Host 'Stopped the workspace Laravel server.'
}
$databaseProcess = Get-OwnedLocalProcess 'mysql'
if ($null -ne $databaseProcess) {
    # A graceful shutdown is requested only after verifying the saved process.
    $adminPath = Join-Path (Split-Path $databaseProcess.Path -Parent) 'mysqladmin.exe'
    & $adminPath --no-defaults --protocol=tcp --host=127.0.0.1 "--port=$script:DatabasePort" --user=root --connect-timeout=5 shutdown
    if ($LASTEXITCODE -ne 0) { throw 'Graceful shutdown failed; the database process was left running.' }
    $databaseProcess.WaitForExit(10000) | Out-Null
    if (-not $databaseProcess.HasExited) { throw 'The workspace database is still shutting down. Run this script again shortly.' }
    Write-Host 'Stopped the workspace database. Data remains in .runtime/mysql.'
}
Write-Host 'No XAMPP service or global database was changed.'
