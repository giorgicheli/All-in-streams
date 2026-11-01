[CmdletBinding()]
param(
    [switch] $DatabaseOnly,
    [string] $MySqlHome = 'C:\xampp\mysql',
    [string] $PhpPath = ''
)

. (Join-Path $PSScriptRoot 'Local-Runtime.ps1')

$mysqlServer = Join-Path $MySqlHome 'bin\mysqld.exe'
$mysqlInstaller = Join-Path $MySqlHome 'bin\mysql_install_db.exe'
$mysqlClient = Join-Path $MySqlHome 'bin\mysql.exe'
foreach ($requiredBinary in @($mysqlServer, $mysqlInstaller, $mysqlClient)) {
    if (-not (Test-Path -LiteralPath $requiredBinary -PathType Leaf)) {
        throw "MariaDB binary missing: $requiredBinary. Pass -MySqlHome with your MariaDB installation directory."
    }
}
New-Item -ItemType Directory -Path (Assert-LocalRuntimePath $script:RuntimeDirectory) -Force | Out-Null
$databaseProcess = Get-OwnedLocalProcess 'mysql'

if ($null -eq $databaseProcess) {
    if (Test-LocalPort $script:DatabasePort) { throw 'Port 3307 is already in use by another process. No existing database was changed.' }
    if (-not (Test-Path -LiteralPath (Join-Path $script:MySqlDirectory 'mysql') -PathType Container)) {
        if ((Test-Path -LiteralPath $script:MySqlDirectory) -and @(Get-ChildItem -LiteralPath $script:MySqlDirectory -Force).Count -gt 0) {
            throw 'An incomplete database directory exists in .runtime/mysql. Inspect it before initializing again.'
        }
        Write-Host 'Initializing an isolated database in workspace/.runtime/mysql...'
        Push-Location $script:RuntimeDirectory
        try {
            & $mysqlInstaller "--datadir=$script:MySqlDirectory" "--port=$script:DatabasePort"
            if ($LASTEXITCODE -ne 0) { throw 'MariaDB initialization failed.' }
        } finally { Pop-Location }
    }

    $databaseConfig = Assert-LocalRuntimePath (Join-Path $script:RuntimeDirectory 'mysql.cnf')
    $basePath = [IO.Path]::GetFullPath($MySqlHome).Replace('\', '/')
    $dataPath = $script:MySqlDirectory.Replace('\', '/')
    $runtimePath = $script:RuntimeDirectory.Replace('\', '/')
    @"
[mysqld]
basedir="$basePath"
datadir="$dataPath"
port=$script:DatabasePort
bind-address=127.0.0.1
skip-name-resolve
pid-file="$runtimePath/mysql.pid"
log-error="$runtimePath/mysql-error.log"
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
innodb-buffer-pool-size=64M
max-connections=40
"@ | Set-Content -LiteralPath $databaseConfig -Encoding ASCII

    $databaseProcess = Start-Process -FilePath $mysqlServer -ArgumentList ('"--defaults-file={0}" --console' -f $databaseConfig) -WorkingDirectory $script:RuntimeDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $script:RuntimeDirectory 'mysql.stdout.log') -RedirectStandardError (Join-Path $script:RuntimeDirectory 'mysql.stderr.log')
    Save-LocalProcessRecord -Process $databaseProcess -Name 'mysql' -CommandMarker $databaseConfig
    Wait-LocalPort -Port $script:DatabasePort -ProcessId $databaseProcess.Id
}

# These credentials are for this loopback-only development instance.
# SQL is supplied on stdin so it does not appear in process arguments.
$setupSql = @'
CREATE DATABASE IF NOT EXISTS all_in_streams CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'stream_app'@'127.0.0.1' IDENTIFIED BY 'StreamLocal!2026';
ALTER USER 'stream_app'@'127.0.0.1' IDENTIFIED BY 'StreamLocal!2026';
GRANT ALL PRIVILEGES ON all_in_streams.* TO 'stream_app'@'127.0.0.1';
CREATE USER IF NOT EXISTS 'stream_app'@'localhost' IDENTIFIED BY 'StreamLocal!2026';
ALTER USER 'stream_app'@'localhost' IDENTIFIED BY 'StreamLocal!2026';
GRANT ALL PRIVILEGES ON all_in_streams.* TO 'stream_app'@'localhost';
FLUSH PRIVILEGES;
'@
$setupSql | & $mysqlClient --no-defaults --protocol=tcp --host=127.0.0.1 "--port=$script:DatabasePort" --user=root --connect-timeout=5
if ($LASTEXITCODE -ne 0) { throw 'Local database/user setup failed.' }
Write-Host 'Local database ready: 127.0.0.1:3307 / all_in_streams'
if ($DatabaseOnly) { return }

if (-not (Test-Path -LiteralPath (Join-Path $script:ProjectDirectory '.env'))) { throw 'Create platform/.env from .env.example and generate APP_KEY first. See the workspace README.' }
if (-not (Test-Path -LiteralPath (Join-Path $script:ProjectDirectory 'vendor/autoload.php'))) { throw 'Run composer install in platform first.' }
if ([string]::IsNullOrWhiteSpace($PhpPath)) { $PhpPath = (Get-Command php -ErrorAction Stop).Source }
$webProcess = Get-OwnedLocalProcess 'web'
if ($null -eq $webProcess) {
    if (Test-LocalPort $script:WebPort) { throw 'Port 8000 is already in use by another process.' }
    $publicPath = Join-Path $script:ProjectDirectory 'public'
    $routerPath = Join-Path $script:ProjectDirectory 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
    # Start one PHP process directly. artisan serve creates a child that does
    # not inherit -d flags; Filament requires intl in the actual HTTP process.
    # Laravel's router resolves index.php from cwd, so cwd must be public.
    $webArguments = '-d extension=intl -S 127.0.0.1:8000 -t "{0}" "{1}"' -f $publicPath, $routerPath
    $webProcess = Start-Process -FilePath $PhpPath -ArgumentList $webArguments -WorkingDirectory $publicPath -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $script:RuntimeDirectory 'web.stdout.log') -RedirectStandardError (Join-Path $script:RuntimeDirectory 'web.stderr.log')
    Save-LocalProcessRecord -Process $webProcess -Name 'web' -CommandMarker $routerPath
    Wait-LocalPort -Port $script:WebPort -ProcessId $webProcess.Id
}
Write-Host 'All In Streams: http://127.0.0.1:8000'
Write-Host 'Stop this workspace runtime with platform/scripts/Stop-Local.ps1.'
