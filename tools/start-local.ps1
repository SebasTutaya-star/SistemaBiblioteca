param(
    [string]$PhpPath = (Join-Path $env:TEMP 'biblioteca-php-runtime\php.exe'),
    [string]$MariaDbHome = (Join-Path $env:TEMP 'biblioteca-mariadb-runtime\mariadb-11.4.7-winx64'),
    [string]$DataPath = (Join-Path $env:TEMP 'biblioteca-mariadb-runtime\data-ui')
)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
if (!(Test-Path -LiteralPath $PhpPath)) { throw 'Falta PHP. Indique -PhpPath con la ruta a PHP 8.1 o superior.' }
$configFile = Join-Path $projectRoot 'public_html\config\config.php'
if (!(Test-Path -LiteralPath $configFile)) { throw 'Falta config.php local. Consulte docs/REDISENO-STITCH.md.' }
$configText = Get-Content -LiteralPath $configFile -Raw
if ($configText -notmatch 'biblioteca_ui_test' -or $configText -notmatch '127\.0\.0\.1;port=3307') {
    throw 'Este lanzador requiere la configuración local biblioteca_ui_test en 127.0.0.1:3307.'
}
$runtimeLogs = Join-Path $PSScriptRoot '.runtime'
New-Item -ItemType Directory -Force -Path $runtimeLogs | Out-Null
if (!(Get-NetTCPConnection -LocalPort 3307 -State Listen -ErrorAction SilentlyContinue)) {
    $dbExecutable = Join-Path $MariaDbHome 'bin\mariadbd.exe'
    $dbConfig = Join-Path $DataPath 'my.ini'
    if (!(Test-Path -LiteralPath $dbExecutable) -or !(Test-Path -LiteralPath $dbConfig)) { throw 'Falta la instancia MariaDB local de pruebas.' }
    Start-Process -FilePath $dbExecutable -ArgumentList @("--defaults-file=`"$dbConfig`"",'--bind-address=127.0.0.1','--port=3307','--console') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeLogs 'mariadb.stdout.log') -RedirectStandardError (Join-Path $runtimeLogs 'mariadb.stderr.log') | Out-Null
}
if (!(Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue)) {
    $publicRoot = Join-Path $projectRoot 'public_html'
    Start-Process -FilePath $PhpPath -WorkingDirectory $projectRoot -ArgumentList @('-S','127.0.0.1:8080','-t',"`"$publicRoot`"") -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeLogs 'php.stdout.log') -RedirectStandardError (Join-Path $runtimeLogs 'php.stderr.log') | Out-Null
}
Write-Output 'Sistema local: http://127.0.0.1:8080/index.php?p=login'
Write-Output 'Base ficticia aislada: biblioteca_ui_test (puerto 3307).'
