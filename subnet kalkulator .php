<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Subnet Calculator</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #1a2a6c);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 800px;
            padding: 40px;
            text-align: center;
        }
        
        h1 {
            color: #1a2a6c;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .subtitle {
            color: #555;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        .input-container {
            display: flex;
            flex-direction: column;
            margin-bottom: 30px;
            background: #f0f5ff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        label {
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a2a6c;
            font-size: 1.1rem;
        }
        
        input {
            padding: 15px;
            border: 2px solid #ccc;
            border-radius: 8px;
            font-size: 1.2rem;
            text-align: center;
            transition: border-color 0.3s;
        }
        
        input:focus {
            border-color: #1a2a6c;
            outline: none;
            box-shadow: 0 0 8px rgba(26, 42, 108, 0.3);
        }
        
        button {
            background: linear-gradient(to right, #1a2a6c, #3a5fcb);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        button:hover {
            background: linear-gradient(to right, #3a5fcb, #1a2a6c);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .results {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .result-box {
            background: #e6f0ff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .result-box:hover {
            transform: translateY(-5px);
        }
        
        .result-box h3 {
            color: #1a2a6c;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .result-value {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1a2a6c;
            word-break: break-all;
        }
        
        .examples {
            margin-top: 30px;
            background: #f0f5ff;
            padding: 20px;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        
        .examples h3 {
            color: #1a2a6c;
            margin-bottom: 10px;
        }
        
        .examples p {
            color: #555;
            line-height: 1.6;
        }
        
        .error {
            color: #e74c3c;
            font-weight: 600;
            margin-top: 15px;
            padding: 10px;
            border-radius: 5px;
            background: #ffebee;
            display: none;
        }
        
        .footer {
            margin-top: 30px;
            color: #777;
            font-size: 0.9rem;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 25px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            input {
                padding: 12px;
                font-size: 1rem;
            }
            
            button {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .result-value {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>IP Subnet Calculator</h1>
        <p class="subtitle">Hitung network address, broadcast, range host, dan jumlah host</p>
        
        <div class="input-container">
            <label for="ipInput">Masukkan IP Address dan Subnet Mask (Format: 192.168.1.1/24)</label>
            <input type="text" id="ipInput" placeholder="Contoh: 192.168.1.1/24" autofocus>
            <div id="error" class="error"></div>
            <button onclick="calculate()">Hitung Subnet</button>
        </div>
        
        <div class="results">
            <div class="result-box">
                <h3>Network Address</h3>
                <div id="network" class="result-value">-</div>
            </div>
            
            <div class="result-box">
                <h3>Broadcast Address</h3>
                <div id="broadcast" class="result-value">-</div>
            </div>
            
            <div class="result-box">
                <h3>First Host</h3>
                <div id="firstHost" class="result-value">-</div>
            </div>
            
            <div class="result-box">
                <h3>Last Host</h3>
                <div id="lastHost" class="result-value">-</div>
            </div>
            
            <div class="result-box">
                <h3>Jumlah Host</h3>
                <div id="numHosts" class="result-value">-</div>
            </div>
            
            <div class="result-box">
                <h3>Subnet Mask</h3>
                <div id="subnetMask" class="result-value">-</div>
            </div>
        </div>
        
        <div class="examples">
            <h3>Contoh Input Valid:</h3>
            <p>• 192.168.1.1/24<br>
               • 10.0.0.5/8<br>
               • 172.16.0.1/16<br>
               • 192.168.10.50/28</p>
        </div>
        
        <div class="footer">
            <p>© 2025 IP Subnet Calculator | Berfungsi untuk IPv4</p>
        </div>
    </div>

    <script>
        function calculate() {
            // Reset error message
            document.getElementById('error').style.display = 'none';
            
            // Get input value
            const input = document.getElementById('ipInput').value.trim();
            
            // Validate input format
            const regex = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/;
            const match = input.match(regex);
            
            if (!match) {
                showError('Format input tidak valid. Gunakan format: IP/subnet (contoh: 192.168.1.1/24)');
                return;
            }
            
            // Extract IP parts and subnet prefix
            const ipParts = match.slice(1, 5).map(Number);
            const prefix = parseInt(match[5]);
            
            // Validate IP parts
            if (ipParts.some(part => part < 0 || part > 255)) {
                showError('Setiap bagian IP harus antara 0 dan 255');
                return;
            }
            
            // Validate prefix
            if (prefix < 0 || prefix > 32) {
                showError('Subnet mask harus antara 0 dan 32');
                return;
            }
            
            // Calculate subnet mask
            const subnetMask = calculateSubnetMask(prefix);
            
            // Calculate network address
            const networkAddress = calculateNetworkAddress(ipParts, subnetMask);
            
            // Calculate broadcast address
            const broadcastAddress = calculateBroadcastAddress(networkAddress, prefix);
            
            // Calculate first and last host
            const firstHost = calculateFirstHost(networkAddress);
            const lastHost = calculateLastHost(broadcastAddress);
            
            // Calculate number of hosts
            const numHosts = calculateNumHosts(prefix);
            
            // Display results
            document.getElementById('network').textContent = formatIP(networkAddress);
            document.getElementById('broadcast').textContent = formatIP(broadcastAddress);
            document.getElementById('firstHost').textContent = formatIP(firstHost);
            document.getElementById('lastHost').textContent = formatIP(lastHost);
            document.getElementById('numHosts').textContent = numHosts.toLocaleString();
            document.getElementById('subnetMask').textContent = formatIP(subnetMask);
        }
        
        function calculateSubnetMask(prefix) {
            let mask = 0;
            for (let i = 0; i < prefix; i++) {
                mask |= (1 << (31 - i));
            }
            
            return [
                (mask >> 24) & 0xFF,
                (mask >> 16) & 0xFF,
                (mask >> 8) & 0xFF,
                mask & 0xFF
            ];
        }
        
        function calculateNetworkAddress(ip, subnetMask) {
            return [
                ip[0] & subnetMask[0],
                ip[1] & subnetMask[1],
                ip[2] & subnetMask[2],
                ip[3] & subnetMask[3]
            ];
        }
        
        function calculateBroadcastAddress(networkAddress, prefix) {
            const hostBits = 32 - prefix;
            const broadcast = [];
            
            // Convert network address to a single number
            const netNum = (networkAddress[0] << 24) | 
                          (networkAddress[1] << 16) | 
                          (networkAddress[2] << 8) | 
                          networkAddress[3];
            
            // Calculate broadcast address
            const broadcastNum = netNum | ((1 << hostBits) - 1);
            
            return [
                (broadcastNum >> 24) & 0xFF,
                (broadcastNum >> 16) & 0xFF,
                (broadcastNum >> 8) & 0xFF,
                broadcastNum & 0xFF
            ];
        }
        
        function calculateFirstHost(networkAddress) {
            const firstHost = [...networkAddress];
            // For /31 and /32 networks, first host is the same as network address
            firstHost[3] += 1;
            
            // Handle overflow
            for (let i = 3; i >= 0; i--) {
                if (firstHost[i] > 255) {
                    firstHost[i] = 0;
                    if (i > 0) firstHost[i-1]++;
                } else {
                    break;
                }
            }
            
            return firstHost;
        }
        
        function calculateLastHost(broadcastAddress) {
            const lastHost = [...broadcastAddress];
            // For /31 and /32 networks, last host is the same as broadcast address
            lastHost[3] -= 1;
            
            // Handle underflow
            for (let i = 3; i >= 0; i--) {
                if (lastHost[i] < 0) {
                    lastHost[i] = 255;
                    if (i > 0) lastHost[i-1]--;
                } else {
                    break;
                }
            }
            
            return lastHost;
        }
        
        function calculateNumHosts(prefix) {
            if (prefix >= 31) return 2; // Special case for /31 and /32
            return Math.pow(2, 32 - prefix) - 2;
        }
        
        function formatIP(ipArray) {
            return ipArray.join('.');
        }
        
        function showError(message) {
            const errorElement = document.getElementById('error');
            errorElement.textContent = message;
            errorElement.style.display = 'block';
            
            // Clear results
            document.querySelectorAll('.result-value').forEach(el => {
                el.textContent = '-';
            });
        }
        
        // Add event listener for Enter key
        document.getElementById('ipInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                calculate();
            }
        });
    </script>
</body>
</html>