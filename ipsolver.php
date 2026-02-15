<?php
function calculateSubnet() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
    
    $ip = $_POST['ip'] ?? '';
    $subnetMask = $_POST['subnet_mask'] ?? '';
    $subnetNumber = $_POST['subnet_number'] ?? 1;
    
    // Validasi input
    if (!filter_var($ip, FILTER_VALIDATE_IP) || 
        !filter_var($subnetMask, FILTER_VALIDATE_IP) || 
        !is_numeric($subnetNumber) || $subnetNumber < 1) {
        return ['error' => 'Input tidak valid! Pastikan format IP dan subnet mask benar'];
    }
    
    $ipParts = explode('.', $ip);
    $maskParts = explode('.', $subnetMask);
    
    // Tentukan kelas IP
    $firstOctet = (int)$ipParts[0];
    if ($firstOctet >= 1 && $firstOctet <= 126) {
        $class = 'A';
        $defaultSubnet = '255.0.0.0';
    } elseif ($firstOctet >= 128 && $firstOctet <= 191) {
        $class = 'B';
        $defaultSubnet = '255.255.0.0';
    } elseif ($firstOctet >= 192 && $firstOctet <= 223) {
        $class = 'C';
        $defaultSubnet = '255.255.255.0';
    } else {
        $class = 'D/E (Special)';
        $defaultSubnet = 'N/A';
    }
    
    // Hitung CIDR
    $cidr = 0;
    foreach ($maskParts as $part) {
        $bin = decbin((int)$part);
        $cidr += substr_count($bin, '1');
    }
    
    // Hitung jumlah subnet
    $defaultCidr = 0;
    switch ($class) {
        case 'A': $defaultCidr = 8; break;
        case 'B': $defaultCidr = 16; break;
        case 'C': $defaultCidr = 24; break;
        default: $defaultCidr = 0;
    }
    
    $subnetBits = $cidr - $defaultCidr;
    $subnetCount = pow(2, $subnetBits);
    
    // Hitung host per subnet
    $hostBits = 32 - $cidr;
    $hostsPerSubnet = pow(2, $hostBits) - 2;
    
    // Hitung network address
    $networkLong = ip2long($ip) & ip2long($subnetMask);
    $increment = pow(2, $hostBits);
    $targetNetwork = $networkLong + (($subnetNumber - 1) * $increment);
    
    $networkAddress = long2ip($targetNetwork);
    $broadcastAddress = long2ip($targetNetwork + $increment - 1);
    $firstHost = long2ip($targetNetwork + 1);
    $lastHost = long2ip($targetNetwork + $increment - 2);
    
    // Hasil akhir
    return [
        'class' => $class,
        'defaultSubnet' => $defaultSubnet,
        'cidr' => $cidr,
        'subnetBits' => $subnetBits,
        'subnetCount' => $subnetCount,
        'hostBits' => $hostBits,
        'hostsPerSubnet' => $hostsPerSubnet,
        'subnetNumber' => $subnetNumber,
        'networkAddress' => $networkAddress,
        'firstHost' => $firstHost,
        'lastHost' => $lastHost,
        'broadcastAddress' => $broadcastAddress,
        'ip' => $ip,
        'subnetMask' => $subnetMask
    ];
}

