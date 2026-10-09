# Kontrak paket proyek klien

Status: contoh child theme generik lokal dan build terpisah tersedia di examples/projects/falcon-reference. Alpha.5 menyediakan koneksi/check/instal/update satu child theme lewat release proyek; transport GitHub masih diuji fixture, belum live acceptance. Tidak menambah repo klien atau koneksi production tanpa owner/target.

## Pemisahan

Repo FalconWF milik Asukayalab berisi FP/FT. Repo proyek berisi child theme dan plugin proyek opsional. Isi, media dan nilai field tetap per instalasi WordPress; database/uploads bukan asset release. Rizal merupakan pilot terpisah, bukan isi bawaan semua situs.

FP memiliki schema/permission/repository dan koneksi. FT memiliki fondasi/template fallback. Child theme memiliki layout, fallback design tokens dan template proyek. FT memiliki contract global design; child yang kompatibel memakai --fwf-* dengan fallback desainnya sendiri. Plugin proyek hanya untuk fungsi/schema descriptor yang benar-benar dibutuhkan; tidak menduplikasi owner FP.

## Metadata dan versi

Versi rilis produk memilih snapshot distribusi; versi setiap komponen tetap milik komponennya. Tag rilis tidak boleh memaksa FP, FT, child theme atau plugin proyek memiliki versi sama. Manifest proyek perlu project ID, paket ID/type, version, artifact, sha256, min WP/PHP serta compatibility FP/FT. Contract compatibility harus diverifikasi sebelum file diganti. Manifest proyek memakai owner terpisah dari manifest core; tidak mengirim paket klien ke UpdateManager core yang hanya menerima falcon-wf/falcon-theme.

Repo dan credential reference proyek terpisah dari fwf_repo/FWF_GITHUB_TOKEN core. Credential tidak berada dalam URL/UI/artifact. UI yang direncanakan: Falcon WF → Proyek & Koneksi, bagian core dan proyek yang jelas, versi terpasang serta hasil kompatibilitas. Kolom repo proyek/project ID/theme slug/tag tersedia di Proyek & Koneksi; hasil review dan apply di Pembaruan. Token proyek FWF_PROJECT_GITHUB_TOKEN terpisah dari token core.

## Alur satu contoh website

1. Tetapkan owner/project ID dan child theme slug.
2. Child theme menyatakan Template: falcon-theme. front-page.php mengatur beranda; page.php halaman biasa; archive/single Project mengatur listing/detail. Template mengambil nilai dari native WP/shared repository sesuai visibility; tidak menampilkan field private ke publik.
3. Build hanya runtime child theme, CSS/asset berlisensi dan manifest. WP theme uploader tetap dapat memasang ZIP manual. Installer proyek memerlukan project-manifest.json + ZIP pada GitHub Release bersih.
4. Preview di staging; aktivasi merupakan pilihan manusia terpisah. Instal/update tidak otomatis mengubah theme aktif atau Reading settings/homepage.
5. Konten beranda diedit di Pages atau field yang didefinisikan proyek. Design tokens berlaku per proyek, bukan global antar klien. Repo menyimpan template/desain, database menyimpan nilai.
6. Uji empat route (beranda/page/listing/detail), draft/password/private exclusion, escaping, parent compatibility, font/assets, mobile/keyboard, disable/preserve dan update/recovery.

## SEO dan GEO

FT/child theme menghasilkan HTML semantic dan heading. Satu owner metadata SEO menangani title/description/canonical/structured data/robots; integrasi plugin SEO harus mencegah duplikasi, bukan memasang dua generator bersamaan. Sitemap WP dimanfaatkan sebelum menambah generator. Structured data mencerminkan isi yang benar-benar terlihat.

Coming-soon beranda harus noindex, sedangkan beranda final mengikuti pengaturan indeks/launch. Alpha.3 membatasi filter noindex ke template coming-soon yang benar-benar dirender; child front page mengikuti kebijakan visibility WordPress. Helper field FT juga menolak metadata saat password belum dibuka. Regresi guest/index/template hierarchy ada di tests/project.test.mjs. Belum ada janji SEO/GEO selesai atau jaminan ranking/citation AI.

