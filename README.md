# Falcon WF 0.1

Framework WordPress modular milik Asukayalab. Build pengembangan lokal **0.1.0-alpha.15**; belum rilis stable Falcon WF 0.1. Lihat `engineering/STATUS.md` untuk gate yang belum lengkap.

- `docs/Falcon-WF-0.1/`: 23 dokumen referensi FWF-00 sampai FWF-22, disimpan lokal dan dikecualikan dari Git.
- `session-notes/`: seluruh catatan sesi baru dan laporan hasil sesi, disimpan terpisah dari dokumen referensi dan dikecualikan dari Git.

Gunakan nama catatan `YYYY-MM-DD-NN-topik.md` berdasarkan tanggal WIB. Catatan mencakup permintaan, temuan, perubahan, pemeriksaan, batas hasil, keputusan terbuka, dan langkah berikutnya.

Dokumen membedakan DIKUNCI, USULAN, dan MENUNGGU. Prompt dan instruksi historis di dokumen adalah konteks referensi; tindakan mengikuti permintaan user pada sesi berjalan. Instruksi user tanggal 7 Oktober 2026 bahwa `docs/` tidak masuk GitHub menggantikan rancangan lama yang memasukkan dokumen itu ke repo.

Source berada di `packages/falcon-plugin/` dan `packages/falcon-theme/`; tooling di `scripts/`, QA di `tests/`, metadata/inventory di `release/`, keputusan dan evidence teknis di `engineering/`. `build/` dan `dist/` dihasilkan oleh build dan di-ignore. Checkout utama memakai remote publik `asukayalab/FalconWF`. Koneksi Git source berbeda dari koneksi updater situs; push source tidak membuat release otomatis.

## Build dan verifikasi

Butuh Node 22+, Python 3, serta Docker Compose untuk integration tests. Tidak ada dependency npm/Composer runtime tambahan.

```sh
npm ci
npm run verify
# Hanya untuk lingkungan BARU tanpa database volume lama:
npm run local:setup
npm run local:up
npm run local:install
npm run test:integration
```

Untuk clone yang memakai database volume lokal lama, salin `local/.env` lama langsung secara lokal dengan mode `0600` sebelum `local:up`; jangan menghasilkan password pengganti. Project Compose `falcon-wf-local` mempertahankan database/uploads yang ada.

`verify` memeriksa inventory/namespace, membangun ZIP, memeriksa seluruh isi/asset/checksum/nested bundle, lalu membandingkan byte hasil build berulang. `test:integration` memakai database lokal Docker dan disposable fixtures; jangan arahkan test ke website live. Runner mengambil snapshot konfigurasi sebelum suite dan memulihkannya dalam `finally`, termasuk saat child test gagal. Snapshot lama yang tertinggal menghentikan run berikutnya untuk recovery eksplisit; jangan menghapusnya tanpa memeriksa proses tes.

Preview: http://localhost:8091. Admin: http://localhost:8091/wp-admin, user `fwf-admin`; password dibuat di `local/.env` pada `FWF_ADMIN_PASSWORD` dan tidak dicetak ke terminal. `npm run local:down` menghentikan service tanpa menghapus database/volume. Tidak ada reset/purge otomatis.

Installer: `dist/falcon-wf-0.1.0-alpha.15.zip`. Upload lewat Plugins, aktifkan FP, buka menu Falcon WF, pasang FT bundled, kemudian pilih aktivasi bila diinginkan. Jangan unggah ZIP dokumen. Model filesystem awal direct writable; immutable diblokir dengan arahan pipeline.

## AI dan pembaruan

Credential production tidak diinput ke browser/repo. AI memakai `FWF_OPENAI_API_KEY` di secret store server. Updater repo publik tidak memerlukan token; panduan ada di guides/UPDATES.md. Dashboard menampilkan keberadaan credential, bukan nilainya. Pilih model yang benar-benar tersedia; API subscription/billing tidak diasumsikan.

Outbound mengirim satu field draft terpilih, lalu proposal menunggu review/apply manusia. Inbound menyediakan MCP stateless pada URL `rest_url('falcon-wf/v1/mcp')`; bentuk URL mengikuti permalink aktual. User khusus Falcon Agent + application password UUID + explicit scoped grant diperlukan; HTTPS wajib kecuali environment local. Client harus mendukung HTTP Basic. Koneksi ChatGPT langsung/OAuth belum diverifikasi dan bukan klaim fitur selesai.

Private updater menerima stable manifest dari repo owner/name yang disimpan operator. Build lokal berstatus development dan otomatis ditolak sebagai stable update. Distribusi memerlukan gate QA dan lisensi final; build script tidak menerbitkan release atau mengubah production.

