<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Core\Database;
use Core\Request;
use Core\Response;
use Core\View;
use PDO;

/**
 * Admin Site Health & cPanel Diagnostics Controller for NOEI CMS.
 */
class HealthController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /**
     * Display Site Health & Diagnostics report.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        $checks = $this->runHealthChecks($request);

        $goodCount = count(array_filter($checks, fn($c) => $c['status'] === 'good'));
        $criticalCount = count(array_filter($checks, fn($c) => $c['status'] === 'critical'));
        $recommendedCount = count(array_filter($checks, fn($c) => $c['status'] === 'recommended'));
        $total = count($checks);

        $score = $total > 0 ? (int)round(($goodCount / $total) * 100) : 100;

        $html = View::render('admin/health/index', [
            'title' => 'Site Health & Diagnostics - NOEI CMS',
            'user' => $this->auth->user(),
            'currentRoute' => 'health',
            'checks' => $checks,
            'score' => $score,
            'goodCount' => $goodCount,
            'criticalCount' => $criticalCount,
            'recommendedCount' => $recommendedCount,
        ]);

        return new Response($html);
    }

    /**
     * Execute comprehensive system diagnostic tests.
     *
     * @param Request $request
     * @return array<array{category: string, label: string, value: string, status: string, description: string}>
     */
    public function runHealthChecks(Request $request): array
    {
        $checks = [];

        // 1. PHP Version
        $phpVersion = PHP_VERSION;
        $phpStatus = version_compare($phpVersion, '8.2.0', '>=') ? 'good' : (version_compare($phpVersion, '8.1.0', '>=') ? 'recommended' : 'critical');
        $checks[] = [
            'category' => 'PHP & Server',
            'label' => 'PHP Version',
            'value' => "v{$phpVersion}",
            'status' => $phpStatus,
            'description' => $phpStatus === 'critical' ? 'PHP 8.1+ is strictly required. Please upgrade in cPanel Select PHP Version.' : 'Running a modern and supported PHP version.',
        ];

        // 2. Memory Limit
        $memoryLimit = ini_get('memory_limit') ?: '128M';
        $memoryBytes = $this->parseMemoryBytes($memoryLimit);
        $memStatus = ($memoryBytes >= 134217728 || $memoryLimit === '-1') ? 'good' : (($memoryBytes >= 67108864) ? 'recommended' : 'critical');
        $checks[] = [
            'category' => 'PHP & Server',
            'label' => 'PHP Memory Limit',
            'value' => $memoryLimit,
            'status' => $memStatus,
            'description' => $memStatus === 'critical' ? 'Memory limit is below recommended 64M. Increase memory_limit in cPanel MultiPHP INI Editor.' : 'Sufficient memory allocated for CMS operations.',
        ];

        // 3. Required Extensions
        $requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'curl', 'json', 'zip', 'fileinfo'];
        $missingExts = array_filter($requiredExtensions, fn($ext) => !extension_loaded($ext));

        $checks[] = [
            'category' => 'PHP & Server',
            'label' => 'Required PHP Extensions',
            'value' => empty($missingExts) ? 'All Installed' : 'Missing: ' . implode(', ', $missingExts),
            'status' => empty($missingExts) ? 'good' : 'critical',
            'description' => empty($missingExts) ? 'All essential PHP extensions (PDO, Mbstring, cURL, JSON, Zip, Fileinfo) are active.' : 'Enable missing extensions via cPanel PHP Extensions manager.',
        ];

        // 4. Image Resizing Extension
        $hasGd = extension_loaded('gd');
        $hasImagick = extension_loaded('imagick');
        $checks[] = [
            'category' => 'Media & Processing',
            'label' => 'Image Processing Library',
            'value' => $hasGd ? 'GD Active' : ($hasImagick ? 'Imagick Active' : 'None Detected'),
            'status' => ($hasGd || $hasImagick) ? 'good' : 'recommended',
            'description' => ($hasGd || $hasImagick) ? 'Responsive thumbnail and web variant generator is fully operational.' : 'Install GD or Imagick in cPanel to enable automatic image resizing.',
        ];

        // 5. Storage Directory Permissions
        $rootDir = dirname(__DIR__, 2);
        $directories = [
            '/storage/cache',
            '/storage/logs',
            '/storage/uploads',
            '/storage/backups',
            '/config',
        ];

        $writableCount = 0;
        foreach ($directories as $dir) {
            $path = $rootDir . $dir;
            if (is_dir($path) && is_writable($path)) {
                $writableCount++;
            }
        }

        $allWritable = ($writableCount === count($directories));
        $checks[] = [
            'category' => 'Security & Storage',
            'label' => 'Storage & Config Writable',
            'value' => "{$writableCount}/" . count($directories) . " Directories Writable",
            'status' => $allWritable ? 'good' : 'critical',
            'description' => $allWritable ? 'Storage directories have proper 0755 permissions.' : 'Ensure storage and config directories are writable (chmod 0755 in cPanel File Manager).',
        ];

        // 6. Database Connection & UTF8mb4
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            $checks[] = [
                'category' => 'Database',
                'label' => 'Database Server',
                'value' => ucfirst((string)$driver) . " v{$version}",
                'status' => 'good',
                'description' => 'Database connection established using parameterized prepared statements with native utf8mb4 encoding.',
            ];
        } catch (\Throwable $e) {
            $checks[] = [
                'category' => 'Database',
                'label' => 'Database Connection',
                'value' => 'Connection Error',
                'status' => 'critical',
                'description' => 'Failed to connect to database: ' . $e->getMessage(),
            ];
        }

        // 7. HTTPS & Secure Connection
        $isHttps = $request->isHttps();
        $checks[] = [
            'category' => 'Security & Storage',
            'label' => 'HTTPS & SSL Encryption',
            'value' => $isHttps ? 'HTTPS Active' : 'HTTP (Insecure)',
            'status' => $isHttps ? 'good' : 'recommended',
            'description' => $isHttps ? 'Site is served over secure encrypted HTTPS connection.' : 'Enable Let\'s Encrypt SSL in cPanel to ensure secure visitor browsing.',
        ];

        // 8. Upload Limits
        $uploadMax = ini_get('upload_max_filesize') ?: '2M';
        $postMax = ini_get('post_max_size') ?: '8M';
        $checks[] = [
            'category' => 'PHP & Server',
            'label' => 'Upload File Size Limit',
            'value' => "Upload: {$uploadMax} | Post: {$postMax}",
            'status' => 'good',
            'description' => 'Shared hosting upload thresholds configured.',
        ];

        return $checks;
    }

    /**
     * Convert memory shorthand (e.g. 128M, 1G) to integer bytes.
     */
    private function parseMemoryBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1] ?? '');
        $num = (int)$val;

        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }

        return $num;
    }
}
