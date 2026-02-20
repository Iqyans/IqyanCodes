DKID03 Advanced Offensive Security Toolkit

Untuk Edukasi Cybersecurity & Penetration Testing di Lingkungan Lab

https://img.shields.io/badge/Usage-Educational%20Only-brightgreen
https://img.shields.io/badge/Lab-Isolated%20Required-red

Peringatan: Alat ini dibuat semata-mata untuk tujuan edukasi dalam lingkungan lab yang terisolasi.
Penggunaan di sistem produksi atau tanpa izin adalah ilegal dan melanggar hukum. Penulis tidak bertanggung jawab atas penyalahgunaan.
 
 kalo ngeyel tanggung jawab sendiri!!
---

BACA SAMPE ABIS Deskripsi

DKID03 Advanced Offensive Security Toolkit adalah alat yang menggabungkan file manager lengkap dengan modul pengujian penetrasi ofensif untuk mendemonstrasikan teknik-teknik keamanan siber secara nyata. Dirancang khusus untuk instruktur dan cybersecurity, toolkit ini memungkinkan eksperimen dengan:

· Reverse shell multi-protokol
· Persistence mechanisms
· Data collection (screenshot, webcam, keylogger)
· Obfuscation & encryption
· Anti-kill process
· Command & Control (C2) panel
· Privilege escalation
· Lateral movement
· Credential dumping
· Anti-forensics

Semua fitur berfungsi nyata (bukan simulasi) namun dilengkapi dengan keamanan akses OTP via Telegram dan logging lengkap untuk memastikan penggunaan hanya di lingkungan lab yang sah.

---

★ Fitur Lengkap

Kategori Fitur Keterangan
File Manager Manajemen file/folder Browse, upload, download, rename, delete, bulk delete
Autentikasi OTP via Telegram Login menggunakan kode 6 digit yang dikirim ke akun Telegram Anda
Reverse Shell TCP, UDP, HTTP, DNS, ICMP, WebSocket Bypass berbagai jenis firewall, menghasilkan perintah untuk dijalankan di listener
Persistence 7 metode: cron, systemd, registry, WMI, startup, service, hook Menginstal persistence di sistem Windows/Linux
Data Collection Command execution, screenshot, webcam, keylogger Mengumpulkan informasi dari sistem target
Obfuscation Base64, XOR, AES-256, ROT13, multiple layers Menyembunyikan kode berbahaya agar tidak terdeteksi AV
Anti-Kill Fork bomb, watchdog, process migration Melindungi proses agar tidak mudah dimatikan
C2 Panel Encrypted communication, tasking Komunikasi dua arah dengan server C2
Privilege Escalation UAC bypass, sudo abuse, SUID check Mencoba meningkatkan hak akses di sistem
Lateral Movement SMB, WMI, SSH, RDP Berpindah ke sistem lain dalam jaringan
Credential Dumping Browser, Windows credentials, Linux shadow, SSH keys Mengambil kredensial dari sistem
Anti-Forensics Log cleaning, timestamp spoofing, history clearing Menghapus jejak aktivitas

---

🖥️ Persyaratan Sistem

· PHP 7.4 atau lebih baru
· Ekstensi PHP: curl, openssl, json, pcntl (opsional untuk fork bomb di Linux)
· Fungsi eksekusi (exec, shell_exec, proc_open) – diperlukan untuk sebagian besar fitur ofensif
· PowerShell (untuk fitur di Windows)
· Tools tambahan (jika ingin fitur tertentu):
  · ffmpeg, imagemagick, gnome-screenshot, scrot (Linux screenshot/webcam)
  · Akses ke API Telegram (untuk OTP)

---

🔧 Instalasi & Konfigurasi

1. Clone atau download file

Simpan file sebagai dkd03.php di server lab Anda (VM/container terisolasi).

2. Sesuaikan konfigurasi

Buka file dkd03.php dan ubah konstanta di bagian awal (sekitar baris 20–35):

