<?php
declare(strict_types=1);

session_start();
$root = dirname(__DIR__, 2);
$lockFile = $root . '/storage/installed.lock';
$envFile = $root . '/.env';
$sqlFile = $root . '/database/angelprintshop.sql';
$message = null;
$error = null;
$installed = file_exists($lockFile);

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function envLine(string $key, string $value): string {
    $value = str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', ''], $value);
    return $key . '="' . $value . '"';
}
function updateEnv(string $template, array $values): string {
    foreach ($values as $key => $value) {
        $line = envLine($key, (string)$value);
        $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
        if (preg_match($pattern, $template)) {
            $template = preg_replace($pattern, $line, $template);
        } else {
            $template .= PHP_EOL . $line;
        }
    }
    return $template . PHP_EOL;
}
function requirements(string $root): array {
    return [
        'PHP 8.2 or above' => version_compare(PHP_VERSION, '8.2.0', '>='),
        'PDO MySQL extension' => extension_loaded('pdo_mysql'),
        'OpenSSL extension' => extension_loaded('openssl'),
        'Mbstring extension' => extension_loaded('mbstring'),
        'Fileinfo extension' => extension_loaded('fileinfo'),
        'Writable storage folder' => is_writable($root . '/storage'),
        'Writable bootstrap/cache folder' => is_writable($root . '/bootstrap/cache'),
        'Laravel dependencies (vendor)' => file_exists($root . '/vendor/autoload.php'),
        'Compiled React frontend (public/build)' => file_exists($root . '/public/build/manifest.json'),
    ];
}
function splitSql(string $sql): array {
    $statements = [];
    $buffer = '';
    foreach (preg_split('/\R/', $sql) as $line) {
        if (preg_match('/^\s*--/', $line) || trim($line) === '') continue;
        $buffer .= $line . "\n";
        if (str_ends_with(trim($line), ';')) {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') $statements[] = trim($buffer);
    return $statements;
}

$checks = requirements($root);
$requiredReady = !in_array(false, $checks, true);

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Session expired. Reload this page and try again.';
    } elseif (!$requiredReady) {
        $error = 'Please resolve all server checks before running installation.';
    } else {
        $appUrl = rtrim(trim((string)($_POST['app_url'] ?? '')), '/');
        $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
        $dbPort = trim((string)($_POST['db_port'] ?? '3306'));
        $dbName = trim((string)($_POST['db_database'] ?? ''));
        $dbUser = trim((string)($_POST['db_username'] ?? ''));
        $dbPass = (string)($_POST['db_password'] ?? '');
        $adminName = trim((string)($_POST['admin_name'] ?? 'Portal Admin'));
        $adminEmail = trim((string)($_POST['admin_email'] ?? ''));
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        $staffName = trim((string)($_POST['staff_name'] ?? 'Printing Operator'));
        $staffEmail = trim((string)($_POST['staff_email'] ?? ''));
        $staffPassword = (string)($_POST['staff_password'] ?? '');
        if (!$appUrl || !$dbName || !$dbUser || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please fill all required application, database, admin and staff fields correctly.';
        } elseif (strlen($adminPassword) < 8 || strlen($staffPassword) < 8) {
            $error = 'Admin and staff passwords must contain at least 8 characters.';
        } else {
            try {
                $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $sql = file_get_contents($sqlFile);
                if ($sql === false) throw new RuntimeException('SQL installation file is missing.');
                foreach (splitSql($sql) as $statement) $pdo->exec($statement);
                $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, password = ? WHERE role = ?');
                $stmt->execute([$adminName, $adminEmail, password_hash($adminPassword, PASSWORD_BCRYPT), 'admin']);
                $stmt->execute([$staffName, $staffEmail, password_hash($staffPassword, PASSWORD_BCRYPT), 'staff']);
                if (empty($_POST['demo_dealer'])) {
                    $pdo->exec("DELETE FROM wallet_transactions WHERE user_id = 3; DELETE FROM users WHERE id = 3 AND role = 'dealer';");
                }
                $template = file_get_contents($root . '/.env.example') ?: '';
                $env = updateEnv($template, [
                    'APP_ENV' => 'production', 'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
                    'APP_DEBUG' => 'false', 'APP_URL' => $appUrl,
                    'DB_HOST' => $dbHost, 'DB_PORT' => $dbPort, 'DB_DATABASE' => $dbName,
                    'DB_USERNAME' => $dbUser, 'DB_PASSWORD' => $dbPass,
                ]);
                if (file_put_contents($envFile, $env) === false) throw new RuntimeException('Could not write .env file.');
                $storageLink = $root . '/public/storage';
                if (!file_exists($storageLink) && function_exists('symlink')) {
                    @symlink($root . '/storage/app/public', $storageLink);
                }
                file_put_contents($lockFile, 'Installed: ' . date('c') . PHP_EOL);
                $installed = true;
                $message = 'Installation complete. Your Angel Print Shop portal is ready.';
            } catch (Throwable $exception) {
                $error = 'Installation failed: ' . $exception->getMessage();
            }
        }
    }
}
$_SESSION['install_csrf'] = bin2hex(random_bytes(24));
$baseUrl = rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Angel Print Shop Portal Installer</title>
<style>
*{box-sizing:border-box} body{margin:0;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#1b2733} .wrap{max-width:960px;margin:32px auto;padding:0 18px} .head{background:#09233d;color:#fff;border-radius:16px;padding:27px 30px;margin-bottom:18px}.head h1{margin:0 0 8px;font-size:27px}.head p{margin:0;opacity:.83}.card{background:#fff;border:1px solid #dce3ec;border-radius:14px;padding:22px;margin-bottom:18px;box-shadow:0 3px 16px rgba(9,35,61,.05)} h2{font-size:19px;color:#09233d;margin:0 0 15px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.check{padding:12px;border-radius:9px;border:1px solid #e6ebf1;display:flex;align-items:center;gap:9px;font-size:14px}.ok{color:#15803d;font-weight:bold}.bad{color:#b91c1c;font-weight:bold}.fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px} label{display:block;font-size:13px;font-weight:700;color:#344555} label.full{grid-column:1/-1} input{display:block;width:100%;height:44px;border:1px solid #cfdae6;border-radius:8px;padding:0 12px;font-size:14px;margin-top:6px} .section-label{grid-column:1/-1;margin-top:10px;padding-top:13px;border-top:1px solid #e7ecf3;font-size:15px;color:#09233d;font-weight:700}.alert{padding:14px 16px;border-radius:9px;margin-bottom:17px;font-size:14px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}.warning{background:#fff7ed;color:#9a3412} .button{background:#c59a4a;color:#fff;border:0;border-radius:9px;padding:14px 22px;font-size:15px;font-weight:bold;cursor:pointer;margin-top:18px}.button:disabled{opacity:.5;cursor:not-allowed}.choice{grid-column:1/-1;display:flex;gap:10px;align-items:center;font-weight:normal}.choice input{display:inline;width:auto;height:auto;margin:0}.done{text-align:center;padding:18px}.done a{display:inline-block;background:#09233d;color:white;text-decoration:none;padding:13px 23px;border-radius:8px;font-weight:bold;margin-top:10px}.small{font-size:13px;color:#536577;line-height:1.55}@media(max-width:650px){.grid,.fields{grid-template-columns:1fr}.head{padding:22px}}
</style>
</head><body><div class="wrap">
<div class="head"><h1>Angel Print Shop B2B Portal</h1><p>One-time website installer — database, admin account and printing staff setup.</p></div>
<?php if ($message): ?><div class="alert success"><?=e($message)?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?=e($error)?></div><?php endif; ?>
<?php if ($installed): ?>
<div class="card done"><h2>Portal is Installed</h2><p>Your portal has been configured. For security, remove the <strong>public/installer</strong> folder from the server.</p><a href="../">Open Portal</a></div>
<?php else: ?>
<div class="card"><h2>1. Server Check</h2><div class="grid"><?php foreach ($checks as $label => $pass): ?><div class="check"><span class="<?=$pass?'ok':'bad'?>"><?=$pass?'✓':'✕'?></span><?=e($label)?></div><?php endforeach; ?></div>
<?php if (!$requiredReady): ?><div class="alert warning" style="margin-top:15px;margin-bottom:0">Required files are not ready. This package already includes the compiled React frontend. When <strong>vendor</strong> is missing, first open <a href="dependencies.php" style="font-weight:bold;color:#9a3412">Prepare Portal Files</a> and click <strong>Install Laravel Dependencies</strong>.</div><?php endif; ?></div>
<div class="card"><h2>2. Website & Database Details</h2><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['install_csrf'])?>"><div class="fields">
<label class="full">Website URL *<input required name="app_url" value="<?=e($_POST['app_url'] ?? $baseUrl)?>" placeholder="https://angelprintshop.com"></label>
<div class="section-label">MySQL Database</div>
<label>Database Host *<input required name="db_host" value="<?=e($_POST['db_host'] ?? 'localhost')?>"></label><label>Database Port *<input required name="db_port" value="<?=e($_POST['db_port'] ?? '3306')?>"></label>
<label>Database Name *<input required name="db_database" value="<?=e($_POST['db_database'] ?? '')?>"></label><label>Database Username *<input required name="db_username" value="<?=e($_POST['db_username'] ?? '')?>"></label>
<label class="full">Database Password<input type="password" name="db_password" value=""></label>
<div class="section-label">Admin Login</div>
<label>Admin Name *<input required name="admin_name" value="<?=e($_POST['admin_name'] ?? 'Portal Admin')?>"></label><label>Admin Email *<input required type="email" name="admin_email" value="<?=e($_POST['admin_email'] ?? '')?>"></label>
<label class="full">Admin Password *<input required type="password" name="admin_password" minlength="8"></label>
<div class="section-label">Printing Staff Login</div>
<label>Staff Name *<input required name="staff_name" value="<?=e($_POST['staff_name'] ?? 'Printing Operator')?>"></label><label>Staff Email *<input required type="email" name="staff_email" value="<?=e($_POST['staff_email'] ?? '')?>"></label>
<label class="full">Staff Password *<input required type="password" name="staff_password" minlength="8"></label>
<label class="choice"><input type="checkbox" name="demo_dealer" value="1" <?=isset($_POST['demo_dealer'])?'checked':''?>> Keep demo dealer account and opening wallet balance for testing</label>
</div><button class="button" type="submit" <?=$requiredReady?'':'disabled'?>>Install Portal</button></form></div>
<div class="card small"><strong>Important:</strong> Use an empty MySQL database because installation will recreate portal tables and import sample product rows. After installation, delete the installer folder and ensure the domain document root points to the Laravel <strong>public</strong> directory.</div>
<?php endif; ?></div></body></html>
