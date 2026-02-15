<?php
// Konfigurasi
session_start();
$uploadDir = 'uploads/'; // Folder penyimpanan
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'text/html']; // Jenis file yang diizinkan
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'html']; // Ekstensi file yang diizinkan
$maxSize = 5 * 1024 * 1024; // 5MB

// Buat folder jika belum ada
if (!file_exists($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true)) {
        die("Gagal membuat folder upload");
    }
}

$message = '';
$fileLink = '';
$fileName = '';

// Proses upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Permintaan tidak valid. Silakan coba lagi.';
    } 
    else if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        
        // Validasi error
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = 'Error uploading file. Code: ' . $file['error'];
        } 
        // Validasi tipe file
        elseif (!in_array($file['type'], $allowedTypes)) {
            $message = 'Hanya file gambar (JPG, PNG, GIF) dan HTML yang diizinkan.';
        } 
        // Validasi ukuran
        elseif ($file['size'] > $maxSize) {
            $message = 'Ukuran file terlalu besar. Maksimal 5MB.';
        } 
        // Validasi ekstensi file
        else {
            $originalName = basename($file['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                $message = 'Ekstensi file tidak diizinkan.';
            } else {
                $safeName = uniqid('file_', true) . '.' . $extension;
                $targetPath = $uploadDir . $safeName;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Perbaikan path untuk link
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
                    $fileLink = $baseUrl . str_replace('//', '/', $scriptPath . '/' . $uploadDir . $safeName);
                    $fileName = $originalName;
                } else {
                    $message = 'Gagal menyimpan file.';
                }
            }
        }
    } else {
        $message = 'Tidak ada file yang dipilih.';
    }
}

