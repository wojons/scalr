
$debugPreference = "continue"
$errorActionPreference = "stop"

$repoUrl = "{{winRepoUrl}}"
$installDir = "C:\opt\scalarizr"
$installDirOld = "$($env:PROGRAMFILES)\Scalarizr"
$servicesToOperate = @("ScalrUpdClient", "Scalarizr")

$useNewtonJsonParser = $PSVersionTable.PSVersion.Major -le 2
$jsonParserUrl = "{{jsonParserUrl}}"


function log {
param (
    $message
)
    write-debug  "$(get-date -format s) -  $message"
}


function tmpName {
param (
    $suffix
)
    return [system.guid]::newGuid().toString() + $suffix
}


function downloadFile {
param (
    $url,
    $fileName
)
    if (!$fileName) {
        $fileName = tmpName -suffix $([system.io.path]::getExtension($url))
    }
    $wc = new-object system.net.WebClient
    $dst = [system.io.path]::getTempPath() + $fileName
    log "Downloading $url to $dst"
    $wc.downloadFile($url, $dst)
    return $dst
}

function installJsonParser {
    $jsonParserPath = downloadFile $jsonParserUrl "Newtonsoft.Json.dll"
    [Reflection.Assembly]::LoadFile($jsonParserPath)
}

function getLatestPackageFromJson {
param(
    $metadata_raw
)
    $result = $false
    if ($useNewtonJsonParser) {
        $metadata = [Newtonsoft.Json.Linq.JObject]::Parse($metadata_raw)
        $packages = @()
        for($i = 0; $i -lt $metadata["packages"].Count; $i++) {
            $packages += $metadata["packages"][$i]["path"].Value
        }
        [array]::sort($packages)
        $result = $packages[-1]
    }
    else {
        $metadata = convertfrom-json $metadata_raw
        $packages = $metadata.packages | sort-object path -descending
        $result = $packages[0].path
    }
    log "Detected v2 (json) repo"
    return $result
}

function getLatestPackage {
param (
    $url
)
    $subdir = ""
    $index_path = "index.json"
    $response = $null

    try {
        log "Trying to get repo metadata from $url$index_path"
        $metadata_raw = (new-object net.webclient).DownloadString("$url$index_path")
    }
    catch [System.Net.WebException] {
        log "Failed"
        $subdir = "x86_64"
        $index_path = "$subdir/index"
        log "Trying to get repo metadata from $url$index_path"
        $metadata_raw = (new-object net.webclient).DownloadString("$url$index_path")
    }

    log "Repo metadata gathered"
    log "Raw metadata: $metadata_raw"
    try {
        log "Trying to decode raw metadata"
        $metadata_raw = [System.Text.Encoding]::ASCII.GetString($metadata_raw)
    }
    catch {
        log "Decoding is unnecessary"
    }

    try {
        return (getLatestPackageFromJson $metadata_raw)
    }
    catch [System.ArgumentException],
        [System.Management.Automation.MethodInvocationException] {
        log "Detected v1 repo"
        return "$subdir/" + $metadata_raw.Split()[1]
    }
}


function runInstaller {
param (
    $fileName
)
    log "Starting installer"
    $proc = ''
    if ($fileName.EndsWith('.exe')) {
        $proc = start-process -wait -noNewWindow $fileName /S
    }
    elseif ($fileName.EndsWith('.msi')) {
        $arguments = "/qn /norestart /i $fileName"
        $proc = start-process -wait -noNewWindow msiexec $arguments
    }
    else {
        throw "Unknown installer extension"
    }
    if ($proc.exitCode) {
        throw "Installer $(split-path -leaf $fileName) exited with code: $($proc.ExitCode)"
    }
    log "Installer completed"
    sleep 2  # Give them time to think
}


function tryScalarizr {
    try {
        start-process -wait -noNewWindow "Scalarizr" "/v"
    }
    catch {
        return $false
    }
    return $true
}


function main {
    if (!$repoUrl) {
        log "Unknown repository"
        exit 1
    }
    if (!$repoUrl.EndsWith("/")) {
        $repoUrl = $repoUrl + "/"
    }
    if ($useNewtonJsonParser) {
        installJsonParser
    }
    if (-not (Test-Path -Path "hklm:\SOFTWARE\Wow6432Node\Microsoft\VisualStudio\*\VC\VCRedist")) {
        $vcredistUrl = "http://download.microsoft.com/download/5/D/8/5D8C65CB-C849-4025-8E95-C3966CAFD8AE/vcredist_x64.exe"
        $vcredistPath = downloadFile $vcredistUrl "vcredist.exe"
        $arguments = "/qn /norestart"
        start-process -wait -noNewWindow $vcredistPath $arguments
    }

    log "Using repository $repoUrl"
    $latestPkg = getLatestPackage $repoUrl
    log "Latest package is $latestPkg"
    $url = $repoUrl + $latestPkg
    try {
        $packageFile = downloadFile $url $latestPkg.Split("/")[-1]
        if (-not $packageFile) {
            throw "Download installer failed"
        }
        runInstaller $packageFile
        if (-not (test-path $installDir) -and -not (test-path $installDirOld)) {
            throw "Installer completed without installing new files"
        }
    }
    finally {
        remove-Item $packageFile
    }
}

main -errorAction continue 2>&1