<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once '/var/www/src/config.php';
require_once '/var/www/src/utils.php';
require_once '/var/www/src/security.php';
require_once '/var/www/src/storage.php';
require_once '/var/www/src/db.php';

foreach (['repo', 'arch', 'packageName', 'packageVersion', 'packageRelease', 'username', 'token'] as $f) {
    if (!isset($_POST[$f])) jsonError("Missing POST field: $f");
}

$pdo = getPdo();
$ip = getIpAddress();

$repo = $_POST['repo'];
$arch = $_POST['arch'];
$packageName = $_POST['packageName'];
$packageVersion = $_POST['packageVersion'];
$packageRelease = $_POST['packageRelease'];
$username = $_POST['username'];
$token = $_POST['token'];

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    jsonError("File upload error");
}

$user = getUserByUsername($pdo, $username);
if (!$user) {
    jsonError("Invalid username or token", 401);
}

$uploadToken = getUploadTokenByUser($pdo, $user['id']);
if (!$uploadToken || !verifyToken($token, $uploadToken['token_hash'])) {
    jsonError("Invalid username or token", 401);
}

$keyFp = $uploadToken['signing_key_fingerprint'];

$fileTmpPath = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];

$expectedFileName = sprintf("%s-%s-%s-%s.pkg.tar.zst", $packageName, $packageVersion, $packageRelease, $arch);
if ($fileName !== $expectedFileName) jsonError("Filename does not match expected: $expectedFileName != $fileName");
if (!str_ends_with($fileName, '.pkg.tar.zst')) jsonError("File must have .pkg.tar.zst extension");

$repoId = getRepoId($pdo, $repo);
$archId = getArchId($pdo, $arch);
$packageId = getPackageId($pdo, $packageName);

$stagingPath = getStagingPath($STAGING_BASE, $repo, $arch, $fileName);
$stagingBase = dirname($stagingPath);
ensureDir($stagingBase, 0700);

$finalBase = getFinalDir($MIRROR_BASE, $repo, $arch);
ensureDir($finalBase);

// Move uploaded file to a secure staging location
$tmpUploaded = "$stagingBase/_upload_" . bin2hex(random_bytes(8));
if (!move_uploaded_file($fileTmpPath, $tmpUploaded)) {
    jsonError("Failed to store uploaded file", 500);
}

// Validate package contents: ensure PKGINFO exists and matches expected metadata
exec('bsdtar -xO -f ' . escapeshellarg($tmpUploaded) . ' .PKGINFO 2> /dev/null', $bsdtarOut, $bsdtarExitCode);
if ($bsdtarExitCode !== 0) {
    jsonError("Failed to extract .PKGINFO from package; invalid package file", 400);
}

$pkginfo = implode("\n", $bsdtarOut);

foreach ([
    "pkgname = {$packageName}",
    "pkgver = {$packageVersion}-{$packageRelease}",
    "arch = {$arch}"
] as $f) {
    if (strpos($pkginfo, $f) === false) {
        jsonError('PKGINFO pkgname mismatch: invalid ' . implode(' = ', $f)[0], 400);
    }
}

// Compute checksum and size
$checksum = hash_file('sha256', $tmpUploaded);
if ($checksum === false) {
    jsonError("Failed to compute package checksum", 500);
}

$sizeBytes = filesize($tmpUploaded);
if ($sizeBytes === false) {
    jsonError("Failed to compute package size", 500);
}


// Create workspace for building db
$buildId   = "build_" . date("YmdHis") . "_" . bin2hex(random_bytes(8));
$buildRoot = "$stagingBase/$buildId";
$buildPkgs = "$buildRoot/packages";
$buildDb   = "$buildRoot/db";

ensureDir($buildPkgs);
ensureDir($buildDb);

// Copy existing package filenames from DB into buildPkgs
$existing = getPackageFilenamesForRepoArch($pdo, $repoId, $archId);
foreach ($existing as $existingFile) {
    $pkgSrc = "$finalBase/$existingFile";
    $pkgDst = "$buildPkgs/$existingFile";

    if (is_file($pkgSrc)) {
        copyPreserve($pkgSrc, $pkgDst);

        $sigSrc = "$pkgSrc.sig";
        if (is_file($sigSrc)) {
            copyPreserve($sigSrc, "$pkgDst.sig");
        }
    }
}