$result = calculateSubnet();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soal IP Subnet - Iqyan Tech Tools</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            color: #f5f5f5;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            padding: 30px 0;
            margin-bottom: 20px;
            position: relative;
        }
        
        .logo {
            position: absolute;
            top: 20px;
            left: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            font-size: 1.2rem;
            color: #4dabf7;
        }
        
        h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(77, 171, 247, 0.5);
            background: linear-gradient(to right, #4dabf7, #64d0ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }
        
        h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 4px;
            background: linear-gradient(to right, #4dabf7, #64d0ff);
            border-radius: 2px;
        }
        
        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 20px auto 0;
            line-height: 1.6;
            color: #a0d2ff;
        }
        
        .card {
            background: rgba(30, 30, 46, 0.7);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(77, 171, 247, 0.2);
        }
        
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #64d0ff;
        }
        
        .card-title i {
            color: #4dabf7;
        }
        
        .input-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .input-container {
            position: relative;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #a0d2ff;
        }
        
        input, select {
            width: 100%;
            padding: 14px;
            border-radius: 10px;
            border: 2px solid rgba(77, 171, 247, 0.3);
            background: rgba(10, 15, 35, 0.5);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        input:focus, select:focus {
            border-color: #4dabf7;
            outline: none;
            background: rgba(10, 15, 35, 0.7);
            box-shadow: 0 0 0 3px rgba(77, 171, 247, 0.3);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }
        
        button {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(to right, #4dabf7, #339af0);
            color: white;
            box-shadow: 0 4px 15px rgba(77, 171, 247, 0.4);
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(77, 171, 247, 0.6);
            background: linear-gradient(to right, #339af0, #4dabf7);
        }
        
        .results {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .result-card {
            background: rgba(20, 25, 45, 0.7);
            border-radius: 12px;
            padding: 20px;
            transition: transform 0.3s ease;
            border: 1px solid rgba(77, 171, 247, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .result-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, #4dabf7, #64d0ff);
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            background: rgba(25, 30, 50, 0.8);
        }
        
        .result-title {
            font-size: 1rem;
            color: #a0d2ff;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .result-value {
            font-size: 1.5rem;
            font-weight: 600;
            word-break: break-all;
            color: #4dabf7;
        }
        
        .explanation {
            background: rgba(20, 25, 45, 0.7);
            border-radius: 12px;
            padding: 20px;
            margin-top: 30px;
            border: 1px solid rgba(77, 171, 247, 0.2);
        }
        
        .explanation h3 {
            color: #64d0ff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .explanation ul {
            padding-left: 20px;
        }
        
        .explanation li {
            margin-bottom: 10px;
            line-height: 1.6;
            color: #c0d6ff;
        }
        
        .final-result {
            background: linear-gradient(to right, rgba(77, 171, 247, 0.2), rgba(100, 208, 255, 0.2));
            border-radius: 12px;
            padding: 25px;
            margin-top: 30px;
            text-align: center;
            border: 1px solid rgba(77, 171, 247, 0.3);
        }
        
        .final-result h3 {
            color: #64d0ff;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        
        .final-answer {
            font-size: 1.8rem;
            font-weight: bold;
            color: #4dabf7;
            text-shadow: 0 0 10px rgba(77, 171, 247, 0.5);
        }
        
        footer {
            text-align: center;
            padding: 30px 0;
            margin-top: 40px;
            color: rgba(160, 210, 255, 0.7);
            font-size: 1rem;
            width: 100%;
            border-top: 1px solid rgba(77, 171, 247, 0.2);
        }
        
        .error {
            background: rgba(255, 77, 77, 0.2);
            border: 1px solid rgba(255, 77, 77, 0.5);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #ffa0a0;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            h1 {
                font-size: 2.2rem;
            }
            
            .input-group {
                grid-template-columns: 1fr;
            }
            
            .logo {
                position: static;
                justify-content: center;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <i class="fas fa-network-wired"></i>
                <span>IQYAN TECH TOOLS</span>
            </div>
            <h1>Soal IP Subnet Solver</h1>
            <p class="subtitle">Alat untuk membantu siswa TKJ memahami dan menyelesaikan soal perhitungan subnet IP address dengan penjelasan langkah demi langkah</p>
        </header>
        
        <main>
            <div class="card">
                <h2 class="card-title"><i class="fas fa-calculator"></i> Input Data Subnet</h2>
                
                <form method="post">
                    <div class="input-group">
                        <div class="input-container">
                            <label for="ip">Alamat IPv4</label>
                            <input type="text" id="ip" name="ip" placeholder="Contoh: 192.168.10.0" 
                                   value="<?= isset($_POST['ip']) ? htmlspecialchars($_POST['ip']) : '192.168.10.0' ?>">
                        </div>
                        
                        <div class="input-container">
                            <label for="subnet_mask">Subnet Mask</label>
                            <input type="text" id="subnet_mask" name="subnet_mask" placeholder="Contoh: 255.255.255.224" 
                                   value="<?= isset($_POST['subnet_mask']) ? htmlspecialchars($_POST['subnet_mask']) : '255.255.255.224' ?>">
                        </div>
                        
                        <div class="input-container">
                            <label for="subnet_number">Subnet ke-berapa</label>
                            <input type="number" id="subnet_number" name="subnet_number" min="1" 
                                   value="<?= isset($_POST['subnet_number']) ? htmlspecialchars($_POST['subnet_number']) : '1' ?>">
                        </div>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit">
                            <i class="fas fa-calculator"></i> Hitung Jawaban
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if ($result): ?>
                <?php if (isset($result['error'])): ?>
                    <div class="error">
                        <i class="fas fa-exclamation-triangle"></i> <?= $result['error'] ?>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-chart-bar"></i> Hasil Perhitungan</h2>
                        
                        <div class="results">
                            <div class="result-card">
                                <div class="result-title">Kelas IP</div>
                                <div class="result-value">Kelas <?= $result['class'] ?></div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Jumlah Subnet</div>
                                <div class="result-value"><?= $result['subnetCount'] ?> subnet</div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Host per Subnet</div>
                                <div class="result-value"><?= $result['hostsPerSubnet'] ?> host</div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Network Address</div>
                                <div class="result-value"><?= $result['networkAddress'] ?></div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Host Pertama</div>
                                <div class="result-value"><?= $result['firstHost'] ?></div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Host Terakhir</div>
                                <div class="result-value"><?= $result['lastHost'] ?></div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">Broadcast Address</div>
                                <div class="result-value"><?= $result['broadcastAddress'] ?></div>
                            </div>
                            
                            <div class="result-card">
                                <div class="result-title">CIDR Notation</div>
                                <div class="result-value">/<?= $result['cidr'] ?></div>
                            </div>
                        </div>
                        
                        <div class="explanation">
                            <h3><i class="fas fa-lightbulb"></i> Penjelasan Perhitungan</h3>
                            <ul>
                                <li><strong>Kelas <?= $result['class'] ?></strong>: Alamat IP <?= $result['ip'] ?> termasuk kelas <?= $result['class'] ?> (Subnet mask default: <?= $result['defaultSubnet'] ?>)</li>
                                <li><strong>Subnet Bits</strong>: Subnet mask <?= $result['subnetMask'] ?> (/<?= $result['cidr'] ?>) memiliki <?= $result['subnetBits'] ?> bit subnet (<?= $result['cidr'] ?> - <?= $result['class'] == 'A' ? 8 : ($result['class'] == 'B' ? 16 : 24) ?> = <?= $result['subnetBits'] ?>)</li>
                                <li><strong>Jumlah Subnet</strong>: 2<sup><?= $result['subnetBits'] ?></sup> = <?= $result['subnetCount'] ?> subnet</li>
                                <li><strong>Host Bits</strong>: Terdapat <?= $result['hostBits'] ?> bit host (32 - <?= $result['cidr'] ?> = <?= $result['hostBits'] ?>)</li>
                                <li><strong>Host per Subnet</strong>: 2<sup><?= $result['hostBits'] ?></sup> - 2 = <?= $result['hostsPerSubnet'] ?> host yang dapat digunakan</li>
                                <li><strong>Network Address</strong>: Alamat network untuk subnet ke-<?= $result['subnetNumber'] ?> adalah <?= $result['networkAddress'] ?></li>
                                <li><strong>Rentang IP</strong>: Host pertama adalah <?= $result['firstHost'] ?> dan host terakhir adalah <?= $result['lastHost'] ?></li>
                            </ul>
                        </div>
                        
                        <div class="final-result">
                            <h3><i class="fas fa-check-circle"></i> Jawaban Akhir</h3>
                            <p class="final-answer">
                                Untuk IP <?= $result['ip'] ?> dengan subnet mask <?= $result['subnetMask'] ?>, 
                                subnet ke-<?= $result['subnetNumber'] ?> memiliki network address <?= $result['networkAddress'] ?> 
                                dengan rentang host <?= $result['firstHost'] ?> - <?= $result['lastHost'] ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
        
        <footer>
            <p>Iqyan Tech Tools &copy; <?= date('Y') ?> | Alat untuk membantu siswa TKJ memahami subnetting IP</p>
        </footer>
    </div>
</body>
</html>