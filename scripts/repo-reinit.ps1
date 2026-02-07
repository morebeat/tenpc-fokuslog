param(
    [Parameter(Mandatory=$true)][string]$Target,
    [string]$Remote,
    [string]$Source = (Get-Location).Path,
    [string]$Branch = "main",
    [switch]$NoCommit,
    [switch]$Force,
    [string[]]$ExtraExclude = @()
)

function Write-Info($msg) { Write-Host "[INFO] $msg" -ForegroundColor Cyan }
function Write-ErrorAndExit($msg) { Write-Host "[ERROR] $msg" -ForegroundColor Red; exit 1 }

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    Write-ErrorAndExit "git not found in PATH"
}

if (Test-Path $Target) {
    if ($Force) {
        Remove-Item -Recurse -Force $Target
    } else {
        Write-ErrorAndExit "Target already exists. Use -Force to overwrite."
    }
}

$defaultExcludeDirs = @(".git","logs","backups","cache","vendor/cache")
$defaultExcludeFiles = @(".env",".env.*","*.log","*.bak","*.tmp")
$excludeDirs = $defaultExcludeDirs + $ExtraExclude

Write-Info "Copying sanitized files to $Target"
$excludeDirArgs = @()
foreach ($dir in $excludeDirs) { $excludeDirArgs += @('/XD', (Join-Path $Source $dir)) }
$excludeFileArgs = @()
foreach ($file in $defaultExcludeFiles) { $excludeFileArgs += @('/XF', $file) }

$robocopyArgs = @($Source, $Target, '/MIR', '/NFL', '/NDL', '/NJH', '/NJS', '/nc', '/ns', '/np') + $excludeDirArgs + $excludeFileArgs
$robocopyResult = Start-Process -FilePath robocopy.exe -ArgumentList $robocopyArgs -Wait -PassThru
if ($robocopyResult.ExitCode -ge 8) {
    Write-ErrorAndExit "Robocopy failed (code $($robocopyResult.ExitCode))"
}

Write-Info "Removing residual secrets (.env, .git)"
Get-ChildItem -Path $Target -Include '.env*','*.pem','*.key' -Recurse -ErrorAction SilentlyContinue | Remove-Item -Force
if (Test-Path (Join-Path $Target '.git')) { Remove-Item -Recurse -Force (Join-Path $Target '.git') }

pushd $Target

& git init --initial-branch=$Branch | Out-Null
if (-not $NoCommit) {
    & git add . | Out-Null
    & git commit -m "Initial sanitized import" | Out-Null
}
if ($Remote) {
    & git remote add origin $Remote
    Write-Info "Configured origin -> $Remote"
}

Write-Info "Sanitized repository ready at $Target"
popd > $null
