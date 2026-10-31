# Backup pada hosting container — untuk pengelola

Klien cukup memilih Backup sekarang → komputer/server atau menyimpan jadwal. Path, UID, volume dan permission ditangani pengelola, bukan pekerjaan rutin klien.

## Diagnosis trial

Pemeriksaan baca-saja ar.obie.my.id: WordPress /var/www/html, folder WordPress/content/uploads/plugin/theme writable; Apache Debian/PHP8.3.35. Pengguna mengonfirmasi Docker/panel container. UI backup versi terpasang belum punya storage. Ini cocok dengan PHP yang dapat menulis volume WordPress tetapi tidak parent /var/www; izin parent belum diinspeksi langsung. Jangan menyebut root cause filesystem terkonfirmasi hanya dari Site Health.

Alpha.21 mencoba ruang privat otomatis. Bila hanya temp privat tersedia, download tetap bisa dipakai; area temp tidak dipasarkan sebagai arsip permanen dan tidak dipakai untuk jadwal/restore.

## Volume tetap untuk server backup

Pola yang sudah dipakai dan diuji di local/compose.yml core:

1. Tambahkan named volume privat, misalnya backups, di luar public webroot.
2. Mount sebagai /fwf-backups pada WordPress, CLI dan worker yang memakai arsip yang sama.
3. Persiapkan volume dengan owner UID/GID proses PHP dan mode0700. Image WordPress resmi yang dipakai lokal memakai33:33; konfirmasikan UID image hosting aktual sebelum menerapkan, jangan mengubah kepemilikan volume WordPress/database.
4. Tambahkan define('FWF_BACKUP_DIR', '/fwf-backups'); melalui konfigurasi server yang didukung deployment, misalnya WORDPRESS_CONFIG_EXTRA pada image resmi. Pertahankan konfigurasi existing; jangan menyalin ulang wp-config atau credential sembarang.
5. Recreate hanya service yang memang perlu dengan volume/data existing tetap terpasang; jangan down -v atau reset database/uploads.
6. Review status storage, uji backup kecil dan download. Periksa akses HTTP publik tidak bisa membaca ZIP, cron/worker, kapasitas dan retensi. Rehearsal restore dilakukan di staging dan versi yang didukung, bukan langsung di production.

Lokasi/config yang sudah bekerja tidak diganti otomatis. Jika sebelumnya memakai transit, pengelola perlu merencanakan perpindahan tanpa kehilangan arsip, bukan sekadar mengubah option di database. Automatic migration arsip bukan fitur alpha.21. Constant punya prioritas; bila memakai constant baru, tinjau/pertahankan akses arsip lama terlebih dahulu.

Panel/service/volume deployment aktual harus diidentifikasi sebelum perubahan. Dokumen ini bukan perintah untuk menjalankan konfigurasi generik di hosting. Mount/perubahan hosting memerlukan langkah operator tersendiri; belum dilakukan pada trial ini.

Transit temp hanya terlihat oleh filesystem container yang membuatnya. WP-Cron bawaan berjalan di WordPress yang sama; worker container terpisah harus memakai volume bersama yang benar, jangan mengasumsikan /tmp antarcontainer sama. Bahkan folder HOME/sibling yang writable belum membuktikan persistensi saat container direcreate; pengelola tetap memastikan lokasi arsip server berada pada volume durable.
