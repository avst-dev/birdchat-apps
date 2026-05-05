# 🐦 BirdChat - Open Source Real-time Chatting Platform
### 🚀 Modern Communication Ecosystem | AVST Project
[![License](https://img.shields.io/badge/License-Open%20Source-blue.svg)]() [![Framework](https://img.shields.io/badge/PHP-8.4-777bb4.svg)]() [![Python](https://img.shields.io/badge/Python-3.x-3776ab.svg)]()

**BirdChat** adalah platform pesan instan sumber terbuka yang dibangun dengan fokus pada kecepatan, kesederhanaan, dan efisiensi. Menggunakan arsitektur *hybrid* antara PHP untuk manajemen data dan Python WebSocket untuk komunikasi dua arah yang responsif.

---

## ✨ Fitur Utama
*   **Real-time Messaging**: Pengiriman pesan instan tanpa jeda menggunakan teknologi WebSocket.
*   **Lightweight Architecture**: Desain sistem yang ringan, cocok untuk dijalankan di berbagai spesifikasi server.
*   **Open Source Management**: Dilengkapi dengan alat manajemen database otomatis untuk memudahkan pemeliharaan.
*   **Environment-based Config**: Konfigurasi yang mudah menggunakan file `.env` untuk keamanan dan fleksibilitas.

---

## 🛠️ Persiapan Sistem
Sebelum memulai, pastikan server Anda sudah terinstal:
*   **Web Server**: Apache2 atau Nginx.
*   **PHP Environment**: PHP 8.4+ dengan ekstensi `php-mysql` 'mbstring` dan 'php-fpm`.
*   **Python Engine**: Python 3.x dengan library `websockets` dan `python-dotenv`.
*   **Database**: MariaDB Server.

---

## ⚙️ Panduan Instalasi (Deployment)

### 1. Struktur Folder
Pindahkan komponen aplikasi ke direktori standar server Anda:
```bash
# Direktori Frontend (PHP)
sudo mv html /var/www/

# Direktori Backend (Python & Management)
sudo mv backend /var/www/backend

# 1. Menjalankan Layanan Database & Web
sudo service mariadb start
sudo service php8.x-fpm start
sudo service apache2 start  # Gunakan nginx jika diperlukan

cd /var/www/backend
python3 ws.py

mv managedb.sh /usr/local/bin/managedb
chmod +x managedb

