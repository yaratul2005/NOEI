<?php

declare(strict_types=1);

/**
 * NOEI CMS - Interactive Browser Setup Wizard
 */

define('NOEI_INSTALLER', true);
define('NOEI_ROOT_DIR', dirname(__DIR__));

// Start installer session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lockFile = NOEI_ROOT_DIR . '/storage/installed.lock';
$configFile = NOEI_ROOT_DIR . '/config/database.php';
$dbConfig = file_exists($configFile) ? require $configFile : [];

// Lock Security Check
if (file_exists($lockFile) || !empty($dbConfig['installed'])) {
    http_response_code(403);
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Installation Locked</title>" .
        "<link rel='icon' type='image/svg+xml' href='assets/images/NOEI.svg'>" .
        "<link rel='stylesheet' href='assets/installer.css'></head><body>" .
        "<div class='installer-container'><div class='installer-header'>" .
        "<img src='assets/images/NOEI.svg' alt='NOEI CMS' class='brand-logo' style='max-width: 120px; height: auto; margin-bottom: 12px;'>" .
        "<h1>CMS Already Installed</h1>" .
        "<p>NOEI CMS has already been configured on this system.</p></div>" .
        "<div class='alert alert-error'>For security reasons, the installer has been disabled via <code>storage/installed.lock</code>.</div>" .
        "<a href='../index.php' class='btn'>Return to Website</a></div></body></html>";
    exit;
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Step 1: Pre-Flight Check Evaluator
function checkPreflight(): array
{
    $results = [];

    // PHP Version
    $phpPass = version_compare(PHP_VERSION, '8.1.0', '>=');
    $results[] = [
        'name' => 'PHP Version (≥ 8.1.0)',
        'value' => PHP_VERSION,
        'pass' => $phpPass,
    ];

    // Extensions
    $extensions = [
        'pdo' => 'PDO Extension',
        'mbstring' => 'Mbstring Extension',
        'curl' => 'cURL Extension',
        'json' => 'JSON Extension',
    ];

    foreach ($extensions as $ext => $label) {
        $pass = extension_loaded($ext);
        $results[] = [
            'name' => $label,
            'value' => $pass ? 'Installed' : 'Missing',
            'pass' => $pass,
        ];
    }

    // Image Processor (GD or Imagick)
    $hasImageProc = extension_loaded('gd') || extension_loaded('imagick');
    $results[] = [
        'name' => 'Image Processor (GD or Imagick)',
        'value' => $hasImageProc ? (extension_loaded('gd') ? 'GD Installed' : 'Imagick Installed') : 'Missing (Recommended)',
        'pass' => true, // Warning only, core CMS remains operational
    ];

    // Writable Directories
    $directories = [
        '/config',
        '/storage',
        '/storage/uploads',
        '/storage/cache',
        '/storage/logs',
        '/storage/backups',
    ];

    foreach ($directories as $dir) {
        $fullPath = NOEI_ROOT_DIR . $dir;
        $isWritable = is_dir($fullPath) && is_writable($fullPath);
        $results[] = [
            'name' => "Directory Writable: {$dir}",
            'value' => $isWritable ? 'Writable' : 'Not Writable',
            'pass' => $isWritable,
        ];
    }

    return $results;
}

if (defined('NOEI_TESTING')) {
    return;
}

$preflightChecks = checkPreflight();
$allPreflightPassed = array_reduce($preflightChecks, fn($carry, $item) => $carry && $item['pass'], true);

// Process Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 2) {
        // Step 2: Database Setup & Connection Test
        $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort = (int)($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_password'] ?? '';
        $dbPrefix = trim($_POST['db_prefix'] ?? 'cms_');

        if (empty($dbName) || empty($dbUser)) {
            $error = 'Database Name and Username are required.';
        } else {
            try {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);

                // Import Schema SQL
                $schemaFile = NOEI_ROOT_DIR . '/install/schema.sql';
                if (!file_exists($schemaFile)) {
                    throw new Exception("Schema SQL file not found at install/schema.sql");
                }

                $sqlContent = file_get_contents($schemaFile);
                if ($dbPrefix !== 'cms_') {
                    $sqlContent = str_replace('`cms_', '`' . $dbPrefix, $sqlContent);
                }

                // Strip comments line by line
                $cleanSql = preg_replace('/--.*$/m', '', $sqlContent);

                // Execute SQL statements
                $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
                foreach ($statements as $stmt) {
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                }

                // Save DB Config to Session
                $_SESSION['installer_db'] = [
                    'driver' => 'mysql',
                    'host' => $dbHost,
                    'port' => $dbPort,
                    'dbname' => $dbName,
                    'username' => $dbUser,
                    'password' => $dbPass,
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => $dbPrefix,
                    'installed' => false
                ];

                header('Location: index.php?step=3');
                exit;
            } catch (Exception $e) {
                $error = 'Database Connection Error: ' . $e->getMessage();
            }
        }
    } elseif ($step === 3) {
        // Step 3: Site & Admin Account Setup
        $siteTitle = trim($_POST['site_title'] ?? 'NOEI CMS Site');
        $adminUser = trim($_POST['admin_username'] ?? 'admin');
        $adminEmail = trim($_POST['admin_email'] ?? 'admin@example.com');
        $adminPass = $_POST['admin_password'] ?? '';

        if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
            $error = 'All Admin fields (Username, Email, Password) are required.';
        } elseif (strlen($adminPass) < 8) {
            $error = 'Admin Password must be at least 8 characters long.';
        } elseif (empty($_SESSION['installer_db'])) {
            $error = 'Database configuration missing from session. Please restart setup.';
            $step = 2;
        } else {
            try {
                $dbInfo = $_SESSION['installer_db'];
                $dsn = "mysql:host={$dbInfo['host']};port={$dbInfo['port']};dbname={$dbInfo['dbname']};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbInfo['username'], $dbInfo['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);

                $prefix = $dbInfo['prefix'];

                // Insert Admin User
                $passwordHash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `{$prefix}users` (username, email, password_hash, role_id, status) VALUES (:username, :email, :password_hash, 1, 'active')");
                $stmt->execute([
                    'username' => $adminUser,
                    'email' => $adminEmail,
                    'password_hash' => $passwordHash
                ]);

                // Update Site Options
                $stmtOpt = $pdo->prepare("UPDATE `{$prefix}options` SET option_value = :val WHERE option_name = :name");
                $stmtOpt->execute(['val' => $siteTitle, 'name' => 'site_title']);
                $stmtOpt->execute(['val' => $adminEmail, 'name' => 'admin_email']);

                // Complete Installation & Create Lock
                $dbInfo['installed'] = true;
                $configContent = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($dbInfo, true) . ";\n";
                file_put_contents($configFile, $configContent);

                file_put_contents($lockFile, "INSTALLED_ON=" . date('c') . "\nVERSION=1.0.0-alpha\n");

                unset($_SESSION['installer_db']);
                header('Location: index.php?step=4');
                exit;
            } catch (Exception $e) {
                $error = 'Failed to create Admin account: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOEI CMS Installation Wizard</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/NOEI.svg">
    <link rel="stylesheet" href="assets/installer.css">
</head>
<body>
    <div class="installer-container">
        <div class="installer-header">
            <img src="assets/images/NOEI.svg" alt="NOEI CMS" class="brand-logo" style="max-width: 120px; height: auto; margin-bottom: 12px;">
            <h1>NOEI CMS Setup</h1>
            <p>Modern, Fast, & Secure Shared-Hosting CMS</p>
        </div>

        <div class="step-indicator">
            <div class="step-item <?= $step >= 1 ? ($step > 1 ? 'completed' : 'active') : '' ?>">1. Pre-Flight</div>
            <div class="step-item <?= $step >= 2 ? ($step > 2 ? 'completed' : 'active') : '' ?>">2. Database</div>
            <div class="step-item <?= $step >= 3 ? ($step > 3 ? 'completed' : 'active') : '' ?>">3. Admin Account</div>
            <div class="step-item <?= $step >= 4 ? 'active' : '' ?>">4. Finish</div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: Pre-Flight Check -->
            <h2>System Requirements Check</h2>
            <p style="margin-bottom: 16px; color: #64748b; font-size: 0.9rem;">Verifying environment compatibility prior to installation.</p>
            <ul class="check-list">
                <?php foreach ($preflightChecks as $check): ?>
                    <li class="check-item">
                        <span><?= htmlspecialchars($check['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="badge <?= $check['pass'] ? 'badge-pass' : 'badge-fail' ?>">
                            <?= htmlspecialchars($check['value'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($allPreflightPassed): ?>
                <a href="index.php?step=2" class="btn">Continue to Database Setup &rarr;</a>
            <?php else: ?>
                <button class="btn" disabled>Fix System Requirements to Continue</button>
            <?php endif; ?>

        <?php elseif ($step === 2): ?>
            <!-- STEP 2: Database Setup -->
            <h2>Database Configuration</h2>
            <p style="margin-bottom: 20px; color: #64748b; font-size: 0.9rem;">Enter your MySQL/MariaDB credentials provided by your web host.</p>
            <form method="POST" action="index.php?step=2">
                <div class="form-group">
                    <label for="db_host">Database Host</label>
                    <input type="text" id="db_host" name="db_host" class="form-control" value="127.0.0.1" required>
                </div>
                <div class="form-group">
                    <label for="db_port">Database Port</label>
                    <input type="number" id="db_port" name="db_port" class="form-control" value="3306" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Database Name</label>
                    <input type="text" id="db_name" name="db_name" class="form-control" placeholder="noei_db" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Database Username</label>
                    <input type="text" id="db_user" name="db_user" class="form-control" placeholder="db_user" required>
                </div>
                <div class="form-group">
                    <label for="db_password">Database Password</label>
                    <input type="password" id="db_password" name="db_password" class="form-control">
                </div>
                <div class="form-group">
                    <label for="db_prefix">Table Prefix</label>
                    <input type="text" id="db_prefix" name="db_prefix" class="form-control" value="cms_" required>
                </div>
                <button type="submit" class="btn">Test & Connect Database &rarr;</button>
            </form>

        <?php elseif ($step === 3): ?>
            <!-- STEP 3: Admin & Site Setup -->
            <h2>Site & Administrator Account</h2>
            <p style="margin-bottom: 20px; color: #64748b; font-size: 0.9rem;">Configure your main website title and initial superadmin account.</p>
            <form method="POST" action="index.php?step=3">
                <div class="form-group">
                    <label for="site_title">Site Title</label>
                    <input type="text" id="site_title" name="site_title" class="form-control" value="NOEI CMS Site" required>
                </div>
                <div class="form-group">
                    <label for="admin_username">Admin Username</label>
                    <input type="text" id="admin_username" name="admin_username" class="form-control" value="admin" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Admin Email Address</label>
                    <input type="email" id="admin_email" name="admin_email" class="form-control" placeholder="admin@example.com" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Admin Password (min. 8 chars)</label>
                    <input type="password" id="admin_password" name="admin_password" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn">Install NOEI CMS &rarr;</button>
            </form>

        <?php elseif ($step === 4): ?>
            <!-- STEP 4: Completion -->
            <h2>Installation Complete!</h2>
            <div class="alert alert-success" style="margin-top: 16px;">
                NOEI CMS has been successfully installed and configured! For security, the installer has been locked automatically.
            </div>
            <p style="margin-bottom: 24px; color: #64748b; font-size: 0.95rem;">You can now log in to the admin panel or visit your homepage.</p>
            <a href="../index.php" class="btn" style="margin-bottom: 12px;">Visit Website Homepage &rarr;</a>
        <?php endif; ?>
    </div>
</body>
</html>
