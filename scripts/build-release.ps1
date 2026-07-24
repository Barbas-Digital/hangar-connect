# Builds a Linux-compatible WordPress zip (forward slashes, real folders).
param(
    [string]$OutDir = $env:TEMP
)

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$Slug = 'hangar-connect'
$Staging = Join-Path $env:TEMP "$Slug-build"
$Target = Join-Path $Staging $Slug
$ZipPath = Join-Path $OutDir "$Slug.zip"

Remove-Item $Staging -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $Target -Force | Out-Null
robocopy $Root $Target /E /XD .git .github scripts /NFL /NDL /NJH /NJS | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

Remove-Item $ZipPath -Force -ErrorAction SilentlyContinue
Push-Location $Staging
try {
    tar -a -c -f $ZipPath $Slug
} finally {
    Pop-Location
}

$listing = tar -tf $ZipPath
$first = $listing | Select-Object -First 1
if ($first -notlike "$Slug/*" -and $first -ne "$Slug/") {
    throw "Invalid zip root: '$first' (expected '$Slug/')"
}
Write-Host "Created: $ZipPath"
