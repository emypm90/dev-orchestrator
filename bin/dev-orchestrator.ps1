[CmdletBinding()]
param(
    [int]$Port = 8001,
    [switch]$SetupOnly,
    [switch]$NoInstall
)

$ErrorActionPreference = 'Stop'
$appPath = Split-Path -Parent $PSScriptRoot
$phpPath = 'C:\MAMP\bin\php\php8.3.11\php.exe'
$phpIni = Join-Path $appPath 'php.ini'

function Write-Status {
    param([string]$Message)

    Write-Host "[dev-orchestrator] $Message"
}

function Invoke-ExternalCommand {
    param(
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$Description
    )

    Write-Status $Description
    & $FilePath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

function Test-AppKeyPresent {
    param([string]$EnvPath)

    foreach ($line in Get-Content -LiteralPath $EnvPath) {
        if ($line -match '^\s*APP_KEY\s*=\s*(.*)$') {
            $value = $Matches[1].Trim().Trim('"').Trim("'")
            return -not [string]::IsNullOrWhiteSpace($value)
        }
    }

    return $false
}

if ($Port -lt 1 -or $Port -gt 65535) {
    throw 'Port must be between 1 and 65535.'
}

if (-not (Test-Path -LiteralPath $phpPath -PathType Leaf)) {
    throw "MAMP PHP was not found at $phpPath. Install MAMP PHP 8.3.11 or update bin\dev-orchestrator.ps1."
}

if (-not (Test-Path -LiteralPath $phpIni -PathType Leaf)) {
    throw "Project PHP configuration was not found at $phpIni."
}

$env:PHP_INI_SCAN_DIR = ''
$env:PHPRC = $phpIni

Push-Location $appPath
try {
    $requiredExtensions = @('pdo_sqlite', 'sqlite3', 'mbstring', 'openssl', 'fileinfo')
    $loadedExtensions = & $phpPath -c $phpIni -m
    if ($LASTEXITCODE -ne 0) {
        throw "Could not load PHP extensions using $phpIni."
    }

    $missingExtensions = @($requiredExtensions | Where-Object { $loadedExtensions -notcontains $_ })
    if ($missingExtensions.Count -gt 0) {
        throw "Configured PHP is missing required extensions: $($missingExtensions -join ', '). Check $phpIni."
    }

    $envPath = Join-Path $appPath '.env'
    if (-not (Test-Path -LiteralPath $envPath -PathType Leaf)) {
        Copy-Item -LiteralPath (Join-Path $appPath '.env.example') -Destination $envPath
        Write-Status 'Created .env from .env.example.'
    }

    $databasePath = Join-Path $appPath 'database'
    if (-not (Test-Path -LiteralPath $databasePath -PathType Container)) {
        New-Item -ItemType Directory -Path $databasePath | Out-Null
        Write-Status 'Created database directory.'
    }

    $sqlitePath = Join-Path $databasePath 'database.sqlite'
    if (-not (Test-Path -LiteralPath $sqlitePath -PathType Leaf)) {
        New-Item -ItemType File -Path $sqlitePath | Out-Null
        Write-Status 'Created database/database.sqlite.'
    }

    if (-not $NoInstall -and -not (Test-Path -LiteralPath (Join-Path $appPath 'vendor\autoload.php') -PathType Leaf)) {
        $composer = Get-Command composer -ErrorAction SilentlyContinue
        if ($null -eq $composer) {
            throw 'Composer is required because vendor/autoload.php is missing. Install Composer or rerun after dependencies are available.'
        }

        Invoke-ExternalCommand -FilePath $composer.Source -Arguments @('install') -Description 'Installing Composer dependencies'
    }

    if (-not $NoInstall -and (Test-Path -LiteralPath (Join-Path $appPath 'package.json') -PathType Leaf) -and -not (Test-Path -LiteralPath (Join-Path $appPath 'node_modules') -PathType Container)) {
        $npm = Get-Command npm -ErrorAction SilentlyContinue
        if ($null -eq $npm) {
            Write-Warning 'npm was not found. Continuing because the current server-rendered dashboard does not require Vite.'
        } else {
            Invoke-ExternalCommand -FilePath $npm.Source -Arguments @('install') -Description 'Installing npm dependencies'
        }
    }

    if (-not (Test-Path -LiteralPath (Join-Path $appPath 'vendor\autoload.php') -PathType Leaf)) {
        throw 'vendor/autoload.php is missing. Run without -NoInstall after installing Composer dependencies.'
    }

    if (-not (Test-AppKeyPresent -EnvPath $envPath)) {
        Invoke-ExternalCommand -FilePath $phpPath -Arguments @('-c', $phpIni, 'artisan', 'key:generate', '--force') -Description 'Generating application key'
    }

    Invoke-ExternalCommand -FilePath $phpPath -Arguments @('-c', $phpIni, 'artisan', 'migrate', '--force') -Description 'Running database migrations'

    if ($SetupOnly) {
        Write-Status 'Setup complete.'
        return
    }

    $url = "http://127.0.0.1:$Port"
    Write-Status "Starting dashboard at $url"
    Write-Status 'Press Ctrl+C to stop the server.'
    & $phpPath -c $phpIni -S "127.0.0.1:$Port" -t public
} finally {
    Pop-Location
}
