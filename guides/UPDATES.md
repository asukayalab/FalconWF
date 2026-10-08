# Pembaruan Falcon WF

Commit menyimpan kode lokal; push mengirim kode ke GitHub. WordPress memperbarui file hanya setelah operator memasang ZIP atau memilih Update komponen dari release tervalidasi. GitHub bukan runtime frontend.

## Konfigurasi situs

1. Buka Falcon WF → Proyek & Koneksi. Sumber Falcon WF bawaan sudah terisi; pengaturan sumber hanya untuk pengelola.
2. Pilih **Stabil** untuk website klien, atau **Uji coba** untuk website trial. Uji coba tersedia pada local/staging saja. Klik Simpan pengaturan pembaruan. Nomor versi tidak perlu diketik.
3. Buka Pembaruan → Periksa pembaruan. FWF mencari versi terbaru, menampilkan versi terpasang/tersedia, waktu pemeriksaan dan catatan perubahan.
4. Bila tersedia versi lebih baru, siapkan backup, centang konfirmasi lalu Perbarui Falcon Plugin atau Perbarui Falcon Theme. Paket diperiksa sebelum dipasang. FP dan FT diperbarui terpisah; tampilan aktif dipertahankan.

Pengaturan tag lama dari alpha.15 atau sebelumnya menjadi jalur Uji coba otomatis saat dibaca oleh alpha.16. Menyimpan pengaturan baru menghapus tag lama. Release baru dapat ditemukan dengan tombol yang sama tanpa mengedit koneksi. Tidak ada pemasangan tanpa tindakan manusia.

Stabil membaca latest stable GitHub. Uji coba memilih versi prerelease tertinggi yang diterbitkan (alpha/beta/rc), mengabaikan draft dan stable. Pengaturan ini tidak otomatis beralih ke stable saat stable diterbitkan; pengelola memilih Stabil setelah trial selesai. Repo publik tidak membutuhkan token/server setup. Repo private tetap opsional melalui credential terbatas server; tidak ada token di form.

## Beralih dari alpha.15 ke alur baru

Setelah release alpha.16 dari commit bersih diterbitkan, instalasi alpha.15 memilih tag v0.1.0-alpha.16 satu kali untuk memperbarui Falcon Plugin lewat updater yang sudah ada. Setelah plugin menjadi alpha.16, tag lama dikenali sebagai jalur Uji coba otomatis. Klik Periksa pembaruan lagi untuk memperbarui daftar; rilis berikutnya tidak perlu perubahan tag. Ini bootstrap alur baru, bukan pengaturan rutin klien.

## Menyiapkan release otomatis

Setelah workflow masuk ke GitHub, pengelola cukup commit/push ke main. Actions menjalankan verify, memasang paket pada WordPress Docker terisolasi, menjalankan integration tests, lalu membangun ulang dari commit bersih. Jika versi belum diterbitkan, Actions membuat GitHub prerelease dengan tiga asset: release-manifest.json, ZIP Falcon Plugin dan ZIP Falcon Theme. Tidak perlu mengunggah ZIP manual atau memasang token GitHub pada server WordPress.

Developer menaikkan versi melalui release/components.json (metadata npm harus tetap cocok) dan menyiapkan catatan di release/notes/VERSI.md. Commit perubahan tanpa kenaikan versi tetap diperiksa, tetapi tidak menimpa release yang sudah terbit. Paket desain klien memakai jalur proyek tersendiri, bukan workflow core ini.

1. Commit/push perubahan main lewat VS Code.
2. Buka repo GitHub → Actions → Check and release Falcon WF. Tunggu job pemeriksaan dan penerbitan selesai; jika merah, perbaiki penyebab lalu push lagi. Tidak ada release baru dari pemeriksaan gagal.
3. Bila Actions dinonaktifkan oleh kebijakan akun/repo, pengelola mengaktifkannya sekali di Settings → Actions → General. Workflow menggunakan GITHUB_TOKEN bawaan dengan izin tulis hanya pada job penerbitan; tidak meminta personal token.
4. Periksa halaman Releases: prerelease dan tiga asset harus tersedia. Baru lakukan trial pembaruan di ar.obie.my.id. Pemasangan pada WordPress tetap dikonfirmasi manusia.

Automation saat ini hanya untuk alpha/beta/rc berstatus development. Stable tidak diterbitkan otomatis sebelum gerbang 0.1 diterima. Upload memakai draft terlebih dahulu; kegagalan upload tidak membuka release parsial kepada updater. Draft yang tertinggal atau benturan tag berhenti untuk review pengelola, tidak menimpa asset diam-diam. Eksekusi workflow remote belum dianggap lulus hanya karena tes lokal lulus.

Fallback manual tetap tersedia: jalankan verify dan integration, build dari commit bersih, buat prerelease pada commit sama lalu unggah tiga asset dari dist. Jangan edit manifest hasil build atau mengunggah DB/uploads/credential/docs/session-notes.

## Recovery

Sebelum update, simpan backup database, uploads, plugin/theme dan konfigurasi di luar webroot/repo. Uji restore di instalasi terisolasi: catat versi dan fingerprint konten sebelum update; restore file/database; pastikan admin login, URL, konten/media, theme aktif dan integrasi kembali normal. Catat durasi aktual agar target RPO/RTO berbasis bukti.

WP upgrader menangani penggantian/recovery kode sesuai WordPress. FWF tidak menjanjikan rollback database. Jika update gagal, hentikan retry sampai versi/file terpasang dan log WordPress diperiksa. Gunakan backup yang telah diuji; jangan menghapus konten atau mengganti theme otomatis.

## Bukti dan batas

Fixture integration memanggil WP Plugin_Upgrader dan Theme_Upgrader sungguhan, tetapi metadata/download GitHub disimulasikan. Ini bukti lokal, bukan acceptance distribusi private/hosting atau rehearsal restore insiden production. Repo publik tidak membutuhkan konfigurasi credential server. Pengujian update hosting dan recovery tetap memerlukan staging HTTPS target.
