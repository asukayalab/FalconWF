# Menyiapkan paket desain proyek

Panduan pengelola/developer untuk builder dan installer Falcon alpha.20. Klien rutin tetap mengedit konten di WordPress; tidak perlu menjalankan langkah build ini.

## Sebelum build

- Source child theme berada di repo proyek yang ditentukan owner, terpisah dari core.
- project.json mendeskripsikan identitas, versi, requirement, compatibility dan daftar runtime. Semua file runtime yang didukung wajib masuk daftar.
- style.css harus memiliki Theme Name, Version dan Template: falcon-theme yang sesuai paket.
- index.php wajib masuk paket. Bila hanya ingin memakai fallback parent, contoh minimal:

```php
<?php
if (!defined('ABSPATH')) { exit; }
require get_template_directory().'/index.php';
```

Named Page template tetap file terpisah yang benar-benar didaftarkan oleh theme. Menambah index.php tidak otomatis membuat template Kontak/About atau Page WordPress.

## Aset yang didukung

Gunakan ekstensi lowercase berikut: php, css, js, mjs, svg; png, jpg, jpeg, gif, webp, avif, ico; woff, woff2, ttf, otf. Gambar/font disalin sebagai byte asli; placeholder @@PROJECT_VERSION@@ hanya diproses pada file teks. Enqueue JS/CSS lewat API WordPress. Cek lisensi aset dan perilaku browser secara terpisah; keberhasilan kemasan tidak membuktikan font valid atau desain cocok referensi.

Batas paket: ZIP20MiB dan total expanded40MiB. Symlink, traversal/alias path, jenis file unsupported dan runtime tidak terpetakan ditolak. Konten/media unggahan, credential, database, handbook dan session notes bukan bagian ZIP desain.

## Build dan distribusi

Dari checkout core yang sesuai:

```sh
npm run build:project -- --source /path/absolut/source-proyek --output /path/absolut/hasil-build
```

Ganti placeholder dengan path aktual. Output di luar source dan staging builder. Source tanpa Git/commit bersih dapat menghasilkan ZIP preview dirty; jangan menerbitkannya sebagai release remote.

Untuk release remote, commit source/versi yang benar terlebih dahulu, pastikan repo proyek bersih, lalu build ulang. Periksa project-manifest.json: dirty:false dan source_commit SHA aktual. Builder baseline menghasilkan development prerelease; tag eksplisit harus cocok dengan versi manifest, dan prerelease dibatasi local/staging. Otomatisasi release desain/stable diperluas dalam kelompok roadmap lain; jangan menganggap builder ini otomatis push/publish.

Release berisi project-manifest.json dan artifact ZIP yang disebut manifest. Jangan mengganti asset release published; perbaikan diterbitkan sebagai versi baru dengan provenance bersih setelah diotorisasi owner.

## Yang diperiksa Falcon

- **Check:** manifest, identitas proyek/theme, versi/kanal, requirement/compatibility dan keberadaan asset rilis. ZIP belum diunduh; ini belum bukti checksum/isi ZIP valid.
- **Apply:** refetch rilis dan bandingkan dengan review, download ZIP, verifikasi SHA-256/path/ukuran/header/parent/entrypoint/aset, lalu installer WordPress mengganti file setelah semua guard terpenuhi.
- Instal/update tidak otomatis mengaktifkan theme, membuat Page/field atau mengganti homepage. Blueprint otomatis belum tersedia.

## Jika ditolak

| Pesan | Langkah pengelola |
|---|---|
| Dirty checkout | Commit perubahan, build ulang dari checkout bersih, siapkan versi rilis baru |
| Commit sumber invalid | Pastikan source dilacak Git dan punya commit, lalu build ulang |
| Project ID/slug berbeda | Cocokkan paket dengan koneksi proyek yang memang dituju |
| Versi/tag/kanal tidak sesuai | Periksa versi manifest dan pilihan tag/stable; jangan mengubah angka manifest manual |
| Entry/style/header/aset tidak valid | Perbaiki source/runtime lalu build ulang; jangan menambal ZIP published |
| Checksum ZIP tidak cocok | Hentikan pemasangan; periksa artifact dan manifest rilis sumber |
| Komponen tidak kompatibel | Periksa requirement FP/FT/WP/PHP sebelum memasang |

Tetap review backup/staging sesuai kemampuan recovery yang didukung. Jangan mengganti theme, menerbitkan konten atau menimpa data klien sebagai efek samping perbaikan paket.
