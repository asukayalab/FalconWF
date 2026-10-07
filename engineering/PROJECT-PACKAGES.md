# Kontrak paket proyek klien

Status: kontrak tahap implementasi berikutnya; installer/updater proyek belum tersedia. Tidak menambah repo klien atau koneksi production tanpa owner/target.

## Pemisahan

Repo FalconWF milik Asukayalab berisi FP/FT. Repo proyek berisi child theme dan plugin proyek opsional. Isi, media dan nilai field tetap per instalasi WordPress; database/uploads bukan asset release. Rizal merupakan pilot terpisah, bukan isi bawaan semua situs.

FP memiliki schema/permission/repository dan koneksi. FT memiliki fondasi/template fallback. Child theme memiliki layout, design tokens, font/color/spacing dan template proyek. Plugin proyek hanya untuk fungsi/schema descriptor yang benar-benar dibutuhkan; tidak menduplikasi owner FP.

## Metadata dan versi

Versi rilis produk memilih snapshot distribusi; versi setiap komponen tetap milik komponennya. Tag rilis tidak boleh memaksa FP, FT, child theme atau plugin proyek memiliki versi sama. Manifest proyek perlu project ID, paket ID/type, version, artifact, sha256, min WP/PHP serta compatibility FP/FT. Contract compatibility harus diverifikasi sebelum file diganti. Manifest proyek memakai owner terpisah dari manifest core; tidak mengirim paket klien ke UpdateManager core yang hanya menerima falcon-wf/falcon-theme.

Repo dan credential reference proyek terpisah dari fwf_repo/FWF_GITHUB_TOKEN core. Credential tidak berada dalam URL/UI/artifact. UI yang direncanakan: Falcon WF → Proyek & Koneksi, bagian core dan proyek yang jelas, versi terpasang serta hasil kompatibilitas. Belum ada kolom repo klien di build ini.

## Alur satu contoh website

1. Tetapkan owner/project ID dan child theme slug.
2. Child theme menyatakan Template: falcon-theme. front-page.php mengatur beranda; page.php halaman biasa; archive/single Project mengatur listing/detail. Template mengambil nilai dari native WP/shared repository sesuai visibility; tidak menampilkan field private ke publik.
3. Build hanya runtime child theme, CSS/asset berlisensi dan manifest. WP theme uploader dapat memasang ZIP manual sebelum installer proyek tersedia.
4. Preview di staging; aktivasi merupakan pilihan manusia terpisah. Instal/update tidak otomatis mengubah theme aktif atau Reading settings/homepage.
5. Konten beranda diedit di Pages atau field yang didefinisikan proyek. Design tokens berlaku per proyek, bukan global antar klien. Repo menyimpan template/desain, database menyimpan nilai.
6. Uji empat route (beranda/page/listing/detail), draft/password/private exclusion, escaping, parent compatibility, font/assets, mobile/keyboard, disable/preserve dan update/recovery.

## SEO dan GEO

FT/child theme menghasilkan HTML semantic dan heading. Satu owner metadata SEO menangani title/description/canonical/structured data/robots; integrasi plugin SEO harus mencegah duplikasi, bukan memasang dua generator bersamaan. Sitemap WP dimanfaatkan sebelum menambah generator. Structured data mencerminkan isi yang benar-benar terlihat.

Coming-soon beranda harus noindex, sedangkan beranda final mengikuti pengaturan indeks/launch. Filter FT saat ini masih menandai semua front page noindex; koreksi dan regresi template hierarchy diperlukan sebelum launch. Belum ada janji SEO/GEO selesai atau jaminan ranking/citation AI.

## Batas penerimaan

Kontrak ini belum merupakan bukti pemasangan paket klien. Contoh generik lokal boleh dipakai untuk runtime proof; jangan mengklaim acceptance Rizal atau klien tertentu tanpa bahan dan review. Semua pekerjaan tetap melewati verify/integration; Q06/Q07 AI live wajib 0.1 dan tidak digantikan pekerjaan desain.