```php
define('AUTH_KEY', 'wh1t3h4t_2024_secure');       // GANTI dengan kunci acak kuat!
define('ENCRYPTION_KEY', '32byte-aes-key-here!@#$%^&*()'); // HARUS 32 karakter!
define('C2_DOMAIN', 'your-c2-server.com');       // Domain/IP C2 (opsional)
define('C2_PORT', 8443);                          // Port C2
define('BOT_TOKEN', '8513008865:AAFvBdueP_HRaBfU5hm7el3lQAN1DxzgOE4');   // Token bot Telegram Anda
define('TELEGRAM_USER_ID', '7547598395');         // User ID Telegram penerima OTP
define('LOG_FILE', sys_get_temp_dir() . '/.system_update.log'); // Lokasi log
define('SLEEP_INTERVAL', 60);                      // Interval C2 check-in
define('SESSION_TIMEOUT', 1800);                   // Timeout login (30 menit)
```

Catatan: Pastikan ENCRYPTION_KEY tepat 32 karakter. Anda bisa menggunakan generator online atau membuat sendiri.

3. Setel izin file (jika diperlukan)

Pastikan file dapat diakses oleh web server, dan direktori log dapat ditulis.

4. Akses melalui browser

Buka http://lab-server/dkd03.php. Halaman login akan muncul.

5. Dapatkan kode OTP

Klik tombol "Request OTP via Telegram". Bot akan mengirim kode 6 digit ke akun Telegram Anda. Masukkan kode untuk login.

---

 Penggunaan

File Manager (tab pertama)

· Navigasi direktori, upload/download file, rename, delete, bulk delete.
· Sama seperti file manager pada umumnya.

Offensive Tools (tab kedua)

Pilih fitur yang ingin didemonstrasikan. Setiap fitur memiliki form input yang sesuai. Hasil akan ditampilkan di bagian Result.

Contoh:

· Reverse Shell: Isi IP dan port listener Anda, pilih protokol, klik Execute. Perintah yang dihasilkan harus dijalankan di terminal listener.
· Persistence: Klik tombol "Install All Persistence" – akan mencoba menginstal semua metode persistence.
· Command Execution: Masukkan perintah (misal whoami), hasil akan ditampilkan.
· Screenshot: Klik tombol – akan mengambil screenshot layar dan menampilkan base64 (bisa didecode).
· Obfuscation: Masukkan kode PHP, pilih metode, dapatkan kode terobfuskasi.

Semua aktivitas tercatat di LOG_FILE untuk keperluan audit.

---

🧪 Lingkungan Lab yang Direkomendasikan

· Virtual Machine (VirtualBox/VMware) dengan snapshot sebelum menjalankan alat.
· Container (Docker) dengan isolasi jaringan.
· Jaringan internal (tidak terhubung ke internet publik) untuk menghindari kebocoran.

Pastikan semua siswa memahami etika hacking dan hanya menggunakan alat ini di sistem yang mereka miliki atau telah mendapat izin tertulis.

---

⚠️ Peringatan Penting

· Alat ini sangat berbahaya jika disalahgunakan. Fitur-fiturnya dapat digunakan untuk meretas sistem secara ilegal.
· Penulis tidak bertanggung jawab atas kerusakan atau pelanggaran hukum yang disebabkan oleh penggunaan alat ini.
· Gunakan hanya untuk pendidikan di lingkungan lab yang terisolasi.
· Jangan pernah mengunggah alat ini ke server publik atau produksi.

---

📝 Lisensi

Proyek ini dilisensikan di bawah Dkid03 – Anda bebas menggunakan, memodifikasi (selain author), dan mendistribusikan, namun tanggung jawab sepenuhnya ada pada pengguna. Lihat file LICENSE untuk detail.

---

🙏 Kredit

Dikembangkan oleh Dkid03 untuk keperluan edukasi cybersecurity.
Terima kasih kepada komunitas white hat yang terus berbagi pengetahuan.

---

📧 Kontak

Jika Anda memiliki pertanyaan atau saran, silakan buka issue di repositori ini.

---