// Generate CSRF token
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uploader by Tn.Iqyan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            color: #fff;
        }
        
        .container {
            width: 100%;
            max-width: 500px;
            background: rgba(0, 0, 0, 0.6);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            z-index: -1;
        }
        
        .header {
            margin-bottom: 30px;
            position: relative;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #00dbde, #fc00ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            text-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.8;
            color: #a0a0e0;
        }
        
        .upload-icon {
            font-size: 80px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #00dbde, #fc00ff);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
        }
        
        .upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            padding: 30px 20px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.4s;
            position: relative;
        }
        
        .upload-area:hover {
            border-color: #00dbde;
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .upload-text h3 {
            font-size: 1.3rem;
            margin-bottom: 15px;
            color: #fff;
        }
        
        .file-input {
            display: none;
        }
        
        .file-label {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(45deg, #00dbde, #fc00ff);
            color: white;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .file-label::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .file-label:hover::before {
            left: 100%;
        }
        
        .file-label:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }
        
        .upload-btn {
            background: linear-gradient(45deg, #fc00ff, #00dbde);
            border: none;
            padding: 15px 40px;
            font-size: 1.1rem;
            color: white;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
            transition: all 0.4s;
            width: 100%;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .upload-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .upload-btn:hover::before {
            left: 100%;
        }
        
        .upload-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }
        
        .result-area {
            margin-top: 30px;
            padding: 25px;
            border-radius: 15px;
            background: rgba(0, 0, 0, 0.4);
            display: none;
            border: 1px solid rgba(0, 219, 222, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .success-icon {
            font-size: 50px;
            color: #00ff9d;
            margin-bottom: 15px;
            text-shadow: 0 0 15px rgba(0, 255, 157, 0.5);
        }
        
        .file-link {
            background: rgba(255, 255, 255, 0.08);
            padding: 15px;
            border-radius: 10px;
            word-break: break-all;
            margin: 20px 0;
            text-align: left;
            border-left: 3px solid #00dbde;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .file-link:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateX(5px);
        }
        
        .file-link a {
            color: #00dbde;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .file-link a:hover {
            color: #fc00ff;
            text-decoration: underline;
        }
        
        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.95rem;
            margin-top: 10px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            display: none;
            font-size: 0.95rem;
            background: rgba(255, 0, 0, 0.15);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff6b6b;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .info {
            font-size: 0.85rem;
            opacity: 0.7;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #a0a0e0;
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .floating {
            animation: float 3s ease-in-out infinite;
        }
        
        @media (max-width: 600px) {
            .container {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .upload-icon {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="container floating">
        <div class="header">
            <div class="upload-icon pulse">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h1>Uploader by Tn.Iqyan</h1>
            <p>Unggah file HTML dan gambar dengan cepat dan aman</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            
            <div class="upload-area" id="uploadArea">
                <div class="upload-text">
                    <h3>Seret & Lepaskan File Anda</h3>
                    <label for="fileInput" class="file-label">
                        <i class="fas fa-folder-open"></i> Pilih File
                    </label>
                    <input type="file" name="file" id="fileInput" class="file-input" accept=".jpg,.jpeg,.png,.gif,.html" required>
                    <p id="fileName" style="margin-top: 15px; font-size: 0.95rem; color: #a0a0e0;"></p>
                </div>
            </div>
            
            <button type="submit" class="upload-btn">
                <i class="fas fa-upload"></i> Upload File Sekarang
            </button>
        </form>
        
        <?php if ($message): ?>
            <div class="message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($fileLink): ?>
            <div class="result-area" id="resultArea">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3 style="margin-bottom: 15px; color: #00ff9d;">Upload Berhasil!</h3>
                
                <div class="file-link">
                    <a href="<?= htmlspecialchars($fileLink) ?>" target="_blank" id="fileLink">
                        <?= htmlspecialchars($fileLink) ?>
                    </a>
                </div>
                
                <button class="copy-btn" onclick="copyLink()">
                    <i class="fas fa-copy"></i> Salin Link
                </button>
            </div>
        <?php endif; ?>
        
        <div class="info">
            <p><i class="fas fa-shield-alt"></i> File Anda dijamin aman dan terlindungi</p>
            <p><i class="fas fa-info-circle"></i> Hanya file HTML dan gambar (JPG, PNG, GIF) yang diizinkan. Ukuran maksimal: 5MB</p>
        </div>
    </div>

    <script>
        // Tampilkan nama file yang dipilih
        document.getElementById('fileInput').addEventListener('change', function(e) {
            if (this.files.length > 0) {
                document.getElementById('fileName').textContent = 'File terpilih: ' + this.files[0].name;
            }
        });
        
        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = '#00dbde';
            uploadArea.style.backgroundColor = 'rgba(0, 219, 222, 0.1)';
            uploadArea.style.transform = 'scale(1.02)';
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.style.borderColor = 'rgba(255, 255, 255, 0.3)';
            uploadArea.style.backgroundColor = 'rgba(255, 255, 255, 0.05)';
            uploadArea.style.transform = 'scale(1)';
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.style.borderColor = 'rgba(255, 255, 255, 0.3)';
            uploadArea.style.backgroundColor = 'rgba(255, 255, 255, 0.05)';
            uploadArea.style.transform = 'scale(1)';
            
            if (e.dataTransfer.files.length) {
                document.getElementById('fileInput').files = e.dataTransfer.files;
                document.getElementById('fileName').textContent = 'File terpilih: ' + e.dataTransfer.files[0].name;
            }
        });
        
        // Tampilkan hasil jika ada
        <?php if ($fileLink): ?>
            document.getElementById('resultArea').style.display = 'block';
        <?php endif; ?>
        
        // Tampilkan pesan error jika ada
        <?php if ($message): ?>
            document.getElementById('errorMessage').style.display = 'block';
        <?php endif; ?>
        
        // Fungsi untuk menyalin link
        function copyLink() {
            const link = document.getElementById('fileLink').href;
            navigator.clipboard.writeText(link)
                .then(() => {
                    const btn = document.querySelector('.copy-btn');
                    btn.innerHTML = '<i class="fas fa-check"></i> Link Tersalin!';
                    btn.style.background = 'rgba(0, 219, 222, 0.3)';
                    
                    setTimeout(() => {
                        btn.innerHTML = '<i class="fas fa-copy"></i> Salin Link';
                        btn.style.background = 'rgba(255, 255, 255, 0.1)';
                    }, 2000);
                })
                .catch(err => {
                    console.error('Gagal menyalin: ', err);
                });
        }
    </script>
</body>
</html>