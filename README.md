###install php####
install php php-fpm nginx/apache2 php8.4-mbstring
### install python###
install python3-dotenv python3-websockets
###manage database
untuk hapusbuser atau chatting cukup lewat mamagedb.sh agar lebih mudah
pindahkan folder html dan backend ke /var/www serta konfigurasi dulu ke nginx/ apache
#database_setting
install mariadb dan ekspor databasenya ke mariadb

#running apps
service php8.x-fpm start
service apache2/nginx start
service mariadb start
#jalankan python websocketnya di folder backend
python3 ws.py
#setup .env
ada dua file .env di html php dan di backend python
setting dua duanya samakan

bebas kalian kreasikan atau tambahkan fitur apapun
kode sudah di refactoring ketika ada bug bisa call me untuk di perbaiki lagi