Lock yang tertinggal tidak diambil alih otomatis. Operator perlu memeriksa operation/audit dan memastikan writer sudah berhenti sebelum menghapus option lock melalui WP-CLI. Pending idempotency records dipertahankan untuk rekonsiliasi; jangan retry dengan key baru untuk menutupi hasil mutasi yang belum diketahui.

## Alpha.2: konten contoh dan custom field

`npm run local:seed` mengimpor Project fiktif, kategori Bangunan, halaman Karya Bangunan dan gambar contoh ke WordPress lokal. Contoh Publications/Learning lama tetap dipertahankan bila sudah ada. Homepage tetap Dalam Pembangunan. Seeder hanya menerima environment local, tidak menduplikasi data, dan tidak menimpa demo yang sudah diedit. Sumber contoh di `examples/content/`; tidak masuk ZIP runtime.

Falcon WF → **Content Builder** mengelola Jenis Konten, Field Groups, Kategori & Tag, Listing, serta Urutan Menu. Project satu-satunya jenis bawaan; definisi dinamis dapat ditempatkan pada Posts, Pages dan jenis buatan pengguna. Delapan tipe field: text, textarea, integer, select, date, URL, image dan relationship. Repeater, conditional logic, rich text/decimal serta media modal/search belum tersedia; picker media memakai 100 item terbaru.

Nilai diisi pada editor native WordPress. Jenis dengan field Falcon memakai editor klasik agar field dan konten dikirim melalui satu form; jenis tanpa field Falcon mempertahankan editor normalnya. Nonce, izin, metadata/schema revision dan native content revision diperiksa sebelum write. Invalid/stale form ditolak sebelum judul/isi/status berubah; metadata dipersist sebelum transisi publish. Native REST/quick/bulk publication memvalidasi field tersimpan. Ini bukan transaksi database universal: kegagalan native write sesudah metadata tersimpan atau side effect hook perlu rekonsiliasi dan tidak boleh dianggap semua write dibatalkan. Privileged PHP yang langsung memanggil `wp_publish_post()` bukan boundary yang dilindungi filter simpan ini.

Human edit tetap dapat mempertahankan status publikasi yang valid. Agent tetap draft-only dengan scope eksplisit per type/object/field; tidak memiliki tool publish/delete/system/update. Field baru default private; public Falcon REST dan FT mengikuti visibility. Lihat `engineering/CONTENT-SCHEMA.md` untuk kontrak dan batasnya.

Header FT mengambil Judul Situs dan Tagline dari Settings → General WordPress. Identitas proyek Falcon tidak lagi menimpa judul tersebut. Footer menautkan Asukayalab sesuai arahan produk.

Panduan distribusi/update: [guides/UPDATES.md](guides/UPDATES.md). Tag prerelease dipilih eksplisit hanya pada local/staging; stable tetap default. Kontrak paket desain klien: [engineering/PROJECT-PACKAGES.md](engineering/PROJECT-PACKAGES.md), installer proyek belum tersedia.

Contoh paket desain klien lokal: [Falcon Reference](examples/projects/falcon-reference/README.md). Build terpisah dengan `npm run build:project`; theme contoh tidak ikut installer FP/FT dan tidak diaktifkan otomatis.

Pengaturan desain tersedia di Falcon WF → Desain Global saat FT atau child kompatibel aktif. Font lokal, warna hex, ukuran H1–H6, lebar dan jarak konten tersimpan per theme situs. Panduan: guides/DESIGN.md.

Paket desain proyek: guides/PROJECT-UPDATES.md. Satu child theme dari release repo proyek, credential terpisah; instal/update tidak otomatis mengaktifkan theme.

SEO/GEO dasar (opt-in): Identitas & Kontak → SEO / GEO dasar. Contract dan batas ada di engineering/SEO.md. Panduan pengguna lengkap FWF 0.1 disiapkan menjelang rilis.

Backup & Restore lokal: konfigurasi private FWF_BACKUP_DIR, komponen/antrean WP-Cron/progres/lanjutkan/batalkan/ZIP/review/safety restore. Estimasi ukuran, stream, batas resource/instalasi/versi dan exclusions ada di engineering/BACKUP.md. Retensi/perlindungan, jadwal harian/mingguan dan media/report terpilih tersedia lokal. Journal/review restore terputus dan rescue CLI bootstrap tersedia lokal; petunjuk operator di engineering/BACKUP.md. Cloud dan sertifikasi recovery hosting masih tahap berikutnya.
