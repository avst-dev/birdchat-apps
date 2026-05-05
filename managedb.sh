#!/bin/bash

# Konfigurasi Database - Sesuaikan jika Tuan Muda menggunakan password
DB_NAME="birdchat"
DB_USER="root"

# Fungsi untuk mengeksekusi SQL dengan mematikan foreign key checks
execute_sql() {
    mysql -u "$DB_USER" "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS = 0; $1 SET FOREIGN_KEY_CHECKS = 1;"
}

clear
echo "==============================================="
echo "   BIRDCHAT DATABASE MANAGEMENT SYSTEM (AVST)  "
echo "==============================================="
echo "1. Bersihkan Semua Pesan (TRUNCATE messages)"
echo "2. Bersihkan Semua User  (TRUNCATE users)"
echo "3. Bersihkan Log Keamanan & Session"
echo "4. RESET TOTAL (Kosongkan Semua Tabel)"
echo "5. Keluar"
echo "-----------------------------------------------"
read -p "Pilih opsi [1-5]: " pilihan

case $pilihan in
    1)
        echo "Menghapus semua pesan..."
        execute_sql "TRUNCATE TABLE messages;"
        echo "[+] Tabel 'messages' berhasil dikosongkan."
        ;;
    2)
        echo "Menghapus semua user..."
        execute_sql "TRUNCATE TABLE users;"
        echo "[+] Tabel 'users' berhasil dikosongkan (ID di-reset ke 1)."
        ;;
    3)
        echo "Membersihkan log..."
        execute_sql "TRUNCATE TABLE security_logs; TRUNCATE TABLE sessions;"
        echo "[+] Tabel 'security_logs' dan 'sessions' berhasil dibersihkan."
        ;;
    4)
        read -p "APAKAH TUAN MUDA YAKIN? Ini akan menghapus SEMUA data! (y/n): " confirm
        if [ "$confirm" == "y" ]; then
            echo "Melakukan reset total..."
            execute_sql "TRUNCATE TABLE messages; TRUNCATE TABLE users; TRUNCATE TABLE security_logs; TRUNCATE TABLE sessions;"
            echo "[!] Database '$DB_NAME' sekarang benar-benar kosong."
        else
            echo "Operasi dibatalkan."
        fi
        ;;
    5)
        echo "Keluar..."
        exit 0
        ;;
    *)
        echo "Opsi tidak valid."
        ;;
esac
