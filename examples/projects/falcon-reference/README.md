# Falcon Reference — contoh paket desain klien lokal

Ini contoh generik untuk membuktikan hubungan FP, FT, child theme dan konten WordPress. Bukan website Rizal atau desain klien final; tidak membuat repo GitHub klien.

## Cara memasang dan melihat

1. Jalankan `npm run verify` untuk build/cek core dan contoh. `npm run build:project` membangun contoh saja.
2. Parent Falcon Theme harus minimal 0.1.0-alpha.4 dan berada dalam rentang project.json. Parent lama belum memiliki contract Desain Global yang dipakai contoh ini. Plugin FP diperlukan untuk Project/listing, sedangkan Page tetap berfungsi tanpa FP.
3. Di WordPress lokal: Tampilan → Tema → Tambah Tema → Unggah. Pilih dist/projects/falcon-reference-0.1.0-alpha.2.zip. Pemasangan tidak mengganti theme aktif.
4. Pilih Live Preview untuk melihat tanpa aktivasi. Aktivasi theme harus keputusan manusia; installer core tidak melakukannya otomatis.
5. Jika diaktifkan, Settings → Reading dapat memilih halaman statis sebagai homepage. Judul dan isi halaman menjadi isi hero. Mode latest posts memakai Judul Situs/Tagline sebagai hero, bukan memindahkan isi atau membuat halaman otomatis.
6. Pages mengurus tulisan/gambar; Project mengurus data karya; template PHP dan CSS mengurus layout. Update template tidak mengganti database/media.

## Peta hubungan

| Source | Dipakai oleh | Data |
|---|---|---|
| functions.php | WordPress child theme loader | enqueue CSS setelah parent; public archive query |
| front-page.php | front page hierarchy | Page statis atau judul/tagline; FP falcon_listing |
| page.php | Page hierarchy | title/content native dan password form WP |
| archive-fwf_project.php | Project archive hierarchy | main query publish tanpa password + pagination |
| single-fwf_project.php | Project detail hierarchy | title/body native; field publik dari helper FT/Schema FP |
| index.php | fallback hierarchy | template generic parent FT |
| assets/design.css | child enqueue | font judul/isi, warna, spacing, heading scale, responsive |
| style.css | WP theme discovery | Template falcon-theme dan versi hasil build |
| project.json | build-project.mjs | inventory, versi, parent/runtime/compatibility |

System fonts memakai font yang tersedia di perangkat; tidak mengunduh Google Fonts. Fallback design tokens didefinisikan di CSS; override per situs/theme melalui form Desain Global. H1/H2/H3 adalah struktur isi, sementara CSS mengatur ukuran. SEO metadata/structured data belum diimplementasikan. Installer/updater satu child theme tersedia di FP alpha.5, dengan acceptance distribusi GitHub klien masih pending.

Build hanya delapan file runtime yang terdaftar; README/project source metadata, tests, database/media dan dokumen tidak ikut ZIP. Project manifest dan build report berada di dist/projects, terpisah dari manifest core. Komponen contoh memiliki versi sendiri.

## Pengujian

npm run test:integration menjalankan project.test.mjs: install sementara tidak mengaktifkan theme; fixture test memilih child sementara di Docker, menguji route dan visibility/index/assets lalu memulihkan theme/options/file lama serta menghapus hanya konten fixture miliknya. Tidak memasang desain di hosting. Jangan menghentikan proses saat snapshot aktif; jika interrupted, recovery memakai owner snapshot, bukan menimpa snapshot lama.

Desain Global: setelah theme aktif dipilih manusia, buka Falcon WF → Desain Global. Token --reference-* mengambil --fwf-* dengan fallback desain proyek. Kosong berarti fallback; konfigurasi tersimpan di situs, bukan ikut commit source desain. H1–H6 tetap mengikuti markup template/konten.
