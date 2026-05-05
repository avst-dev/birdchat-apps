# 🐦 BirdChat - Virtual Communication & Digital Forensics
### 🛠️ AVST Project: Integrated Security Platform
[![Version](https://img.shields.io/badge/Version-1.0.0--Stable-blue.svg)]() [![Cybersecurity](https://img.shields.io/badge/Focus-Digital%20Forensics-red.svg)]() [![Dev](https://img.shields.io/badge/Developer-avst--dev-orange.svg)]()

**BirdChat** adalah platform komunikasi virtual berbasis WebSocket dan PHP yang dioptimalkan untuk performa tinggi, efisiensi database, dan audit keamanan. Proyek ini merupakan bagian dari ekosistem **AVST Project** yang berfokus pada edukasi digital forensics dan pengembangan aplikasi web aman.

---

## ⚡ Fitur Utama (Refactored)
*   **Real-time Messaging**: Menggunakan Python WebSocket (`ws.py`) untuk transmisi pesan instan tanpa refresh halaman.
*   **Secure Authentication**: Logika login dan registrasi yang telah divalidasi melalui PHP backend.
*   **Database Management**: Script otomatis `manage_db.sh` untuk pembersihan data, reset total, dan ekspor database.
*   **Cyberpunk Aesthetic**: UI/UX yang dirancang dengan tema futuristik dan clean.

---

## 🚀 Prasyarat Instalasi

### 1. Stack Teknologi
Pastikan komponen berikut terinstal di sistem (Disarankan menggunakan Kali Linux):
*   **Web Server**: Apache2 atau Nginx.
*   **PHP**: Versi 8.x dengan ekstensi `mbstring`[cite: 1].
*   **Python**: Versi 3.x untuk menjalankan WebSocket Server[cite: 1].
*   **Database**: MariaDB atau MySQL[cite: 1].

### 2. Module Python yang Dibutuhkan
Instal library eksternal berikut untuk mendukung server WebSocket:
```bash
pip install websockets python-dotenv --break-system-packages

### 3. Jalankan aplikasinya
```bash
service mariadb start
service php8.*-fpm start
service nginx/apache2 start
python3 ws.py
