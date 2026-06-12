<?php
declare(strict_types=1);
session_start();
$root = dirname(__DIR__, 2);
$vendorReady = file_exists($root . '/vendor/autoload.php');
$buildReady = file_exists($root . '/public/build/manifest.json');
$message = '';
$error = '';
$output = '';

function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function commandExists(string $command): bool {
    if (!function_exists('shell_exec')) return false;
    $result = @shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null');
    return is_string($result) && trim($result) !== '';
}
function runCommand(string $command, string $cwd, string &$output): int {
    if (!function_exists('exec')) {
        $output = 'Server has disabled PHP exec(). Use the Hostinger Terminal command shown below.';
        return 127;
    }
    $lines = [];
    $code = 0;
    $full = 'cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1';
    @exec($full, $lines, $code);
    $output = implode("\n", $lines);
    return $code;
}
function downloadComposer(string $root, string &$error): ?string {
    if (!ini_get('allow_url_fopen')) {
        $error = 'Composer is not installed and allow_url_fopen is disabled. Use Hostinger Terminal and run composer install.';
        return null;
    }
    $setup = $root . '/composer-setup.php';
    $phar = $root . '/composer.phar';
    $sig = @file_get_contents('https://composer.github.io/installer.sig');
    $installer = @file_get_contents('https://getcomposer.org/installer');
    if (!$sig || !$installer) {
        $error = 'Could not download Composer automatically. Use Hostinger Terminal and run composer install.';
        return null;
    }
    file_put_contents($setup, $installer);
    if (!hash_equals(trim($sig), hash_file('sha384', $setup))) {
        @unlink($setup);
        $error = 'Composer installer verification failed. Please use Hostinger Terminal.';
        return null;
    }
    $commandOutput = '';
    $code = runCommand(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($setup) . ' --quiet --install-dir=' . escapeshellarg($root) . ' --filename=composer.phar', $root, $commandOutput);
    @unlink($setup);
    if ($code !== 0 || !file_exists($phar)) {
        $error = 'Composer download succeeded but composer.phar could not be created. ' . $commandOutput;
        return null;
    }
    return escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phar);
}

if (empty($_SESSION['dep_csrf'])) $_SESSION['dep_csrf'] = bin2hex(random_bytes(24));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$vendorReady) {
    if (!hash_equals($_SESSION['dep_csrf'], (string)($_POST['csrf'] ?? ''))) {
        $error = 'Session expired. Reload and try again.';
    } else {
        $composerCommand = null;
        if (commandExists('composer')) {
            $composerCommand = 'composer';
        } elseif (file_exists($root . '/composer.phar')) {
            $composerCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/composer.phar');
        } else {
            $composerCommand = downloadComposer($root, $error);
        }
        if ($composerCommand !== null && $error === '') {
            $cmd = $composerCommand . ' install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress';
            $code = runCommand($cmd, $root, $output);
            clearstatcache();
            $vendorReady = file_exists($root . '/vendor/autoload.php');
            if ($code === 0 && $vendorReady) {
                $message = 'Laravel dependencies installed successfully. You can continue to portal setup.';
            } else {
                $error = 'Dependencies could not be installed automatically. Use the Hostinger Terminal command below.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Prepare Angel Print Shop Portal</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1d2939}.wrap{max-width:820px;margin:38px auto;padding:0 20px}.head{background:#09233d;color:#fff;border-radius:16px;padding:27px 30px;margin-bottom:20px}.head h1{margin:0 0 8px;font-size:27px}.head p{margin:0;color:#d6e0ec}.card{background:#fff;border:1px solid #dce5f0;border-radius:14px;padding:23px;margin-bottom:18px;box-shadow:0 4px 18px rgba(9,35,61,.06)}h2{margin:0 0 16px;color:#09233d;font-size:19px}.row{padding:14px 15px;border:1px solid #e3e9f0;border-radius:9px;margin:10px 0;display:flex;justify-content:space-between;align-items:center}.ok{color:#15803d;font-weight:bold}.bad{color:#b91c1c;font-weight:bold}.btn{border:0;background:#c59a4a;color:#fff;padding:14px 22px;border-radius:8px;font-weight:700;font-size:15px;cursor:pointer}.btn.secondary{display:inline-block;text-decoration:none;background:#09233d;margin-top:10px}.alert{border-radius:9px;padding:13px 15px;margin:0 0 17px;font-size:14px}.success{background:#dcfce7;color:#166534}.error{background:#fee2e2;color:#991b1b}pre{background:#0e1d2d;color:#d6e0ec;border-radius:8px;padding:14px;overflow:auto;font-size:13px;line-height:1.5}.small{font-size:13px;line-height:1.6;color:#546579}.steps{padding-left:18px;line-height:1.8}
</style></head><body><div class="wrap">
<div class="head"><h1>Prepare Portal Files</h1><p>Hostinger one-time dependency setup before database installation.</p></div>
<?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<div class="card"><h2>Deployment File Check</h2>
<div class="row"><span>React frontend files</span><span class="<?= $buildReady?'ok':'bad' ?>"><?= $buildReady ? '✓ Included' : '✕ Missing' ?></span></div>
<div class="row"><span>Laravel dependencies (vendor)</span><span class="<?= $vendorReady?'ok':'bad' ?>"><?= $vendorReady ? '✓ Ready' : '✕ Must install once' ?></span></div>
<?php if (!$vendorReady): ?><p class="small">The compiled React frontend is already included in this ZIP. Click below once to make the server download Laravel dependencies into the <strong>vendor</strong> folder.</p>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['dep_csrf']) ?>"><button class="btn" type="submit">Install Laravel Dependencies</button></form>
<?php else: ?><p class="small">All required website files are ready. Continue with database and admin account installation.</p><a class="btn secondary" href="index.php">Continue to Installer</a><?php endif; ?>
</div>
<?php if ($error || !$vendorReady): ?><div class="card"><h2>Terminal Backup Method</h2><p class="small">When one-click installation is blocked by hosting settings, open Hostinger Terminal, go to the application folder containing <strong>composer.json</strong>, and run:</p><pre>composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction</pre><p class="small">Then reload this page. React does not need to be built on Hostinger because <strong>public/build</strong> is already supplied.</p></div><?php endif; ?>
<?php if ($output): ?><div class="card"><h2>Installation Output</h2><pre><?= e($output) ?></pre></div><?php endif; ?>
<div class="card small"><strong>Security:</strong> After the complete portal installation is successful, delete the <strong>public/installer</strong> folder from Hostinger File Manager.</div>
</div></body></html>
