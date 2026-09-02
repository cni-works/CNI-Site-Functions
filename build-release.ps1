param(
	[switch] $Force
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$pluginFile = Join-Path $projectRoot 'cni-site-functions.php'
$readmeFile = Join-Path $projectRoot 'readme.txt'
$updaterFile = Join-Path $projectRoot 'includes/updater/class-github-release-updater.php'
$releaseDirectory = Join-Path $projectRoot 'release'
$formalSlug = 'cni-site-functions'
$expectedUpdateUri = 'https://github.com/cni-works/CNI-Site-Functions'
$semanticVersionPattern = '^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$'
$runtimeRoots = @(
	'cni-site-functions.php',
	'readme.txt',
	'assets',
	'includes'
)

$pluginContents = Get-Content -Raw -Encoding UTF8 -LiteralPath $pluginFile
$readmeContents = Get-Content -Raw -Encoding UTF8 -LiteralPath $readmeFile
$headerVersionMatch = [regex]::Match($pluginContents, '(?m)^\s*\*\s*Version:\s*([^\s]+)\s*$')
$constantVersionMatch = [regex]::Match($pluginContents, "(?m)^\s*define\(\s*'CNI_SITE_FUNCTIONS_VERSION'\s*,\s*'([^']+)'\s*\);\s*$")
$updateUriMatch = [regex]::Match($pluginContents, '(?m)^\s*\*\s*Update URI:\s*(\S+)\s*$')
$stableTagMatch = [regex]::Match($readmeContents, '(?m)^Stable tag:\s*([^\s]+)\s*$')

if (-not $headerVersionMatch.Success) {
	throw 'Unable to read Plugin Header Version from cni-site-functions.php.'
}
if (-not $constantVersionMatch.Success) {
	throw 'Unable to read CNI_SITE_FUNCTIONS_VERSION from cni-site-functions.php.'
}
if (-not $updateUriMatch.Success) {
	throw 'Unable to read Update URI from cni-site-functions.php.'
}
if (-not $stableTagMatch.Success) {
	throw 'Unable to read Stable tag from readme.txt.'
}
if (-not (Test-Path -LiteralPath $updaterFile -PathType Leaf)) {
	throw 'GitHub updater is missing: includes/updater/class-github-release-updater.php.'
}

$headerVersion = $headerVersionMatch.Groups[1].Value
$constantVersion = $constantVersionMatch.Groups[1].Value
$stableTag = $stableTagMatch.Groups[1].Value
$updateUri = $updateUriMatch.Groups[1].Value

foreach ($versionValue in @($headerVersion, $constantVersion, $stableTag)) {
	if ($versionValue -notmatch $semanticVersionPattern) {
		throw "Version is not valid X.Y.Z: $versionValue"
	}
}

if ($headerVersion -ne $constantVersion -or $headerVersion -ne $stableTag) {
	throw "Version mismatch: Header=$headerVersion, Constant=$constantVersion, StableTag=$stableTag"
}
if ($updateUri -ne $expectedUpdateUri) {
	throw "Update URI does not match the formal repository: $updateUri"
}

$version = $headerVersion
$expectedTag = "v$version"
$zipName = "$formalSlug-$version.zip"
$destinationZip = Join-Path $releaseDirectory $zipName

if ((Test-Path -LiteralPath $destinationZip) -and -not $Force) {
	throw "A ZIP for this Version already exists: $destinationZip`nUse -Force only when replacement is intended."
}

foreach ($runtimeRoot in $runtimeRoots) {
	$sourcePath = Join-Path $projectRoot $runtimeRoot
	if (-not (Test-Path -LiteralPath $sourcePath)) {
		throw "Required Runtime path is missing: $runtimeRoot"
	}
}

$temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("cni-site-functions-release-" + [guid]::NewGuid().ToString('N'))
$packageRoot = Join-Path $temporaryRoot $formalSlug
$candidateZip = Join-Path $temporaryRoot $zipName

try {
	New-Item -ItemType Directory -Path $packageRoot -Force | Out-Null

	foreach ($runtimeRoot in $runtimeRoots) {
		Copy-Item -LiteralPath (Join-Path $projectRoot $runtimeRoot) -Destination $packageRoot -Recurse -Force
	}

	Get-ChildItem -LiteralPath $packageRoot -Recurse -Force -File | Where-Object {
		$relativePath = $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/').Replace('\', '/')
		$relativePath -match '(^|/)(?:desktop\.ini|Thumbs\.db|\.DS_Store)$' -or
		$relativePath -match '(^|/)\.env(?:\.[^/]+)?$' -or
		$relativePath -match '(^|/)(?:node_modules|tests?)(/|$)' -or
		$relativePath -match '\.(?:zip|log|bak)$' -or
		$relativePath.EndsWith('~')
	} | Remove-Item -Force

	Add-Type -AssemblyName System.IO.Compression
	Add-Type -AssemblyName System.IO.Compression.FileSystem

	$zipStream = [System.IO.File]::Open($candidateZip, [System.IO.FileMode]::CreateNew)
	try {
		$zipArchive = [System.IO.Compression.ZipArchive]::new(
			$zipStream,
			[System.IO.Compression.ZipArchiveMode]::Create,
			$false
		)
		try {
			Get-ChildItem -LiteralPath $packageRoot -Recurse -Force -File | ForEach-Object {
				$relativePath = $_.FullName.Substring($packageRoot.Length).TrimStart('\', '/')
				$entryName = "$formalSlug/" + $relativePath.Replace('\', '/')
				[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
					$zipArchive,
					$_.FullName,
					$entryName,
					[System.IO.Compression.CompressionLevel]::Optimal
				) | Out-Null
			}
		} finally {
			if ($null -ne $zipArchive) {
				$zipArchive.Dispose()
			}
		}
	} finally {
		$zipStream.Dispose()
	}

	if (-not (Test-Path -LiteralPath $candidateZip -PathType Leaf) -or (Get-Item -LiteralPath $candidateZip).Length -le 0) {
		throw 'Candidate ZIP was not created or is empty.'
	}

	$archive = [System.IO.Compression.ZipFile]::OpenRead($candidateZip)
	try {
		$rawEntryNames = @($archive.Entries | ForEach-Object { $_.FullName })
		$entryNames = @($rawEntryNames | ForEach-Object { $_.Replace('\', '/') })

		$packagedPluginEntry = $archive.GetEntry("$formalSlug/cni-site-functions.php")
		$packagedReadmeEntry = $archive.GetEntry("$formalSlug/readme.txt")
		$packagedPluginContents = ''
		$packagedReadmeContents = ''

		if ($null -ne $packagedPluginEntry) {
			$reader = [System.IO.StreamReader]::new($packagedPluginEntry.Open(), [System.Text.UTF8Encoding]::new($false), $true)
			try { $packagedPluginContents = $reader.ReadToEnd() } finally { $reader.Dispose() }
		}
		if ($null -ne $packagedReadmeEntry) {
			$reader = [System.IO.StreamReader]::new($packagedReadmeEntry.Open(), [System.Text.UTF8Encoding]::new($false), $true)
			try { $packagedReadmeContents = $reader.ReadToEnd() } finally { $reader.Dispose() }
		}
	} finally {
		$archive.Dispose()
	}

	$errors = [System.Collections.Generic.List[string]]::new()
	if ((Split-Path -Leaf $candidateZip) -ne $zipName) {
		$errors.Add('The ZIP filename is incorrect.')
	}
	if ($entryNames.Count -eq 0) {
		$errors.Add('The ZIP is empty.')
	}
	if (@($rawEntryNames | Where-Object { $_.Contains('\') }).Count -gt 0) {
		$errors.Add('A ZIP entry contains a Windows backslash path separator.')
	}
	if (@($entryNames | Where-Object { -not $_.StartsWith("$formalSlug/") }).Count -gt 0) {
		$errors.Add("The top-level folder is not $formalSlug.")
	}

	$requiredFiles = @(
		"$formalSlug/cni-site-functions.php",
		"$formalSlug/readme.txt",
		"$formalSlug/includes/updater/class-github-release-updater.php"
	)
	foreach ($requiredFile in $requiredFiles) {
		if ($entryNames -notcontains $requiredFile) {
			$errors.Add("Required Runtime file is missing: $requiredFile")
		}
	}

	foreach ($requiredDirectory in @('assets', 'includes')) {
		if (@($entryNames | Where-Object { $_.StartsWith("$formalSlug/$requiredDirectory/") }).Count -eq 0) {
			$errors.Add("Required Runtime directory is empty or missing: $requiredDirectory")
		}
	}

	if (@($entryNames | Where-Object { $_.StartsWith("$formalSlug/$formalSlug/") }).Count -gt 0) {
		$errors.Add("A duplicate $formalSlug/$formalSlug folder was found.")
	}

	$allowedTopLevelNames = @($runtimeRoots)
	foreach ($entryName in $entryNames) {
		$relativeEntry = $entryName.Substring(("$formalSlug/").Length)
		$topLevelName = ($relativeEntry -split '/', 2)[0]
		if ($allowedTopLevelNames -notcontains $topLevelName) {
			$errors.Add("Unexpected top-level Runtime entry: $topLevelName")
		}
	}

	$packagedHeaderVersion = [regex]::Match($packagedPluginContents, '(?m)^\s*\*\s*Version:\s*([^\s]+)\s*$')
	$packagedConstantVersion = [regex]::Match($packagedPluginContents, "(?m)^\s*define\(\s*'CNI_SITE_FUNCTIONS_VERSION'\s*,\s*'([^']+)'\s*\);\s*$")
	$packagedUpdateUri = [regex]::Match($packagedPluginContents, '(?m)^\s*\*\s*Update URI:\s*(\S+)\s*$')
	$packagedStableTag = [regex]::Match($packagedReadmeContents, '(?m)^Stable tag:\s*([^\s]+)\s*$')

	if (-not $packagedHeaderVersion.Success -or $packagedHeaderVersion.Groups[1].Value -ne $version) {
		$errors.Add('The packaged Plugin Header Version does not match the source Version.')
	}
	if (-not $packagedConstantVersion.Success -or $packagedConstantVersion.Groups[1].Value -ne $version) {
		$errors.Add('The packaged CNI_SITE_FUNCTIONS_VERSION does not match the source Version.')
	}
	if (-not $packagedStableTag.Success -or $packagedStableTag.Groups[1].Value -ne $version) {
		$errors.Add('The packaged Stable tag does not match the source Version.')
	}
	if (-not $packagedUpdateUri.Success -or $packagedUpdateUri.Groups[1].Value -ne $expectedUpdateUri) {
		$errors.Add('The packaged Update URI does not match the formal repository.')
	}

	$forbiddenPatterns = @(
		'(^|/)\.git(/|$)',
		'(^|/)\.github(/|$)',
		'(^|/)\.agents(/|$)',
		'(^|/)\.codex(/|$)',
		'(^|/)\.idea(/|$)',
		'(^|/)\.vscode(/|$)',
		'(^|/)AGENTS\.md$',
		'(^|/)PROJECT-BRIEF\.md$',
		'(^|/)README\.md$',
		'(^|/)\.gitignore$',
		'(^|/)\.gitattributes$',
		'(^|/)build-release\.ps1$',
		'(^|/)docs(/|$)',
		'(^|/)release(/|$)',
		'(^|/)仕様書\.txt$',
		'(^|/)package(?:-lock)?\.json$',
		'(^|/)node_modules(/|$)',
		'(^|/)tests?(/|$)',
		'(^|/)\.env(?:\.[^/]+)?$',
		'(^|/)[^/]+\.(?:zip|log|bak)$',
		'(^|/)desktop\.ini$',
		'(^|/)Thumbs\.db$',
		'(^|/)\.DS_Store$',
		'~$'
	)
	foreach ($pattern in $forbiddenPatterns) {
		if (@($entryNames | Where-Object { $_ -match $pattern }).Count -gt 0) {
			$errors.Add("A development-only file was found: $pattern")
		}
	}

	if ($errors.Count -gt 0) {
		Remove-Item -LiteralPath $candidateZip -Force -ErrorAction SilentlyContinue
		throw ("ZIP structure validation failed.`n- " + ($errors -join "`n- "))
	}

	New-Item -ItemType Directory -Path $releaseDirectory -Force | Out-Null
	[System.IO.File]::Copy($candidateZip, $destinationZip, $true)

	Write-Output "ZIP created: $destinationZip"
	Write-Output "Version: $version"
	Write-Output "Expected tag: $expectedTag (not required for this build)"
	Write-Output "Entry count: $($entryNames.Count)"
	Write-Output 'Structure validation: passed'
} finally {
	$resolvedTemporaryRoot = [System.IO.Path]::GetFullPath($temporaryRoot)
	$resolvedSystemTemp = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
	if (
		$resolvedTemporaryRoot.StartsWith($resolvedSystemTemp, [System.StringComparison]::OrdinalIgnoreCase) -and
		(Split-Path -Leaf $resolvedTemporaryRoot).StartsWith('cni-site-functions-release-', [System.StringComparison]::OrdinalIgnoreCase) -and
		(Test-Path -LiteralPath $resolvedTemporaryRoot)
	) {
		Remove-Item -LiteralPath $resolvedTemporaryRoot -Recurse -Force
	}
}
