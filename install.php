<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
cm_send_security_headers();

$lockPath = __DIR__ . '/storage/private/installed.lock';
$configPaths = [dirname(__DIR__) . '/config.local.php', __DIR__ . '/config/config.local.php'];
$configExists = false;
foreach ($configPaths as $p) {
    if (is_file($p)) { $configExists = true; break; }
}
if (is_file($lockPath) || $configExists) {
    http_response_code(403);
    exit('<!doctype html><html lang="en"><meta charset="utf-8"><title>Installer locked</title><body><h1>Installer locked</h1><p>Configuration or install lock already exists. For safety, the installer cannot be reopened through the web.</p></body></html>');
}

$errors = [];
$success = false;
$values = ['host' => '127.0.0.1', 'port' => '3306', 'dbname' => 'crystalmarket', 'dbuser' => '', 'site_name' => 'CrystalMarket', 'locale' => 'fr', 'admin_name' => '', 'admin_email' => '', 'admin_password' => ''];

function cm_sql_statements(string $sql): array
{
    $out = []; $buffer = ''; $quote = ''; $lineComment = false; $blockComment = false; $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $ch = $sql[$i]; $next = $i + 1 < $n ? $sql[$i + 1] : '';
        if ($lineComment) { if ($ch === "\n") { $lineComment = false; $buffer .= "\n"; } continue; }
        if ($blockComment) { if ($ch === '*' && $next === '/') { $blockComment = false; $i++; } continue; }
        if ($quote !== '') {
            $buffer .= $ch;
            if ($ch === '\\' && $i + 1 < $n) { $buffer .= $sql[++$i]; continue; }
            if ($ch === $quote) {
                if ($next === $quote && $quote !== '`') { $buffer .= $sql[++$i]; continue; }
                $quote = '';
            }
            continue;
        }
        if (($ch === '-' && $next === '-' && ($i + 2 >= $n || ctype_space($sql[$i + 2]))) || $ch === '#') { $lineComment = true; if ($ch === '-') $i++; continue; }
        if ($ch === '/' && $next === '*') { $blockComment = true; $i++; continue; }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $buffer .= $ch; continue; }
        if ($ch === ';') { if (trim($buffer) !== '') $out[] = trim($buffer); $buffer = ''; continue; }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') $out[] = trim($buffer);
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (array_keys($values) as $key) {
        if ($key === 'admin_password') continue;
        $values[$key] = trim((string)($_POST[$key] ?? $values[$key]));
    }
    $values['admin_password'] = (string)($_POST['admin_password'] ?? '');
    if (!cm_csrf_valid($_POST['_csrf'] ?? null)) $errors[] = 'Session token invalid or expired. Refresh the page and try again.';
    if (!preg_match('/^[a-zA-Z0-9_]{2,64}$/', $values['dbname'])) $errors[] = 'Database name must contain 2–64 letters, numbers, or underscores.';
    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL) || strlen($values['admin_email']) > 190) $errors[] = 'Enter a valid administrator email address.';
    if ($values['admin_name'] === '' || strlen($values['admin_name']) > 80) $errors[] = 'Administrator name is required (maximum 80 characters).';
    if (strlen($values['admin_password']) < 12 || strlen($values['admin_password']) > 200) $errors[] = 'Administrator password must be between 12 and 200 characters.';
    if (!in_array($values['locale'], ['fr','nl','en','es'], true)) $errors[] = 'Unsupported default language.';
    if ($values['site_name'] === '' || strlen($values['site_name']) > 120) $errors[] = 'Site name is required (maximum 120 characters).';
    if (!extension_loaded('pdo_mysql')) $errors[] = 'The PHP pdo_mysql extension is not installed. Enable it before installing CrystalMarket.';
    if (!extension_loaded('mbstring')) $errors[] = 'The PHP mbstring extension is not installed. Enable it before installing CrystalMarket.';

    if (!$errors) {
        $pdo = null;
        try {
            $port = filter_var($values['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
            if ($port === false) throw new RuntimeException('Database port must be between 1 and 65535.');
            $dbPassword = (string)($_POST['dbpassword'] ?? '');
            // The hosting provider/user has already created the database. Connect to it directly;
            // never issue CREATE DATABASE or USE, which can fail on shared hosting.
            $databaseDsn = 'mysql:host=' . $values['host'] . ';port=' . $port . ';dbname=' . $values['dbname'] . ';charset=utf8mb4';
            $pdo = new PDO($databaseDsn, $values['dbuser'], $dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);

            // Protect existing data: this installer only initializes an empty, pre-created database.
            $existingTables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($existingTables) {
                throw new RuntimeException('The selected database already contains tables. For safety, the installer will not overwrite or seed it. Select an empty database, or back up and prepare the database before installation.');
            }
            $schemaPath = __DIR__ . '/database/schema.sql';
            $seedPath = __DIR__ . '/database/seeds.sql';
            foreach ([$schemaPath, $seedPath] as $requiredFile) if (!is_file($requiredFile) || !is_readable($requiredFile)) throw new RuntimeException('Required database source file is missing or unreadable.');
            foreach (cm_sql_statements((string)file_get_contents($schemaPath)) as $statement) $pdo->exec($statement);
            foreach (cm_sql_statements((string)file_get_contents($seedPath)) as $statement) $pdo->exec($statement);
            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1");
            $roleStmt->execute(); $roleId = $roleStmt->fetchColumn();
            if (!$roleId) throw new RuntimeException('Administrator role was not created by the seed data.');
            $userStmt = $pdo->prepare("INSERT INTO users (role_id, email, display_name, password_hash, status, locale, email_verified_at) VALUES (?, ?, ?, ?, 'active', ?, NOW())");
            $userStmt->execute([(int)$roleId, strtolower($values['admin_email']), $values['admin_name'], password_hash($values['admin_password'], PASSWORD_DEFAULT), $values['locale']]);
            $adminId = (int)$pdo->lastInsertId();
            $settings = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
            $settings->execute([$values['site_name'], 'site_name']);
            $settings->execute([$values['locale'], 'default_locale']);
            $settings->execute([date('Y-m-d H:i:s'), 'installed_at']);
            $secret = bin2hex(random_bytes(32));
            $config = "<?php\ndeclare(strict_types=1);\nreturn " . var_export([
                'database' => ['host' => $values['host'], 'port' => (int)$port, 'name' => $values['dbname'], 'user' => $values['dbuser'], 'password' => $dbPassword, 'charset' => 'utf8mb4'],
                'app' => ['name' => $values['site_name'], 'base_url' => '', 'secret' => $secret],
            ], true) . ";\n";
            $configPath = dirname(__DIR__) . '/config.local.php';
            if (!is_dir(dirname($configPath)) || !is_writable(dirname($configPath))) $configPath = __DIR__ . '/config/config.local.php';
            $tempPath = $configPath . '.tmp-' . bin2hex(random_bytes(6));
            if (file_put_contents($tempPath, $config, LOCK_EX) === false) throw new RuntimeException('Could not write configuration file. Check directory permissions.');
            @chmod($tempPath, 0640);
            if (!rename($tempPath, $configPath)) { @unlink($tempPath); throw new RuntimeException('Could not finalize configuration file.'); }
            $privateDir = __DIR__ . '/storage/private';
            if (!is_dir($privateDir) && !mkdir($privateDir, 0700, true) && !is_dir($privateDir)) throw new RuntimeException('Could not create private storage directory.');
            if (file_put_contents($lockPath, "installed_at=" . date(DATE_ATOM) . "\nadmin_id=" . $adminId . "\n", LOCK_EX) === false) throw new RuntimeException('Installation completed but the lock file could not be written. Disable install.php immediately and create the lock file manually.');
            @chmod($lockPath, 0600);
            session_regenerate_id(true);
            $success = true;
        } catch (Throwable $e) {
            error_log('CrystalMarket installer error: ' . $e->getMessage());
            $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Installation failed. Check server logs; sensitive details are not displayed here. If the database was partially created, inspect it before retrying.';
        }
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CrystalMarket Installer</title><link rel="stylesheet" href="/assets/css/tokens.css"><link rel="stylesheet" href="/assets/css/app.css"><link rel="stylesheet" href="/assets/css/installer.css"></head><body><main class="installer glass"><h1>CrystalMarket setup</h1><p class="muted">Connect to a database that your hosting provider has already created, then configure the site and first administrator. The database user needs permission to create and modify tables inside that database; this installer does not create databases.</p>
<?php if ($success): ?><div class="success"><h2>Installation completed</h2><p>Database schema and initial settings were created, and the administrator account was saved.</p><p><a href="/">Open CrystalMarket</a></p><p class="muted">Delete or disable install.php after confirming the site works.</p></div>
<?php else: ?><?php foreach ($errors as $error): ?><div class="error"><?= cm_e($error) ?></div><?php endforeach; ?><form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= cm_e(cm_csrf_token()) ?>"><label>Database host<input name="host" required value="<?= cm_e($values['host']) ?>"></label><label>Database port<input name="port" type="number" min="1" max="65535" required value="<?= cm_e($values['port']) ?>"></label><label>Database name<input name="dbname" required pattern="[A-Za-z0-9_]{2,64}" value="<?= cm_e($values['dbname']) ?>"></label><label>Database username<input name="dbuser" required value="<?= cm_e($values['dbuser']) ?>"></label><label class="wide">Database password<input name="dbpassword" type="password" autocomplete="new-password"></label><label class="wide">Site name<input name="site_name" required maxlength="120" value="<?= cm_e($values['site_name']) ?>"></label><label>Default language<select name="locale"><option value="fr" <?= $values['locale']==='fr'?'selected':'' ?>>Français</option><option value="en" <?= $values['locale']==='en'?'selected':'' ?>>English</option><option value="nl" <?= $values['locale']==='nl'?'selected':'' ?>>Nederlands</option><option value="es" <?= $values['locale']==='es'?'selected':'' ?>>Español</option></select></label><label>Administrator display name<input name="admin_name" required maxlength="80" value="<?= cm_e($values['admin_name']) ?>"></label><label class="wide">Administrator email<input name="admin_email" type="email" required maxlength="190" value="<?= cm_e($values['admin_email']) ?>"></label><label class="wide">Administrator password (minimum 12 characters)<input name="admin_password" type="password" minlength="12" maxlength="200" autocomplete="new-password" required></label><div class="wide"><button class="button button-primary" type="submit">Install CrystalMarket</button></div></form><p class="muted">Use the exact database name, username, and password from your hosting control panel. The database must be empty; existing tables and data are never deleted. Never expose database credentials in screenshots or public reports.</p><?php endif; ?></main></body></html>
