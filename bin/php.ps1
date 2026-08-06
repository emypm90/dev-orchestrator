$env:PHP_INI_SCAN_DIR = ''
$env:PHPRC = "$PSScriptRoot\..\php.ini"
& "C:\MAMP\bin\php\php8.3.11\php.exe" -c $env:PHPRC @args
