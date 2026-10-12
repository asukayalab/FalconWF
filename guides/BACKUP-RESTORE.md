# Memakai Backup & Restore

Panduan build lokal alpha.18; hosting ar.obie.my.id masih alpha.17 saat pemeriksaan 12 Oktober 2026. Perbaikan perlu dipasang dahulu. Restore alpha masih untuk instalasi/versi yang sama dan lingkungan local/staging; ini belum sertifikasi pemulihan seluruh server.

## Siapkan sekali

1. Buka Falcon WF → Backup & Restore sebagai administrator.
2. Jika status Belum siap, klik **Siapkan penyimpanan backup**. Setelah berhasil, status **Siap** dan form backup/jadwal/arsip muncul.
3. Jika server menolak, buka **Pengaturan pengelola hosting**. Minta pengelola menyediakan folder privat di luar document root, dapat ditulis oleh PHP, dengan izin0700; masukkan alamat lengkapnya dan klik **Gunakan folder privat ini**. Jangan memakai uploads atau folder publik.
4. Lokasi lama yang sudah bekerja tetap dipakai. Jika lokasi lama kehilangan akses, pengelola memperbaiki izin folder yang sama; setup tidak memindahkan atau menghapus arsip.

## Backup manual

Pilih komponen, periksa estimasi ukuran, lalu isi catatan opsional dan klik **Mulai backup latar belakang**. Untuk seluruh media beserta metadata native, pilih database dan media. Untuk beberapa media saja, gunakan pemilih Media Library; pilihan ini tidak digabung dengan seluruh database.

Periksa **Tugas backup** sampai Selesai. Jika terhenti, baca pesan dan gunakan **Lanjutkan / coba lagi** setelah sebabnya ditangani. WP-Cron bergantung kunjungan; pengelola dapat memakai cron server untuk situs sepi. Jangan menganggap tugas yang masih antre sebagai backup berhasil.

## Backup berkala dan retensi

Aktifkan jadwal, pilih harian/mingguan, jam, hari serta komponen lalu Simpan jadwal. Periksa zona waktu, jadwal berikutnya dan riwayat hasil. Simpan ulang sesudah zona waktu WordPress berubah.

Retensi default nonaktif. Jika diaktifkan, hanya sesudah backup baru valid arsip biasa tertua dihapus permanen sesuai jumlah pilihan. Arsip **terlindungi** tidak masuk kuota, tetapi tetap memakai disk. Menyimpan retensi tidak langsung menghapus arsip.

## Arsip dan restore

Setiap arsip menampilkan tanggal/jam, filename/ukuran dan catatan:

- **Download ZIP** menyimpan salinan ke komputer. ZIP tidak memakai password/enkripsi; simpan salinan dengan aman.
- **Lindungi backup ini** menjaga arsip dari retensi/Delete aplikasi. **Lepas Perlindungan** membolehkan penghapusan lagi.
- **Restore** membuka review. Periksa komponen dan target sebelum konfirmasi; Falcon membuat backup keselamatan dahulu. File/data yang didukung dapat ditimpa, akun/credential dan pilihan theme/plugin aktif dipertahankan. Versi lama tidak otomatis kompatibel dengan runtime baru.
- **Delete** membuka review dan membutuhkan konfirmasi penghapusan permanen. Hanya arsip yang dipilih dihapus; konten situs tetap ada.

Untuk salinan dari komputer, **Import dan periksa ZIP** dahulu, lalu review/konfirmasi Restore. Batas upload mengikuti server PHP/WordPress. Backup bukan pengganti backup core, akun dan konfigurasi server dari penyedia hosting.

Jika restore terputus, jangan mengulang write sembarangan. Gunakan panel Pemulihan restore terputus bersama pengelola; kondisi yang belum dapat dipastikan tetap diblokir. Contract rescue ada di engineering/BACKUP.md. Cloud storage belum tersedia.
