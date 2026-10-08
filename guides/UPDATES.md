# Pembaruan Falcon WF

Commit menyimpan kode lokal; push mengirim kode ke GitHub. WordPress memperbarui file hanya setelah operator memasang ZIP atau memilih Update komponen dari release tervalidasi. GitHub bukan runtime frontend.

## Konfigurasi situs

1. Falcon WF → Proyek & Koneksi: isi `https://github.com/asukayalab/FalconWF` lalu simpan. Format `asukayalab/FalconWF` juga diterima.
2. Repo publik tidak memerlukan token, Coolify atau pengaturan server. Tag kosong memilih stable terbaru. Selama trial alpha di local/staging, isi tag tepat, misalnya `v0.1.0-alpha.14`. Prerelease bukan stable; pilihan ini tetap dibatasi environment local/staging.
3. Falcon WF → Pembaruan → Periksa pembaruan. Lihat versi terpasang, versi tersedia dan catatan perubahan GitHub Release.
4. Bila ada versi lebih baru, siapkan backup, centang konfirmasi lalu Update komponen. Paket diunduh, metadata/hash/isi ZIP diperiksa, lalu dipasang melalui WordPress. FP dan FT diperbarui terpisah; theme aktif tidak diganti.

Build alpha.14 dan sebelumnya masih membutuhkan token pada updater. Pasang alpha.15 sekali melalui Plugins → Add New → Upload Plugin → pilih ZIP → Replace current with uploaded. Setelah itu repo publik dapat dipakai tanpa token. Tidak ada pemasangan otomatis tanpa klik manusia.

Dukungan private tetap opsional: credential Contents read terbatas melalui constant atau environment `FWF_GITHUB_TOKEN`; proyek memakai `FWF_PROJECT_GITHUB_TOKEN`. Request dimulai tanpa credential, kemudian fallback hanya jika API menolak akses (401/404). Credential tidak diteruskan ke host download. Tidak ada token di form, Git, ZIP atau chat.

## Menyiapkan release

- Gunakan commit bersih. `npm run verify` dan `npm run test:integration` harus lulus; baca batas bukti di engineering/EVIDENCE.md.
- Versi/status berasal dari release/components.json. Prerelease memakai `development` dan versi alpha/beta/rc; stable memakai `stable` dan versi tiga angka. Manifest dengan dirty true ditolak pada kedua jalur.
- Jalankan `npm run build` dari checkout commit bersih setelah verifikasi. Periksa source_commit, dirty, versi, hash dan daftar file; jangan edit manifest hasil build.
- Buat GitHub Release pada commit yang sama. Untuk prerelease gunakan tag `v` + versi dan tandai prerelease; jangan draft. Unggah tiga asset dari dist: release-manifest.json, ZIP falcon-wf, ZIP falcon-theme. Tidak mengunggah source ZIP sebagai paket instalasi, database, uploads, credential, docs atau session-notes.
- WordPress staging memilih tag tepat lalu menguji check, FP self-update, FT update, activation state dan konten. Metadata GitHub dan hash paket diperiksa lagi saat apply.

Tidak ada publish GitHub otomatis dari perintah build. Penerbitan release dan pengujian pemasangan hosting tetap tindakan terpisah.

## Recovery

Sebelum update, simpan backup database, uploads, plugin/theme dan konfigurasi di luar webroot/repo. Uji restore di instalasi terisolasi: catat versi dan fingerprint konten sebelum update; restore file/database; pastikan admin login, URL, konten/media, theme aktif dan integrasi kembali normal. Catat durasi aktual agar target RPO/RTO berbasis bukti.

WP upgrader menangani penggantian/recovery kode sesuai WordPress. FWF tidak menjanjikan rollback database. Jika update gagal, hentikan retry sampai versi/file terpasang dan log WordPress diperiksa. Gunakan backup yang telah diuji; jangan menghapus konten atau mengganti theme otomatis.

## Bukti dan batas

Fixture integration memanggil WP Plugin_Upgrader dan Theme_Upgrader sungguhan, tetapi metadata/download GitHub disimulasikan. Ini bukti lokal, bukan acceptance distribusi private/hosting atau rehearsal restore insiden production. Repo publik tidak membutuhkan konfigurasi credential server. Pengujian update hosting dan recovery tetap memerlukan staging HTTPS target.