## Batas penerimaan

Contoh generik dipasang/dipilih sementara dalam fixture Docker, diuji lalu dipulihkan. Installer repo kini diuji melalui transport fixture dan actual WordPress; ini belum live acceptance repo/klien. Contoh generik lokal boleh dipakai untuk runtime proof; jangan mengklaim acceptance Rizal atau klien tertentu tanpa bahan dan review. Semua pekerjaan tetap melewati verify/integration; Q06/Q07 AI live wajib 0.1 dan tidak digantikan pekerjaan desain.

## Contract installer Alpha.5

- Satu koneksi proyek per installation: repo owner/name, project_id, theme_id, tag. Slug lower-case aman, tidak boleh falcon-wf/falcon-theme. Paket hanya satu theme dengan parent falcon-theme; plugin proyek belum didukung.
- project-manifest.json schema 1 memuat version rilis proyek, project_id, status stable/development, dirty false, source_commit SHA40 serta satu package id/type/theme/parent/version/artifact/sha256/min_wp/min_php/compatibility. Version rilis cocok dengan GitHub tag; versi komponen tetap independen. Artifact tepat {theme_id}-{component_version}.zip.
- Rentang compatibility minimal falcon-theme wajib: min inklusif dan max_exclusive; falcon-wf dapat required/optional. Semua rentang hanya FP/FT, tervalidasi typed. Parent wajib terpasang/valid. Slug existing non-Falcon atau tanpa versi valid ditolak.
- Tag kosong stable terbaru; tag alpha/beta/rc explicit hanya local/staging memakai policy core yang sama. Release draft/duplikat asset/status/tag/provenance/runtime tidak cocok ditolak. Check yang gagal menghapus kandidat. Apply refetch metadata dan menuntut manifest/tag/koneksi identik review sebelumnya.
- Dashboard cap/nonce → ProjectManager → shared GitHubClient (token/manifest proyek) → project validation → shared Verifier remote ZIP → WP Theme_Upgrader. ZIP checksum, root/paths/canonical aliases/duplicates/symlink/size/headerVersion/parent/index diverifikasi. Signed GitHub redirect tidak menerima Authorization.
- Install target belum ada dengan overwrite false; update hanya versi lebih tinggi lewat transient target tunggal yang dipulihkan. Backup/staging confirmation, direct writable filesystem/temp/disk, audit pre-write serta shared updates lock core/proyek wajib. Hasil file/version/parent diperiksa. Tidak switch theme/Reading/content.
- Disconnect hanya menghapus koneksi/kandidat. Live Preview/aktivasi dilakukan lewat Appearance → Themes. Human direct edit child files akan terganti saat update; desain global DB tetap per theme. Recovery kode WordPress/backup, bukan rollback DB otomatis.
- Belum live GitHub release, host acceptance, plugin proyek, multi-project packages, arbitrary layout editor atau contract Rizal. Contoh repo generik tidak mengklaim repo klien tertentu.

## Builder source proyek terpisah

`npm run build:project` tetap membangun Falcon Reference. Untuk source proyek terpisah, gunakan `npm run build:project -- --source <folder-source> --output <folder-artefak>`. `project.json` menjadi pemilik project/package ID dan daftar runtime; source desain klien tidak perlu masuk repo core.

Output harus berada di luar source dan tidak overlap staging build. Runtime PHP/CSS terdaftar menolak symlink, termasuk direktori asset. Commit/dirty dihitung dari repo source proyek, bukan otomatis dari repo core; source yang belum dilacak Git menghasilkan source_commit null/dirty true dan hanya untuk ZIP preview/manual lokal. Rilis remote memerlukan source proyek dilacak dalam repo sendiri dengan provenance bersih. Builder tidak membuat repo, push, publish atau aktivasi theme.
