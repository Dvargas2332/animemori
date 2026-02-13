param(
  [string]$OutDir = ".",
  [string]$VersionSuffix = ""
)

$ErrorActionPreference = "Stop"

function Resolve-OutPath([string]$name) {
  $file = $name
  if ($VersionSuffix -ne "") { $file = $file.Replace(".zip", "-$VersionSuffix.zip") }
  return (Join-Path $OutDir $file)
}

# We avoid PowerShell's Compress-Archive for Linux deploys; it can lead to backslashes
# being treated as literal characters in filenames when unzipped on Linux.
# Using tar -a produces a .zip with normalized '/' paths.

$stageRoot = Join-Path ".tmp" "release-stage"
if (Test-Path $stageRoot) { Remove-Item -Recurse -Force $stageRoot }
New-Item -ItemType Directory -Path $stageRoot | Out-Null

# Plugin: animemori-core.zip (root folder animemori-core/)
$pluginStage = Join-Path $stageRoot "animemori-core"
New-Item -ItemType Directory -Path $pluginStage | Out-Null
Copy-Item -Force "animemori-core.php" $pluginStage
Copy-Item -Force "README.md" $pluginStage
Copy-Item -Recurse -Force "assets" (Join-Path $pluginStage "assets")
Copy-Item -Recurse -Force "includes" (Join-Path $pluginStage "includes")
Copy-Item -Recurse -Force "templates" (Join-Path $pluginStage "templates")

$pluginZip = Resolve-OutPath "animemori-core.zip"
if (Test-Path $pluginZip) { Remove-Item -Force $pluginZip }
tar -a -cf $pluginZip -C $stageRoot "animemori-core"

# Theme: animemori-theme.zip (root folder animemori-theme/)
$themeStageParent = Join-Path $stageRoot "animemori-theme"
New-Item -ItemType Directory -Path $themeStageParent | Out-Null
Copy-Item -Recurse -Force "animemori\\*" $themeStageParent

$themeZip = Resolve-OutPath "animemori-theme.zip"
if (Test-Path $themeZip) { Remove-Item -Force $themeZip }
tar -a -cf $themeZip -C $stageRoot "animemori-theme"

Write-Output "Built:"
Write-Output " - $pluginZip"
Write-Output " - $themeZip"