// Move uploaded package into build set
$workspacePkg = "$buildPkgs/$fileName";
if (!rename($tmpUploaded, $workspacePkg)) {
    jsonError("Failed to move uploaded package to workspace", 500);
}

// Sign the package
$packageSig = "$workspacePkg.sig";
exec(sprintf(
    'gpg --batch --yes --output %s --detach-sign --local-user %s %s 2>&1',
    escapeshellarg($packageSig),
    escapeshellarg($keyFp),
    escapeshellarg($workspacePkg)
), $sigOut, $sigCode);

if ($sigCode !== 0) {
    jsonError("Failed to generate package signature: " . implode("\n", $sigOut), 500);
}

// Prepare repo DB paths
$repoDbPath    = "$buildDb/$repo.db.tar.gz";
$repoFilesPath = "$buildDb/$repo.files.tar.gz";

if (file_exists($repoDbPath)) unlink($repoDbPath);
if (file_exists($repoFilesPath)) unlink($repoFilesPath);

// Build package list and call repo-add
$pkgFiles = glob("$buildPkgs/*.pkg.tar.zst");
$pkgList = implode(' ', array_map('escapeshellarg', $pkgFiles));

$repoAddCmd = sprintf('repo-add --sign --key %s %s %s 2>&1',
    escapeshellarg($keyFp),
    escapeshellarg($repoDbPath),
    $pkgList
);

exec($repoAddCmd, $repoAddOut, $repoAddExitCode);
if ($repoAddExitCode !== 0) {
    jsonError("repo-add failed: " . implode("\n", $repoAddOut), 500);
}

$filesSource = str_replace(".db.tar.gz", ".files.tar.gz", $repoDbPath);
if (!file_exists($filesSource)) {
    jsonError("repo-add failed to produce .files tarball", 500);
}
rename($filesSource, $repoFilesPath);

$publishId = "publish_" . date("YmdHis") . "_" . bin2hex(random_bytes(8));
$publishTmp = "$stagingBase/$publishId";
ensureDir($publishTmp);

foreach ($pkgFiles as $pf) {
    $bn = basename($pf);
    
    copyPreserve($pf, "$publishTmp/$bn");

    if (is_file("$pf.sig")) {
        copyPreserve("$pf.sig", "$publishTmp/$bn.sig");
    }
}

copyPreserve($repoDbPath, "$publishTmp/$repo.db.tar.gz");
copyPreserve($repoDbPath . '.sig', "$publishTmp/$repo.db.tar.gz.sig");
copyPreserve($repoFilesPath, "$publishTmp/$repo.files.tar.gz");
copyPreserve($repoFilesPath . '.sig', "$publishTmp/$repo.files.tar.gz.sig");

symlink("$repo.db.tar.gz", "$publishTmp/$repo.db");
symlink("$repo.db.tar.gz.sig", "$publishTmp/$repo.db.sig");
symlink("$repo.files.tar.gz", "$publishTmp/$repo.files");
symlink("$repo.files.tar.gz.sig", "$publishTmp/$repo.files.sig");

// Atomic swap sequence with backups
$tmpName = "$finalBase.tmp_" . bin2hex(random_bytes(8));
if (!@rename($publishTmp, $tmpName)) {
    exec(sprintf('mv %s %s', escapeshellarg($publishTmp), escapeshellarg($tmpName)), $mvOut, $mvCode);
    if ($mvCode !== 0) {
        jsonError("Failed to prepare publish tmp dir", 500);
    }
}

$backupName = "$finalBase.prev_" . bin2hex(random_bytes(8));
if (file_exists($finalBase)) {
    if (!rename($finalBase, $backupName)) {
        rename($tmpName, $finalBase);
        jsonError("Failed to backup existing repo directory", 500);
    }
}

// Move new into place
if (!rename($tmpName, $finalBase)) {
    if (file_exists($backupName)) {
        rename($backupName, $finalBase);
    }

    jsonError("Failed to move new repo into place", 500);
}

exec('rm -rf ' . escapeshellarg($backupName) . ' 2>/dev/null');

insertPackageVersion(
    $pdo,
    $packageId,
    $repoId,
    $archId,
    $packageVersion,
    $packageRelease,
    $fileName,
    $checksum,
    $sizeBytes,
    $user['id'], // uploaded_by
    $ip,
    $_SERVER['HTTP_USER_AGENT'] ?? '' // user_agent
);

echo json_encode(['success' => true]);
