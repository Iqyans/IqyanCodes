<?php
/**
 * Tool Enkripsi PHP V4 - Dengan Logging Debug + Kompresi + Aes + IV + ciphertext
 *
 * @author    Dkid03
 * @version   4.0
 */

define('ENCRYPTION_ALGO', 'aes-256-cbc');
define('COMPRESSION_LEVEL', 9);

if (php_sapi_name() === 'cli') {
    cliMode();
} else {
    webMode();
}

function cliMode() {
    global $argv;
    if (count($argv) < 2) {
        echo "Penggunaan: php encryptor_v4.php <file_input.php> [file_output.php] [debug=0/1]\n";
        exit(1);
    }
    $input = $argv[1];
    if (!file_exists($input)) die("File tidak ditemukan.\n");
    $output = $argv[2] ?? pathinfo($input, PATHINFO_FILENAME) . '_encrypted_v4.php';
    $debug = isset($argv[3]) ? (int)$argv[3] : 0;

    $code = file_get_contents($input);
    $encrypted = encryptPhpCode($code, $debug);
    file_put_contents($output, $encrypted);
    echo "Berhasil! File: $output (debug=" . ($debug ? 'on' : 'off') . ")\n";
}

function webMode() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['php_file'])) {
        $file = $_FILES['php_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) die("Upload gagal.");
        $debug = isset($_POST['debug']) ? 1 : 0;
        $code = file_get_contents($file['tmp_name']);
        $encrypted = encryptPhpCode($code, $debug);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="encrypted_v4_' . $file['name'] . '"');
        echo $encrypted;
        exit;
    }
    echo <<<HTML
    <!DOCTYPE html>
    <html><head><title>PHP Encryptor V4 (Debug)</title></head>
    <body>
        <h2>Upload file PHP - Encrypt V4 By Dkid03</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="php_file" accept=".php" required>
            <label><input type="checkbox" name="debug" value="1"> Aktifkan mode debug (log ke file)</label><br><br>
            <button type="submit">Encrypt</button>
        </form>
    </body></html>
    HTML;
}

function encryptPhpCode($plain, $debugMode) {
    // 1. Kompresi
    $compressed = gzcompress($plain, COMPRESSION_LEVEL);
    if ($compressed === false) die("Kompresi gagal.");

    // 2. Kunci AES acak
    $key = random_bytes(32);

    // 3. Enkripsi AES
    $iv = random_bytes(openssl_cipher_iv_length(ENCRYPTION_ALGO));
    $ciphertext = openssl_encrypt($compressed, ENCRYPTION_ALGO, $key, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) die("Enkripsi AES gagal.");

    // 4. Gabungkan IV + ciphertext
    $payload = $iv . $ciphertext;
    $encodedPayload = base64_encode($payload);

    // 5. Obfuskasi kunci (sederhana)
    $obfuscatedKey = base64_encode($key);

    // 6. Generate kode output dengan debug
    $outputCode = generateOutputCode($encodedPayload, $obfuscatedKey, $debugMode);

    return "<?php\n" . $outputCode . "\n?>";
}

function generateOutputCode($payload, $obfKey, $debug) {
    // Nama fungsi acak
    $func1 = 'd' . bin2hex(random_bytes(4));
    $func2 = 'e' . bin2hex(random_bytes(5));

    // Kode debug log
    $logFunction = '';
    $logCalls = '';
    if ($debug) {
        $logFunction = '
function _debug_log($msg) {
    @file_put_contents(__DIR__ . "/debug_encrypt.log", date("Y-m-d H:i:s") . " - " . $msg . "\n", FILE_APPEND);
}
';
        $logCalls = '_debug_log("Memulai dekripsi");';
    } else {
        $logFunction = 'function _debug_log($msg) {}';
    }

    $code = '
/***********************************************
* ENCRYPTED BY PHP ENCRYPTOR V4
***********************************************/
' . $logFunction . '

// Fungsi dekripsi
function ' . $func1 . '($data, $key) {
    _debug_log("Memanggil ' . $func1 . '");
    $raw = base64_decode($data);
    $ivLen = openssl_cipher_iv_length("' . ENCRYPTION_ALGO . '");
    $iv = substr($raw, 0, $ivLen);
    $cipher = substr($raw, $ivLen);
    $decrypted = openssl_decrypt($cipher, "' . ENCRYPTION_ALGO . '", base64_decode($key), OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) {
        _debug_log("Dekripsi AES gagal");
        return false;
    }
    $original = gzuncompress($decrypted);
    if ($original === false) {
        _debug_log("Dekompresi gagal");
        return false;
    }
    _debug_log("Dekripsi sukses, panjang kode: " . strlen($original));
    return $original;
}

// Eksekusi
_debug_log("========== START ==========");
$originalCode = ' . $func1 . '("' . $payload . '", "' . $obfKey . '");
if ($originalCode !== false) {
    _debug_log("Menjalankan eval...");
    ob_start();
    eval("?>" . $originalCode . "<?php ");
    $output = ob_get_clean();
    echo $output;
    _debug_log("Eval selesai");
} else {
    _debug_log("Gagal mendapatkan kode asli");
    echo "Error: Gagal mendekripsi.";
}
_debug_log("========== END ==========");
';
    return $code;
}