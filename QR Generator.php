<?php
// Tangani proses pembuatan QR code
$qrImage = '';
$downloadLink = '';
$qrData = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qrData = $_POST['qr_data'] ?? '';
    if (!empty($qrData)) {
        $encodedData = urlencode($qrData);
        $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data={$encodedData}&format=png";
        $downloadLink = $qrImage;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Generator - Iqyan Tech Tools</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #3498db;
            --primary-glow: rgba(52, 152, 219, 0.4);
            --dark-bg: #121212;
            --card-bg: #1e1e1e;
            --text-primary: #f5f5f5;
            --text-secondary: #b0b0b0;
            --success: #2ecc71;
            --iqyan-blue: #1a73e8;
        }

        body {
            background: var(--dark-bg);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 20px;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(52, 152, 219, 0.05) 0%, transparent 55%),
                radial-gradient(circle at 75% 75%, rgba(52, 152, 219, 0.05) 0%, transparent 55%);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            width: 100%;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(90deg, var(--primary), #8e44ad);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
            position: relative;
            display: inline-block;
        }

        .header h1::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), #8e44ad);
            bottom: -10px;
            left: 0;
            border-radius: 4px;
        }

        .header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 500px;
            margin: 20px auto 0;
            line-height: 1.6;
        }

        .card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 30px;
            width: 100%;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: 0 0 20px 5px var(--primary-glow);
            transform: translateY(-5px);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.1rem;
        }

        .input-field {
            width: 100%;
            padding: 14px 20px;
            border-radius: 12px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            background: rgba(30, 30, 30, 0.7);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), #2980b9);
            color: white;
            border: none;
            padding: 14px 28px;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.5);
        }

        .btn:active {
            transform: translateY(1px);
        }

        .qr-container {
            margin-top: 30px;
            text-align: center;
            animation: fadeIn 0.5s ease;
        }

        .qr-placeholder {
            background: rgba(30, 30, 30, 0.5);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 1.2rem;
        }

        .qr-result {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .qr-image {
            max-width: 300px;
            width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 0 20px 5px var(--primary-glow);
            border: 1px solid rgba(52, 152, 219, 0.3);
            transition: all 0.3s ease;
        }

        .qr-image:hover {
            transform: scale(1.03);
            box-shadow: 0 0 30px 8px var(--primary-glow);
        }

        .iqyan-brand {
            font-size: 1.4rem;
            font-weight: bold;
            background: linear-gradient(90deg, var(--iqyan-blue), #3498db);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 10px 0;
            letter-spacing: 1px;
        }

        .download-btn {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            padding: 12px 25px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.5);
        }

        .footer {
            text-align: center;
            margin-top: 50px;
            padding: 20px;
            color: var(--text-secondary);
            font-size: 0.9rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
        }

        .instructions {
            background: rgba(52, 152, 219, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
            color: var(--text-primary);
            border-left: 4px solid var(--primary);
            font-size: 1rem;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 20px;
            }
            
            .btn {
                width: 100%;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>QR Code Generator</h1>
            <p>Alat praktis untuk membuat QR Code dari teks atau URL apapun</p>
        </div>
        
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label for="qr_data">Masukkan Teks/Link:</label>
                    <input 
                        type="text" 
                        id="qr_data" 
                        name="qr_data" 
                        class="input-field" 
                        placeholder="Contoh: Iqyan.com atau teks apa saja..."
                        required
                        value="<?php echo htmlspecialchars($qrData); ?>"
                    >
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-qrcode"></i>
                    Generate QR Code
                </button>
            </form>
            
            <div class="instructions">
                <strong>Petunjuk:</strong> Masukkan teks, URL, atau data apa pun ke dalam kotak input di atas, lalu klik tombol "Generate QR Code" untuk membuat QR Code. QR code yang dihasilkan dapat diunduh dan akan menampilkan brand "Iqyan Tech".
            </div>
            
            <div class="qr-container">
                <?php if (!empty($qrImage)): ?>
                    <div class="qr-result">
                        <img src="<?php echo $qrImage; ?>" alt="Generated QR Code" class="qr-image">
                        <div class="iqyan-brand">IQYAN TECH</div>
                        <a href="<?php echo $downloadLink; ?>" download="iqyan-tech-qr.png" class="download-btn">
                            <i class="fas fa-download"></i> Download QR Code
                        </a>
                    </div>
                <?php else: ?>
                    <div class="qr-placeholder">
                        <div>
                            <i class="fas fa-qrcode" style="font-size: 64px; margin-bottom: 20px; color: var(--primary);"></i>
                            <p>QR Code akan muncul di sini</p>
                            <div class="iqyan-brand" style="margin-top: 20px;">IQYAN TECH</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            Iqyan Tech Tools - QR Code 
        </div>
    </div>
    
    <script>
        // Animasi tombol
        const buttons = document.querySelectorAll('.btn, .download-btn');
        buttons.forEach(button => {
            button.addEventListener('click', function() {
                this.classList.add('clicked');
                setTimeout(() => {
                    this.classList.remove('clicked');
                }, 300);
            });
        });
    </script>
</body>
</html>
