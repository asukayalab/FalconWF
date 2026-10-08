# Memasang dan memperbarui desain proyek

FWF core (FP/FT) dan desain klien memakai koneksi yang terpisah. Commit/push menyimpan source di GitHub; update WordPress memerlukan GitHub Release yang berisi manifest dan ZIP hasil build dari commit bersih.

## Setelah repo klien dan paket tersedia

1. Developer menyiapkan child theme dengan Template: falcon-theme, versi komponen dan project-manifest.json sesuai engineering/PROJECT-PACKAGES.md. Repo/owner proyek harus diketahui; contoh Falcon Reference di sini hanya contoh lokal.
2. Developer menerbitkan release proyek dengan project-manifest.json dan ZIP {slug-theme}-{version}.zip. Version rilis proyek harus cocok dengan tag; versi ZIP komponen dapat berbeda. Jangan sertakan DB, uploads, secrets, docs atau session notes.
3. Repo publik tidak memerlukan token. Untuk repo private saja, operator menyediakan FWF_PROJECT_GITHUB_TOKEN read-only, terpisah dari token core. Jangan kirim token lewat chat atau isi form repo.
4. Buka Falcon WF → Proyek & Koneksi → Paket desain proyek. Isi URL GitHub atau owner/name repo, Project ID sesuai manifest, slug folder child theme, dan tag bila prerelease. Kosongkan tag untuk stable terbaru. Prerelease hanya local/staging.
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

Putus koneksi proyek hanya melepas koneksi/kandidat; file theme dan konten dipertahankan. Build ini menerima satu child theme, belum plugin proyek atau paket multi-theme. Pengujian transport menggunakan fixture lokal; live GitHub/client acceptance belum lulus.
