# Builds production frontend assets and deploys them to the server,
# replacing the remote public/build folder entirely (avoids the scp -r
# "nests inside existing folder" trap).

$sshKey = "$env:USERPROFILE\.ssh\neblink_key"
$remoteHost = "pebe6530@pebe6530.odns.fr"
$remotePath = "~/Sites/neblink.fr/public/build"

Write-Host "==> Building production assets (npm run build)..." -ForegroundColor Cyan
npm run build
if ($LASTEXITCODE -ne 0) {
    Write-Host "Build failed, aborting deploy." -ForegroundColor Red
    exit 1
}

Write-Host "==> Removing old build folder on the server..." -ForegroundColor Cyan
ssh -i $sshKey $remoteHost "rm -rf $remotePath"

Write-Host "==> Uploading new build..." -ForegroundColor Cyan
scp -i $sshKey -r public/build "${remoteHost}:$remotePath"

Write-Host "==> Done." -ForegroundColor Green
