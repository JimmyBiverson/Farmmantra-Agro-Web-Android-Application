param([string]$SourcePng, [string]$ApiBase)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$appRoot = Split-Path $PSScriptRoot -Parent
$repoRoot = Split-Path $appRoot -Parent
if ($ApiBase) {
    $settings = Invoke-RestMethod ($ApiBase.TrimEnd('/') + '/settings/public')
    $SourcePng = Join-Path ([System.IO.Path]::GetTempPath()) 'farmmantra-admin-icon.png'
    Invoke-WebRequest $settings.data.app_icon_url -OutFile $SourcePng -UseBasicParsing
}
$source = New-Object System.Drawing.Bitmap 1024,1024
$graphics = [System.Drawing.Graphics]::FromImage($source)
$graphics.SmoothingMode = 'AntiAlias'
$graphics.Clear([System.Drawing.ColorTranslator]::FromHtml('#123D30'))
if ($SourcePng) {
    $uploaded = [System.Drawing.Image]::FromFile((Resolve-Path $SourcePng))
    if ($uploaded.Width -ne $uploaded.Height -or $uploaded.Width -lt 512) { throw 'Use a square PNG of at least 512 pixels.' }
    $graphics.DrawImage($uploaded,0,0,1024,1024)
    $uploaded.Dispose()
} else {
    $graphics.ScaleTransform(10.24,10.24)
    $cream = New-Object System.Drawing.SolidBrush ([System.Drawing.ColorTranslator]::FromHtml('#F7F3DF'))
    $lime = New-Object System.Drawing.SolidBrush ([System.Drawing.ColorTranslator]::FromHtml('#BBDD79'))
    $gold = New-Object System.Drawing.Pen ([System.Drawing.ColorTranslator]::FromHtml('#D9B86C')),3
    $gold.StartCap = 'Round'; $gold.EndCap = 'Round'
    $leaf = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf.AddBezier(49,53,45,32,62,24,75,26)
    $leaf.AddBezier(75,26,76,45,66,54,49,53)
    $leaf.CloseFigure(); $graphics.FillPath($cream,$leaf)
    $leaf2 = New-Object System.Drawing.Drawing2D.GraphicsPath
    $leaf2.AddBezier(48,52,31,52,25,43,27,32)
    $leaf2.AddBezier(27,32,42,31,49,39,48,52)
    $leaf2.CloseFigure(); $graphics.FillPath($lime,$leaf2)
    $stem = New-Object System.Drawing.Pen ([System.Drawing.ColorTranslator]::FromHtml('#F7F3DF')),3.6
    $stem.StartCap='Round'; $stem.EndCap='Round'
    $graphics.DrawBezier($stem,48,63,48,49,55,40,65,34)
    $graphics.DrawBezier($gold,26,68,40,58,60,58,74,68)
    $graphics.DrawBezier($gold,29,76,42,67,58,67,71,76)
    $graphics.DrawBezier($gold,39,82,46,78,54,78,61,82)
    $cream.Dispose(); $lime.Dispose(); $gold.Dispose(); $stem.Dispose(); $leaf.Dispose(); $leaf2.Dispose()
}
$graphics.Dispose()
function Save-Icon([string]$Path,[int]$Size,[double]$Inset=0) {
    [System.IO.Directory]::CreateDirectory((Split-Path $Path -Parent)) | Out-Null
    $bitmap = New-Object System.Drawing.Bitmap $Size,$Size
    $g = [System.Drawing.Graphics]::FromImage($bitmap)
    $g.InterpolationMode='HighQualityBicubic'
    $g.Clear([System.Drawing.ColorTranslator]::FromHtml('#123D30'))
    $offset=[int]($Size*$Inset); $edge=$Size-2*$offset
    $g.DrawImage($source,$offset,$offset,$edge,$edge)
    $bitmap.Save($Path,[System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose(); $bitmap.Dispose()
}
Save-Icon (Join-Path $appRoot 'assets/images/farmmantra-icon.png') 1024
Save-Icon (Join-Path $repoRoot 'Web/public/images/farmmantra-icon.png') 1024
$res = Join-Path $appRoot 'android/app/src/main/res'
foreach ($entry in @{mdpi=48;hdpi=72;xhdpi=96;xxhdpi=144;xxxhdpi=192}.GetEnumerator()) {
    Save-Icon (Join-Path $res "mipmap-$($entry.Key)/ic_launcher.png") $entry.Value
}
Save-Icon (Join-Path $res 'drawable-nodpi/launcher_art.png') 432 0.17
$adaptive = '<adaptive-icon xmlns:android="http://schemas.android.com/apk/res/android"><background android:drawable="@color/launcher_background"/><foreground android:drawable="@drawable/launcher_art"/></adaptive-icon>'
[System.IO.Directory]::CreateDirectory((Join-Path $res 'mipmap-anydpi-v26')) | Out-Null
[System.IO.File]::WriteAllText((Join-Path $res 'mipmap-anydpi-v26/ic_launcher.xml'),$adaptive)
[System.IO.File]::WriteAllText((Join-Path $res 'values/launcher_colors.xml'),'<resources><color name="launcher_background">#123D30</color></resources>')
$source.Dispose()
Write-Output 'Launcher icons generated. Build and distribute a new APK to update installed launcher icons.'
