# Memasang dan memperbarui desain proyek

FWF core (FP/FT) dan desain klien memakai koneksi yang terpisah. Commit/push menyimpan source di GitHub; update WordPress memerlukan GitHub Release yang berisi manifest dan ZIP hasil build dari commit bersih.

## Setelah repo klien dan paket tersedia

1. Developer menyiapkan child theme dengan Template: falcon-theme, versi komponen dan project-manifest.json sesuai engineering/PROJECT-PACKAGES.md. Repo/owner proyek harus diketahui; contoh Falcon Reference di sini hanya contoh lokal.
2. Developer menerbitkan release proyek dengan project-manifest.json dan ZIP {slug-theme}-{version}.zip. Version rilis proyek harus cocok dengan tag; versi ZIP komponen dapat berbeda. Jangan sertakan DB, uploads, secrets, docs atau session notes.
3. Repo publik tidak memerlukan token. Untuk repo private saja, operator menyediakan FWF_PROJECT_GITHUB_TOKEN read-only, terpisah dari token core. Jangan kirim token lewat chat atau isi form repo.
4. Buka Falcon WF → Proyek & Koneksi → Paket desain proyek. Isi URL GitHub atau owner/name repo dan pilih Stabil terbaru atau Terbaru, termasuk uji coba. Project ID dan slug theme boleh dikosongkan untuk ditemukan dari manifest saat menyimpan. Pengelola dapat mengisinya sebagai batas identitas atau memilih Tag release tertentu. Tag release tertentu mendukung stabil maupun alpha/beta/rc pada semua environment; kolom tag muncul ketika pilihan itu dipakai.
5. Buka Falcon WF → Pembaruan → Paket desain proyek → Periksa paket proyek. FWF memeriksa identitas, versi, parent serta rentang FP/FT. Paket yang lebih baru memerlukan review ulang jika metadata release berubah.
6. Setelah backup dan pengujian staging, centang konfirmasi dan pilih Pasang desain proyek atau Perbarui desain proyek. Checksum/header/paths ZIP diperiksa lagi sebelum file diganti.
7. Buka Lihat theme / Live Preview. Pilih aktivasi sendiri melalui Appearance → Themes bila sudah siap. Instal/update tidak mengubah theme aktif atau pilihan beranda.

Sesudah aktif, struktur beranda/Page/daftar/detail mengikuti template child. Isi tetap diedit melalui Pages/Project, bukan dari GitHub. Pilihan beranda tetap melalui Settings → Reading. Desain Global berlaku jika child memakai token Falcon.

## Bila ada masalah

- Repo/release belum tersedia: periksa alamat dan asset release. Khusus repo private, minta operator menyiapkan credential terbatas ke repo yang benar. Jangan memperlebar akses core sebagai jalan pintas.
- Parent/FP tidak cocok: perbarui core yang diperlukan, lalu periksa kembali proyek. Jangan menghapus parent theme untuk memaksa install.
- Slug dipakai theme lain: gunakan slug child yang benar. Installer menolak overwrite theme non-Falcon.
- Versi sama/lebih lama: tidak diterapkan. Source yang berubah memerlukan kenaikan versi dan release baru.
- Paket/release berubah: ulang Periksa dan review.
- Filesystem credential/immutable: gunakan installer WordPress atau deployment pipeline. Wizard credential FWF belum tersedia.
- Instal/update gagal: periksa hasil file/status theme dan recovery WordPress/backup sebelum retry. Database tidak otomatis rollback. Edit kode langsung pada child akan terganti oleh ZIP update; pengaturan desain database tetap tersimpan.

Putus koneksi proyek hanya melepas koneksi/kandidat; file theme dan konten dipertahankan. Build ini menerima satu child theme, belum plugin proyek atau paket multi-theme. Alpha.23 diuji dengan fixture dan repo GitHub trial publik sungguhan ke WordPress lokal. Penerimaan hosting/private/website klien tetap diperiksa terpisah.

## Auto-ZIP di repo desain terpisah — alpha.23

Pengelola menyiapkan sekali:

1. Dari checkout Falcon, jalankan `npm run export:project-automation -- /path/absolut/kit-baru`. Folder tujuan harus baru.
2. Salin `.falcon-tooling` dan `.github` dari kit ke repo proyek yang owner/target-nya sudah ditentukan. Jangan menyalin desain ke repo core. `project.json` dan runtime terdaftar berada di root repo proyek.
3. Tambahkan `release-notes/VERSI.md`, lalu commit seluruh source. Build output/node_modules tetap ignored.
4. Push main menjalankan workflow paket dan penerbitan; lihat Actions. Versi dari project.json, bukan nomor core. Versi baru menghasilkan ZIP child theme + project-manifest.json, tanpa unggah ZIP manual.
5. Naikkan versi dan notes untuk pembaruan berikutnya. Release published tidak ditimpa; draft gagal atau tag bentrok berhenti untuk diperiksa pengelola.

Workflow hanya menerbitkan prerelease development sebelum gate stable diterima. Token bawaan GitHub hanya mendapat izin tulis pada job publish; tidak memakai credential WordPress atau mengganti theme. Paket source dirty hanya untuk preview lokal.

Falcon memeriksa core/proyek dua kali sehari melalui WP-Cron dan menampilkan notifikasi admin bila versi terpasang lebih lama. Waktu aktual mengikuti kunjungan situs/cron server. Hasil otomatis bukan review manusia: buka Pembaruan, Periksa dan review sebelum Apply. Setelah update kode, perubahan blueprint tetap memerlukan review/apply setup tersendiri; isi lama tidak diimpor ulang otomatis.
