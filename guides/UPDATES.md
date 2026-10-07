# Pembaruan Falcon WF

Commit menyimpan kode lokal; push mengirim kode ke GitHub. WordPress memperbarui file hanya setelah operator memasang ZIP atau memilih Update komponen dari release tervalidasi. GitHub bukan runtime frontend.

## Konfigurasi situs

1. Falcon WF → Proyek & Koneksi: isi repo `asukayalab/FalconWF` dalam format owner/name.
2. Hosting menyediakan `FWF_GITHUB_TOKEN` server-side, credential Contents read yang dibatasi ke repo tersebut. Jangan masukkan token ke kolom repo, Git, ZIP, atau chat.
3. Tag kosong berarti release stable terbaru. Tag seperti `v0.1.0-alpha.3` berarti satu prerelease tertentu, hanya diterima bila WordPress environment `local` atau `staging`. Environment ditentukan server melalui `WP_ENVIRONMENT_TYPE`; jangan mengganti label situs production agar dapat memasang alpha.
4. Falcon WF → Pembaruan → Periksa release. Review versi, lakukan backup dan uji staging, lalu pilih satu komponen. Memperbarui FP tidak otomatis memperbarui FT atau mengganti theme aktif.

Pemasangan alpha.2 lama belum menampilkan kolom tag. Bootstrap fitur ini melalui ZIP build terbaru yang sudah diuji, dengan alur upload/replace plugin WordPress di staging.

## Menyiapkan release

- Gunakan commit bersih. `npm run verify` dan `npm run test:integration` harus lulus; baca batas bukti di engineering/EVIDENCE.md.
- Versi/status berasal dari release/components.json. Prerelease memakai `development` dan versi alpha/beta/rc; stable memakai `stable` dan versi tiga angka. Manifest dengan dirty true ditolak pada kedua jalur.
- Jalankan `npm run build` dari checkout commit bersih setelah verifikasi. Periksa source_commit, dirty, versi, hash dan daftar file; jangan edit manifest hasil build.
- Buat GitHub Release pada commit yang sama. Untuk prerelease gunakan tag `v` + versi dan tandai prerelease; jangan draft. Unggah tiga asset dari dist: release-manifest.json, ZIP falcon-wf, ZIP falcon-theme. Tidak mengunggah source ZIP sebagai paket instalasi, database, uploads, credential, docs atau session-notes.
- WordPress staging memilih tag tepat lalu menguji check, FP self-update, FT update, activation state dan konten. Metadata GitHub dan hash paket diperiksa lagi saat apply.

Tidak ada publish GitHub otomatis dari perintah build. Belum ada private release nyata yang dibuktikan pada tahap ini.

## Recovery

Sebelum update, simpan backup database, uploads, plugin/theme dan konfigurasi di luar webroot/repo. Uji restore di instalasi terisolasi: catat versi dan fingerprint konten sebelum update; restore file/database; pastikan admin login, URL, konten/media, theme aktif dan integrasi kembali normal. Catat durasi aktual agar target RPO/RTO berbasis bukti.

WP upgrader menangani penggantian/recovery kode sesuai WordPress. FWF tidak menjanjikan rollback database. Jika update gagal, hentikan retry sampai versi/file terpasang dan log WordPress diperiksa. Gunakan backup yang telah diuji; jangan menghapus konten atau mengganti theme otomatis.

## Bukti dan batas

Fixture integration memanggil WP Plugin_Upgrader dan Theme_Upgrader sungguhan, tetapi metadata/download GitHub disimulasikan. Ini bukti lokal, bukan acceptance distribusi private/hosting atau rehearsal restore insiden production. Live acceptance memerlukan konfigurasi server dan staging HTTPS target.
