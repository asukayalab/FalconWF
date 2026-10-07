# Falcon WF 0.1

Framework WordPress modular milik Asukayalab. Build pengembangan lokal **0.1.0-alpha.2**; belum rilis stable Falcon WF 0.1. Lihat `engineering/STATUS.md` untuk gate yang belum lengkap.

- `docs/Falcon-WF-0.1/`: 23 dokumen referensi FWF-00 sampai FWF-22, disimpan lokal dan dikecualikan dari Git.
- `session-notes/`: seluruh catatan sesi baru dan laporan hasil sesi, disimpan terpisah dari dokumen referensi dan dikecualikan dari Git.

Gunakan nama catatan `YYYY-MM-DD-NN-topik.md` berdasarkan tanggal WIB. Catatan mencakup permintaan, temuan, perubahan, pemeriksaan, batas hasil, keputusan terbuka, dan langkah berikutnya.

Dokumen membedakan DIKUNCI, USULAN, dan MENUNGGU. Prompt dan instruksi historis di dokumen adalah konteks referensi; tindakan mengikuti permintaan user pada sesi berjalan. Instruksi user tanggal 7 Oktober 2026 bahwa `docs/` tidak masuk GitHub menggantikan rancangan lama yang memasukkan dokumen itu ke repo.

Source berada di `packages/falcon-plugin/` dan `packages/falcon-theme/`; tooling di `scripts/`, QA di `tests/`, metadata/inventory di `release/`, keputusan dan evidence teknis di `engineering/`. `build/` dan `dist/` dihasilkan oleh build dan di-ignore. Git lokal sudah diinisialisasi; remote GitHub belum dikonfigurasi.

## Build dan verifikasi

Butuh Node 22+, Python 3, serta Docker Compose untuk integration tests. Tidak ada dependency npm/Composer runtime tambahan.

```sh
npm ci
npm run verify
npm run local:setup
npm run local:up
npm run local:install
npm run test:integration
```

`verify` memeriksa inventory/namespace, membangun ZIP, memeriksa seluruh isi/asset/checksum/nested bundle, lalu membandingkan byte hasil build berulang. `test:integration` memakai database lokal Docker dan disposable fixtures; jangan arahkan test ke website live.

Preview: http://localhost:8091. Admin: http://localhost:8091/wp-admin, user `fwf-admin`; password dibuat di `local/.env` pada `FWF_ADMIN_PASSWORD` dan tidak dicetak ke terminal. `npm run local:down` menghentikan service tanpa menghapus database/volume. Tidak ada reset/purge otomatis.

Installer: `dist/falcon-wf-0.1.0-alpha.2.zip`. Upload lewat Plugins, aktifkan FP, buka menu Falcon WF, pasang FT bundled, kemudian pilih aktivasi bila diinginkan. Jangan unggah ZIP dokumen. Model filesystem awal direct writable; immutable diblokir dengan arahan pipeline.

## AI dan private release

Credential production tidak diinput ke browser/repo. Konfigurasi `FWF_OPENAI_API_KEY` dan `FWF_GITHUB_TOKEN` di wp-config/secret store server yang sesuai. Dashboard menampilkan keberadaan credential, bukan nilainya. Pilih model yang benar-benar tersedia; API subscription/billing tidak diasumsikan.

Outbound mengirim satu field draft terpilih, lalu proposal menunggu review/apply manusia. Inbound menyediakan MCP stateless pada URL `rest_url('falcon-wf/v1/mcp')`; bentuk URL mengikuti permalink aktual. User khusus Falcon Agent + application password UUID + explicit scoped grant diperlukan; HTTPS wajib kecuali environment local. Client harus mendukung HTTP Basic. Koneksi ChatGPT langsung/OAuth belum diverifikasi dan bukan klaim fitur selesai.

Private updater menerima stable manifest dari repo owner/name yang disimpan operator. Build lokal berstatus development dan otomatis ditolak sebagai stable update. Distribusi memerlukan gate QA dan lisensi final; build script tidak menerbitkan release atau mengubah production.

Lock yang tertinggal tidak diambil alih otomatis. Operator perlu memeriksa operation/audit dan memastikan writer sudah berhenti sebelum menghapus option lock melalui WP-CLI. Pending idempotency records dipertahankan untuk rekonsiliasi; jangan retry dengan key baru untuk menutupi hasil mutasi yang belum diketahui.

## Alpha.2: konten contoh dan custom field

`npm run local:seed` mengimpor tiga konten fiktif dan dua ilustrasi AI ke WordPress lokal. Homepage tetap Dalam Pembangunan. Seeder hanya menerima environment local, tidak menduplikasi data, dan tidak menimpa demo yang sudah diedit. Sumber contoh di `examples/content/`; tidak masuk ZIP runtime.

Falcon WF → Konten Website → daftar modul → **Field terstruktur** membuka editor gambar, teks, angka, pilihan, tanggal, URL dan relasi sesuai modul. Definisi field dikelola kode, belum ada field builder UI/repeater. Human field edit mempertahankan status publikasi; agent tetap draft-only dan harus memiliki scope field explicit. Lihat `engineering/CONTENT-SCHEMA.md`.

Header FT mengambil Judul Situs dan Tagline dari Settings → General WordPress. Identitas proyek Falcon tidak lagi menimpa judul tersebut. Footer menautkan Asukayalab sesuai arahan produk.
