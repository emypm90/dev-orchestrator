$appPath = Split-Path -Parent $PSScriptRoot
$env:PHP_INI_SCAN_DIR = ''
$env:PHPRC = "$appPath\php.ini"
Push-Location $appPath
try {
    & "C:\MAMP\bin\php\php8.3.11\php.exe" -c $env:PHPRC artisan @args
} finally {
    Pop-Location
}
