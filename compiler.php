<?php
// Fungsi untuk menjalankan kode C++
function run_cpp($code) {
    if (strlen($code) > 10000) return "Error: Kode terlalu panjang (maks 10,000 karakter)";
    
    $filename = tempnam(sys_get_temp_dir(), 'cpp_');
    $source_file = $filename . '.cpp';
    $executable = $filename;
    
    file_put_contents($source_file, $code);
    
    $compile = shell_exec("g++ {$source_file} -o {$executable} 2>&1");
    
    if (!empty($compile)) {
        unlink($source_file);
        return "Compilation Error:\n" . $compile;
    }
    
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open("timeout 5s {$executable}", $descriptors, $pipes);
    
    if (!is_resource($process)) {
        unlink($source_file);
        unlink($executable);
        return "Error: Tidak dapat menjalankan program";
    }
    
    fclose($pipes[0]);
    
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    unlink($source_file);
    unlink($executable);
    
    if ($return_value !== 0) {
        return "Runtime Error:\n" . $error;
    }
    
    return $output;
}

// Fungsi untuk menjalankan kode Python
function run_python($code) {
    if (strlen($code) > 10000) return "Error: Kode terlalu panjang (maks 10,000 karakter)";
    
    $filename = tempnam(sys_get_temp_dir(), 'py_');
    $source_file = $filename . '.py';
    
    file_put_contents($source_file, $code);
    
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open("timeout 5s python3 {$source_file}", $descriptors, $pipes);
    
    if (!is_resource($process)) {
        unlink($source_file);
        return "Error: Tidak dapat menjalankan program";
    }
    
    fclose($pipes[0]);
    
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    unlink($source_file);
    
    if ($return_value !== 0) {
        return "Error:\n" . $error;
    }
    
    return $output;
}

// Fungsi untuk menjalankan kode PHP
function run_php($code) {
    if (strlen($code) > 10000) return "Error: Kode terlalu panjang (maks 10,000 karakter)";
    
    $filename = tempnam(sys_get_temp_dir(), 'php_');
    $source_file = $filename . '.php';
    
    file_put_contents($source_file, "<?php\n" . $code);
    
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];
    
    $process = proc_open("timeout 5s php {$source_file}", $descriptors, $pipes);
    
    if (!is_resource($process)) {
        unlink($source_file);
        return "Error: Tidak dapat menjalankan program";
    }
    
    fclose($pipes[0]);
    
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    
    $return_value = proc_close($process);
    
    unlink($source_file);
    
    if ($return_value !== 0) {
        return "Error:\n" . $error;
    }
    
    return $output;
}

