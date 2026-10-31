# Memakai Backup & Restore

Panduan build lokal alpha.21. Status hosting harus diperiksa terpisah. Restore tetap instalasi/versi sama, local/staging, dengan penyimpanan privat persisten; bukan pemulihan seluruh server.

## Mulai memakai backup

1. Buka Falcon WF → Backup & Restore sebagai administrator.
2. Pilih tujuan pada Backup manual lalu Backup sekarang. Falcon menyiapkan ruang privat saat aktivasi atau aksi backup, tanpa meminta klien membuat folder.
3. Jika hanya transit aman tersedia, Download ke komputer tetap tersedia. Arsip server, jadwal dan restore menunggu ruang persisten yang disiapkan pengelola. Pengelola container mengikuti [panduan Docker](BACKUP-HOSTING-DOCKER.md).
4. Lokasi yang sudah bekerja dipertahankan. Bila aksesnya rusak, pengelola memperbaikinya; Falcon tidak memindahkan/menghapus arsip atau mengganti lokasi secara diam-diam. Setup folder manual tetap opsi lanjutan pengelola.

## Backup manual

Pilih tujuan komputer/server dan komponen, periksa estimasi, isi catatan opsional lalu klik **Backup sekarang**. Untuk seluruh media beserta metadata native, pilih database dan media. Untuk beberapa media saja, gunakan pemilih Media Library; pilihan ini tidak digabung dengan seluruh database.

Periksa **Tugas backup** sampai Selesai. Jika terhenti, baca pesan dan gunakan **Lanjutkan / coba lagi** setelah sebabnya ditangani. WP-Cron bergantung kunjungan; pengelola dapat memakai cron server untuk situs sepi. Jangan menganggap tugas yang masih antre sebagai backup berhasil.

## Backup berkala dan retensi

Aktifkan jadwal, pilih harian/mingguan/bulanan, jam, hari/tanggal serta komponen lalu Simpan jadwal. Periksa zona waktu, jadwal berikutnya dan riwayat hasil. Simpan ulang sesudah zona waktu WordPress berubah.

Retensi default nonaktif. Jika diaktifkan, hanya sesudah backup baru valid arsip biasa tertua dihapus permanen sesuai jumlah pilihan. Arsip **terlindungi** tidak masuk kuota, tetapi tetap memakai disk. Menyimpan retensi tidak langsung menghapus arsip.

## Arsip dan restore

Setiap arsip menampilkan tanggal/jam, filename/ukuran dan catatan:

- **Download ZIP** menyimpan salinan ke komputer. ZIP tidak memakai password/enkripsi; simpan salinan dengan aman.
- **Lindungi backup ini** menjaga arsip dari retensi/Delete aplikasi. **Lepas Perlindungan** membolehkan penghapusan lagi.
- **Restore** membuka review. Periksa komponen dan target sebelum konfirmasi; Falcon membuat backup keselamatan dahulu. File/data yang didukung dapat ditimpa, akun/credential dan pilihan theme/plugin aktif dipertahankan. Versi lama tidak otomatis kompatibel dengan runtime baru.
- **Delete** membuka review dan membutuhkan konfirmasi penghapusan permanen. Hanya arsip yang dipilih dihapus; konten situs tetap ada.

Untuk salinan dari komputer, **Import dan periksa ZIP** dahulu, lalu review/konfirmasi Restore. Batas upload mengikuti server PHP/WordPress. Backup bukan pengganti backup core, akun dan konfigurasi server dari penyedia hosting.

Jika restore terputus, jangan mengulang write sembarangan. Gunakan panel Pemulihan restore terputus bersama pengelola; kondisi yang belum dapat dipastikan tetap diblokir. Contract rescue ada di engineering/BACKUP.md. Cloud storage belum tersedia.

## Backup praktis alpha.21

Backup manual kini tampil lebih dahulu. Pilih Download ke komputer atau Simpan di server lalu Backup sekarang. Ruang aman disiapkan otomatis saat aktivasi/backup. Setelah ZIP selesai, browser mencoba mengunduhnya; jika tertahan, klik Download ZIP sekarang. File server tidak dihapus hanya karena unduhan dicoba, jadi bisa diambil ulang. Tanpa JavaScript, gunakan tombol download setelah halaman dimuat ulang.

Backup otomatis mendukung harian, mingguan dan bulanan. Bulanan tanggal31 memakai akhir bulan bila lebih pendek, lalu kembali31 pada bulan berikutnya. Zona waktu mengikuti WordPress saat save; cron hosting tetap diperlukan untuk eksekusi tepat waktu pada situs sepi.

Bila hanya ruang transit tersedia, pilihan server tidak aktif dan jadwal/restore memerlukan pengelola menyiapkan storage tetap. Klien tidak perlu mengisi path/chmod. Pengelola container mengikuti BACKUP-HOSTING-DOCKER.md. Transit dapat dibersihkan hosting; simpan hasil download. Cloud belum tersedia. Batas restore local/staging dan versi sama tetap berlaku.
