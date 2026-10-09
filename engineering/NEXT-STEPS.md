# Prioritas menuju Falcon WF 0.1

Keputusan pengguna 8 Oktober 2026: otomatisasi rilis dan tesnya dikerjakan paralel. Trial memakai ar.obie.my.id; pilot Rizal ditunda. Backup/cloud dan pengembangan Desain Global dibekukan sementara. Referensi Oxygen dibahas kembali saat Desain Global dilanjutkan.

## 1. Distribusi core praktis

- Push main memicu verifikasi, integration WordPress terisolasi dan build dari commit bersih. Versi baru menjadi GitHub prerelease dengan manifest, FP ZIP, FT ZIP dan catatan perubahan. Versi yang sudah terbit tidak ditimpa.
- Uji kegagalan, benturan tag, provenance dan hash sebelum publikasi. Eksekusi GitHub Actions nyata harus dibuktikan sesudah workflow di-push; tes adapter lokal bukan bukti penerbitan GitHub.
- Pengguna melaporkan `ar.obie.my.id` sudah diperbarui manual ke alpha.16; pemeriksaan baca-saja 9 Oktober 2026 mengonfirmasi FPalpha.16, FTalpha.6 aktif, admin dan frontend coming-soon merender. Site Health Good dengan rekomendasi cron native WP; belum host recovery/AI acceptance. Pada prerelease berikutnya, trial jalur Uji coba: backup, periksa, update FP, cek ulang, dan update FT bila ada versi baru. Jalur itu harus menemukan versi berikutnya tanpa mengetik tag; pemasangan manual alpha.16 belum membuktikannya.

## 2. Kebutuhan desain website

Fondasi tersedia: parent FT, contoh child theme, build manifest proyek, installer/update child theme, helper konten dan token Desain Global. Ini belum membuktikan perjalanan pengguna lengkap di hosting.

Deliverable berikutnya:

- Satu starter child theme untuk website trial dengan header/footer/menu, beranda, Page, daftar/detail konten, aset dan tampilan responsif. Template/desain berada di repo; copywriting, media dan nilai field tetap di WordPress.
- Petakan setiap bagian desain ke konten editable: judul, teks, CTA/link, gambar dan field dinamis. Gunakan schema/helper existing, jangan membuat database/editor kedua. Pilihan media dan navigasi mengikuti kemampuan WordPress yang nyata.
- Paket desain trial memiliki owner/repo yang ditentukan pengguna. Buat automation paket proyek dari builder existing; workflow core tidak menerbitkan child theme klien.
- Setup pengelola satu kali: koneksi, pemasangan, Live Preview, aktivasi manusia dan pilihan beranda. Klien rutin mengedit isi/meninjau desain tanpa mengisi tag atau manifest. Pengaturan repo proyek saat ini masih teknis dan perlu disederhanakan.
- Recheck frontend desktop/mobile, link/menu, konten kosong, escaping, SEO dasar serta update desain yang mempertahankan konten dan pilihan theme aktif. Trial remote harus dicatat terpisah dari fixture lokal.

## 3. Connector AI → WordPress melalui FWF

Endpoint MCP, scope, revisions dan empat tool konten sudah tersedia. Belum ada penerimaan client AI nyata; tidak perlu membuat endpoint duplikat.

- Tentukan aplikasi client pertama dan auth yang didukung. Siapkan adapter/client setup untuk MCP existing; Basic Application Password tidak dianggap otomatis kompatibel dengan semua aplikasi cloud.
- Buat alur pengelola yang praktis untuk actor Falcon Agent, credential dan scope; simpan secret pada tempat credential client, bukan repo/log/chat. Tampilkan endpoint aktual dari WordPress, bukan mengasumsikan permalink tertentu.
- Trial HTTPS ar.obie.my.id: initialize, discovery schema, baca konten yang diizinkan, buat draft, edit draft dengan revision, tolak scope asing dan cabut akses. Akses nyata harus dilakukan client terpilih, bukan hanya skrip fixture.
- Coding template dilakukan di repo desain lalu didistribusikan lewat paket proyek; MCP menangani isi draft. Publishing, delete, theme switch, sistem dan update tetap aksi manusia.
- Jalur WordPress → AI tetap wajib untuk 0.1: uji provider sungguhan dan proposal/human apply sesudah inbound praktis. Credential/model/billing harus tersedia; tidak memakai mock sebagai acceptance.

## 4. Penutupan 0.1

Sesudah tiga jalur di atas berjalan: recheck instalasi baru/update, pemulihan yang didukung, panduan pengguna sederhana dan gate dalam STATUS.md. Jangan menamai alpha sebagai stable atau selesai hanya karena build/test lokal lulus. Rizal dilanjutkan saat pengguna memintanya; fitur cloud backup kemudian.

## Target trial 9 Oktober 2026

- Portfolio pribadi Ar. Obie, starter sederhana. Source/paket desain disiapkan lokal di ignored local/artifacts/ar-obie; pengguna meminta tidak push ke GitHub Asukayalab. Repo/distribusi remote desain belum ditentukan.
- Client inbound pertama: ChatGPT custom connector/plugin. Auth existing Basic belum kompatibel dengan pilihan auth yang didokumentasikan ChatGPT; Fondasi OAuth tersedia pada build lokal alpha.17; pemasangan HTTPS dan uji akun ChatGPT masih diperlukan. Contract di CHATGPT-CONNECTOR.md.
- Outbound hosting menampilkan credential belum dikonfigurasi; request provider nyata menunggu credential/model server-side dan policy yang direview. Jangan kirim credential lewat chat.