// Inisialisasi variabel
$languages = ['html' => 'HTML/CSS/JS', 'cpp' => 'C++', 'python' => 'Python', 'php' => 'PHP'];
$current_language = $_POST['language'] ?? 'html';
$code = $_POST['code'] ?? '';
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    switch ($current_language) {
        case 'cpp': $output = run_cpp($code); break;
        case 'python': $output = run_python($code); break;
        case 'php': $output = run_php($code); break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iqyan Tech - Online Compiler</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #1e1e2e;
            --bg-secondary: #252538;
            --bg-tertiary: #2d2d44;
            --text-primary: #f8f8f2;
            --text-secondary: #b4b4cc;
            --accent-primary: #8be9fd;
            --accent-secondary: #50fa7b;
            --accent-warning: #ffb86c;
            --accent-danger: #ff5555;
            --border-radius: 8px;
            --transition: all 0.3s ease;
            --iqyan-blue: #4a8eff;
            --iqyan-purple: #8a4dff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--bg-primary), #2a2139);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
        }

        h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .compiler-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (min-width: 992px) {
            .compiler-container {
                flex-direction: row;
            }
        }

        .editor-section, .preview-section {
            flex: 1;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .preview-section {
            display: flex;
            flex-direction: column;
        }

        .section-header {
            background: var(--bg-tertiary);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .controls {
            display: flex;
            gap: 10px;
        }

        select, button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-family: 'Poppins', sans-serif;
        }

        select:hover, button:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent-primary);
        }

        select:focus, button:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 2px rgba(139, 233, 253, 0.3);
        }

        .run-btn {
            background: var(--accent-secondary);
            color: var(--bg-primary);
            font-weight: bold;
            border: none;
            padding: 8px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .run-btn:hover {
            background: #3fd95c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(80, 250, 123, 0.3);
        }

        .editor-container {
            height: 400px;
            position: relative;
            overflow: hidden;
        }

        textarea {
            width: 100%;
            height: 100%;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            padding: 20px;
            resize: none;
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            line-height: 1.5;
            outline: none;
            tab-size: 4;
        }

        /* Syntax Highlighting */
        .code-editor {
            position: relative;
            height: 100%;
            overflow: auto;
            padding: 20px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 15px;
            line-height: 1.5;
            white-space: pre;
        }

        .code-editor .keyword { color: #ff79c6; }
        .code-editor .string { color: #f1fa8c; }
        .code-editor .comment { color: #6272a4; }
        .code-editor .function { color: #50fa7b; }
        .code-editor .variable { color: #8be9fd; }
        .code-editor .operator { color: #ff79c6; }
        .code-editor .number { color: #bd93f9; }
        .code-editor .tag { color: #ff79c6; }
        .code-editor .attribute { color: #50fa7b; }
        .code-editor .error { color: #ff5555; }

        .preview-container {
            flex: 1;
            padding: 20px;
            overflow: auto;
        }

        .output-container {
            background: var(--bg-tertiary);
            height: 100%;
            border-radius: var(--border-radius);
            padding: 20px;
            overflow: auto;
            font-family: 'JetBrains Mono', monospace;
            white-space: pre-wrap;
            color: var(--text-primary);
        }

        #preview-frame {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            border-radius: var(--border-radius);
        }

        footer {
            text-align: center;
            padding: 30px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 30px;
        }

        .footer-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        .brand {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--iqyan-blue), var(--iqyan-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Poppins', sans-serif;
            letter-spacing: -0.5px;
            position: relative;
            display: inline-block;
        }

        .brand::after {
            content: "Tech";
            position: absolute;
            top: 0;
            left: 0;
            color: transparent;
            background: linear-gradient(90deg, var(--iqyan-purple), var(--accent-primary));
            -webkit-background-clip: text;
            background-clip: text;
            clip-path: polygon(0% 50%, 100% 50%, 100% 100%, 0% 100%);
            animation: brandGlitch 3s infinite;
        }

        @keyframes brandGlitch {
            0%, 100% { clip-path: polygon(0% 50%, 100% 50%, 100% 100%, 0% 100%); }
            50% { clip-path: polygon(0% 30%, 100% 70%, 100% 100%, 0% 100%); }
        }

        .donate-btn {
            background: linear-gradient(90deg, #ff9500, #ff5e62);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-family: 'Poppins', sans-serif;
        }

        .donate-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 94, 98, 0.4);
        }

        .copyright {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .hint {
            background: rgba(139, 233, 253, 0.1);
            border-left: 4px solid var(--accent-primary);
            padding: 15px;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            margin-top: 20px;
            font-size: 0.9rem;
        }

        .hint-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            color: var(--accent-primary);
            font-weight: bold;
        }

        .language-tag {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 5px;
        }

        .cpp-tag { background: rgba(139, 233, 253, 0.2); }
        .python-tag { background: rgba(80, 250, 123, 0.2); }
        .php-tag { background: rgba(189, 147, 249, 0.2); }
        .html-tag { background: rgba(255, 121, 198, 0.2); }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Online Compiler</h1>
            <p class="subtitle">Kompilasi dan jalankan kode secara langsung dengan berbagai bahasa pemrograman</p>
        </header>

        <form method="POST" class="compiler-container">
            <div class="editor-section">
                <div class="section-header">
                    <div class="section-title">Editor Kode</div>
                    <div class="controls">
                        <select name="language" id="language-select" onchange="updateEditor()">
                            <?php foreach ($languages as $key => $lang): ?>
                                <option value="<?= $key ?>" <?= $current_language === $key ? 'selected' : '' ?>>
                                    <?= $lang ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="run" class="run-btn">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 5V19L19 12L8 5Z" fill="currentColor"/>
                            </svg>
                            Run
                        </button>
                    </div>
                </div>
                <div class="editor-container">
                    <textarea id="code" name="code" placeholder="Ketik kode Anda di sini..." oninput="highlightCode()"><?= htmlspecialchars($code) ?></textarea>
                    <div id="highlighted-code" class="code-editor"></div>
                </div>
            </div>

            <div class="preview-section">
                <div class="section-header">
                    <div class="section-title">
                        <?= $current_language === 'html' ? 'Live Preview' : 'Output' ?>
                    </div>
                </div>
                <div class="preview-container">
                    <?php if ($current_language === 'html'): ?>
                        <iframe id="preview-frame" srcdoc="<?= htmlspecialchars($code) ?>"></iframe>
                    <?php else: ?>
                        <div class="output-container"><?= htmlspecialchars($output) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <div class="hint">
            <div class="hint-title">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C6.48 2 2 6.48 2 12C2 17.52 6.48 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2ZM13 17H11V15H13V17ZM13 13H11V7H13V13Z" fill="currentColor"/>
                </svg>
                <span>Tips Penggunaan</span>
            </div>
            <ul>
                <li>Untuk <span class="language-tag html-tag">HTML/CSS/JS</span>: Hasil akan langsung ditampilkan di preview</li>
                <li>Untuk <span class="language-tag cpp-tag">C++</span>, <span class="language-tag python-tag">Python</span>, <span class="language-tag php-tag">PHP</span>: Output akan ditampilkan setelah menekan tombol Run</li>
                <li>Kode dibatasi maksimal 10,000 karakter</li>
                <li>Waktu eksekusi maksimal 5 detik</li>
            </ul>
        </div>

        <footer>
            <div class="footer-content">
                <div class="brand">Iqyan</div>
                <a href="https://saweria.co/iqyan" target="_blank" class="donate-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" fill="currentColor"/>
                    </svg>
                    Dukung Kami
                </a>
                <div class="copyright">© <?= date('Y') ?> Iqyan Tech - Hak Cipta Dilindungi</div>
            </div>
        </footer>
    </div>

    <script>
        // Fungsi untuk syntax highlighting
        function highlightCode() {
            const code = document.getElementById('code').value;
            const language = document.getElementById('language-select').value;
            const highlightElement = document.getElementById('highlighted-code');
            
            // Bersihkan kelas sebelumnya
            highlightElement.className = 'code-editor';
            
            // Tambahkan kelas sesuai bahasa
            highlightElement.classList.add(language);
            
            // Proses syntax highlighting
            let highlightedCode = code;
            
            // Escape karakter khusus HTML
            highlightedCode = highlightedCode
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
            
            // Apply highlighting berdasarkan bahasa
            switch(language) {
                case 'cpp':
                    highlightedCode = highlightedCode
                        .replace(/\b(int|float|double|char|void|bool|auto|const|static|class|struct|namespace|template|public|private|protected|virtual|override|final|friend|using|typedef|enum|operator|return|if|else|for|while|do|switch|case|break|continue|default|new|delete|try|catch|throw|this|true|false|nullptr|sizeof|#include|#define)\b/g, '<span class="keyword">$&</span>')
                        .replace(/"([^"\\]*(\\.[^"\\]*)*)"|\'([^\'\\]*(\\.[^\'\\]*)*)\'/g, '<span class="string">$&</span>')
                        .replace(/\/\/.*|\/\*[\s\S]*?\*\//g, '<span class="comment">$&</span>')
                        .replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g, '<span class="function">$1</span>(')
                        .replace(/\b([0-9]+(\.[0-9]+)?)\b/g, '<span class="number">$&</span>')
                        .replace(/[+\-*\/%=<>!&|^~?:]/g, '<span class="operator">$&</span>');
                    break;
                
                case 'python':
                    highlightedCode = highlightedCode
                        .replace(/\b(and|as|assert|async|await|break|class|continue|def|del|elif|else|except|finally|for|from|global|if|import|in|is|lambda|nonlocal|not|or|pass|raise|return|try|while|with|yield|True|False|None)\b/g, '<span class="keyword">$&</span>')
                        .replace(/"([^"\\]*(\\.[^"\\]*)*)"|\'([^\'\\]*(\\.[^\'\\]*)*)\'|""".*?"""|'''.*?'''/gs, '<span class="string">$&</span>')
                        .replace(/#.*/g, '<span class="comment">$&</span>')
                        .replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g, '<span class="function">$1</span>(')
                        .replace(/\b([0-9]+(\.[0-9]+)?)\b/g, '<span class="number">$&</span>')
                        .replace(/[+\-*\/%=<>!&|^~?:]/g, '<span class="operator">$&</span>');
                    break;
                
                case 'php':
                    highlightedCode = highlightedCode
                        .replace(/\b(echo|print|function|class|interface|trait|namespace|use|extends|implements|public|private|protected|static|abstract|final|const|var|global|if|else|elseif|endif|switch|case|default|endswitch|for|foreach|endfor|while|endwhile|do|break|continue|return|exit|die|try|catch|throw|finally|new|instanceof|clone|include|include_once|require|require_once|__halt_compiler|array|callable|and|or|xor|__CLASS__|__DIR__|__FILE__|__LINE__|__FUNCTION__|__METHOD__|__NAMESPACE__|__TRAIT__)\b/g, '<span class="keyword">$&</span>')
                        .replace(/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/g, '<span class="variable">$&</span>')
                        .replace(/"([^"\\]*(\\.[^"\\]*)*)"|\'([^\'\\]*(\\.[^\'\\]*)*)\'/g, '<span class="string">$&</span>')
                        .replace(/\/\/.*|\/\*[\s\S]*?\*\/|#.*/g, '<span class="comment">$&</span>')
                        .replace(/\b([0-9]+(\.[0-9]+)?)\b/g, '<span class="number">$&</span>')
                        .replace(/[+\-*\/%=<>!&|^~?:]/g, '<span class="operator">$&</span>');
                    break;
                
                case 'html':
                    highlightedCode = highlightedCode
                        .replace(/&lt;\/?([a-zA-Z][a-zA-Z0-9]*)\b[^&]*&gt;/g, (match) => {
                            // Highlight tag names
                            const tagName = match.match(/&lt;\/?([a-zA-Z][a-zA-Z0-9]*)/)[1];
                            return match.replace(tagName, `<span class="tag">${tagName}</span>`);
                        })
                        .replace(/\b([a-zA-Z-]+)=/g, '<span class="attribute">$1</span>=')
                        .replace(/"([^"]*)"/g, '<span class="string">"$1"</span>')
                        .replace(/&lt;!--[\s\S]*?--&gt;/g, '<span class="comment">$&</span>')
                        .replace(/&lt;style[\s\S]*?&gt;([\s\S]*?)&lt;\/style&gt;/gi, (match, css) => {
                            // Highlight CSS
                            const highlightedCSS = css
                                .replace(/([a-zA-Z-]+)\s*:/g, '<span class="attribute">$1</span>:')
                                .replace(/#[a-fA-F0-9]{3,6}|rgb[a]?\([^)]+\)/g, '<span class="number">$&</span>')
                                .replace(/[0-9]+(px|em|rem|%|s)?/g, '<span class="number">$&</span>');
                            return match.replace(css, highlightedCSS);
                        })
                        .replace(/&lt;script[\s\S]*?&gt;([\s\S]*?)&lt;\/script&gt;/gi, (match, js) => {
                            // Highlight JavaScript
                            const highlightedJS = js
                                .replace(/\b(var|let|const|function|return|if|else|for|while|do|switch|case|break|continue|try|catch|finally|throw|new|class|extends|this|true|false|null|undefined|typeof|instanceof|in|of|async|await|export|import)\b/g, '<span class="keyword">$&</span>')
                                .replace(/("([^"\\]*(\\.[^"\\]*)*)"|\'([^\'\\]*(\\.[^\'\\]*)*)\')/g, '<span class="string">$&</span>')
                                .replace(/\/\/.*|\/\*[\s\S]*?\*\//g, '<span class="comment">$&</span>')
                                .replace(/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/g, '<span class="function">$1</span>(')
                                .replace(/\b([0-9]+(\.[0-9]+)?)\b/g, '<span class="number">$&</span>')
                                .replace(/[+\-*\/%=<>!&|^~?:]/g, '<span class="operator">$&</span>');
                            return match.replace(js, highlightedJS);
                        });
                    break;
            }
            
            highlightElement.innerHTML = highlightedCode;
        }
        
        // Fungsi untuk memperbarui preview HTML secara real-time
        function updatePreview() {
            const code = document.getElementById('code').value;
            const previewFrame = document.getElementById('preview-frame');
            if (previewFrame) {
                previewFrame.srcdoc = code;
            }
        }
        
        // Fungsi untuk memperbarui editor berdasarkan bahasa
        function updateEditor() {
            const language = document.getElementById('language-select').value;
            const previewTitle = document.querySelector('.preview-section .section-title');
            
            if (previewTitle) {
                previewTitle.textContent = language === 'html' ? 'Live Preview' : 'Output';
            }
            
            highlightCode();
        }
        
        // Inisialisasi
        document.addEventListener('DOMContentLoaded', function() {
            highlightCode();
            updatePreview();
            
            // Tambahkan event listener untuk input kode
            const codeTextarea = document.getElementById('code');
            if (codeTextarea) {
                codeTextarea.addEventListener('input', function() {
                    const language = document.getElementById('language-select').value;
                    highlightCode();
                    if (language === 'html') {
                        updatePreview();
                    }
                });
            }
        });
    </script>
</body>
</html>