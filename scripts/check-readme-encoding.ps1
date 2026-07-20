# Fail if README.md contains UTF-8 mojibake / BOM / box-drawing trees.
# ASCII-only source (byte patterns) so this script cannot itself be corrupted.
# Usage: powershell -File scripts/check-readme-encoding.ps1 [paths...]
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$Paths = @('README.md')
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Test-ByteSequence {
    param(
        [byte[]]$Haystack,
        [byte[]]$Needle
    )
    if ($null -eq $Needle -or $Needle.Length -eq 0 -or $Haystack.Length -lt $Needle.Length) {
        return $false
    }
    $limit = $Haystack.Length - $Needle.Length
    for ($i = 0; $i -le $limit; $i++) {
        $ok = $true
        for ($j = 0; $j -lt $Needle.Length; $j++) {
            if ($Haystack[$i + $j] -ne $Needle[$j]) {
                $ok = $false
                break
            }
        }
        if ($ok) { return $true }
    }
    return $false
}

function New-Bytes {
    param([Parameter(Mandatory)][int[]]$Values)
    [byte[]]($Values | ForEach-Object { [byte]$_ })
}

# Mojibake byte sequences (UTF-8 of Latin-1 misreads of original UTF-8).
$badSequences = @(
    (New-Bytes 0xC3,0x83,0xC2,0xA3),
    (New-Bytes 0xC3,0x83,0xC2,0xA7),
    (New-Bytes 0xC3,0x83,0xC2,0xA9),
    (New-Bytes 0xC3,0x83,0xC2,0xA1),
    (New-Bytes 0xC3,0x83,0xC2,0xB3),
    (New-Bytes 0xC3,0x83,0xC2,0xAD),
    (New-Bytes 0xC3,0x83,0xC2,0xBA),
    (New-Bytes 0xC3,0x83,0xC2,0xAA),
    (New-Bytes 0xC3,0x83,0xC2,0xB5),
    (New-Bytes 0xC3,0x83,0xC2,0xA0),
    (New-Bytes 0xC3,0x82,0xC2,0xAB),
    (New-Bytes 0xC3,0x82,0xC2,0xBB),
    (New-Bytes 0xC3,0xA2,0xE2,0x82,0xAC),
    (New-Bytes 0xEF,0xBF,0xBD)
)

$fail = $false
foreach ($rel in $Paths) {
    $path = Join-Path $root $rel
    if (-not (Test-Path -LiteralPath $path)) {
        Write-Host "ERROR: missing $rel" -ForegroundColor Red
        $fail = $true
        continue
    }

    $bytes = [System.IO.File]::ReadAllBytes($path)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        Write-Host "ERROR: $rel has UTF-8 BOM (save as UTF-8 without BOM)" -ForegroundColor Red
        $fail = $true
    }

    foreach ($seq in $badSequences) {
        if (Test-ByteSequence -Haystack $bytes -Needle $seq) {
            Write-Host "ERROR: $rel contains mojibake/corruption. Re-save as UTF-8; use ASCII tree (|--, \--)." -ForegroundColor Red
            $fail = $true
            break
        }
    }

    if ((Split-Path -Leaf $rel) -eq 'README.md') {
        $box = New-Bytes 0xE2,0x94
        if (Test-ByteSequence -Haystack $bytes -Needle $box) {
            Write-Host "ERROR: $rel uses Unicode box-drawing. Use ASCII tree: |-- | \--" -ForegroundColor Red
            $fail = $true
        }
    }
}

if ($fail) { exit 1 }
Write-Host "README encoding OK: $($Paths -join ' ')"
