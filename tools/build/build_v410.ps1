$repo = 'C:\sites\metalka\sshwork\mytax-repo'
$src = Join-Path $repo 'extension\mytax'
$zipPath = Join-Path $repo 'releases\mytax.ocmod.zip'

Remove-Item $zipPath -ErrorAction SilentlyContinue

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$fs = [System.IO.File]::Create($zipPath)
$zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create)

$entries = @(
    'install.json',
    'install.php',
    'README.txt',
    'admin/controller/module/mytax.php',
    'admin/language/ru-ru/module/mytax.php',
    'admin/model/module/mytax.php',
    'admin/view/template/module/mytax.twig',
    'catalog/controller/module/mytax.php',
    'catalog/language/ru-ru/module/mytax.php',
    'catalog/model/checkout/mytax.php',
    'catalog/model/checkout/phpqrcode.php',
    'ocmod/mytax_fix_vendor_hang.ocmod.xml'
)

foreach ($e in $entries) {
    $full = Join-Path $src ($e -replace '/', '\')
    if (-not (Test-Path $full)) { Write-Host "MISSING: $full"; continue }
    $entry = $zip.CreateEntry($e)
    $estream = $entry.Open()
    $bytes = [System.IO.File]::ReadAllBytes($full)
    $estream.Write($bytes, 0, $bytes.Length)
    $estream.Close()
    Write-Host "added: $e"
}

$zip.Dispose()
$fs.Close()

Get-Item $zipPath | Select-Object Name, Length
