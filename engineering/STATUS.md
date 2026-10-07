# Status implementasi Falcon WF

Build: 0.1.0-alpha.3. Target akhir tetap Falcon WF 0.1. Ini prerelease lokal, belum production-ready.

Sudah diimplementasikan: bootstrap/lifecycle/initial migration, satu menu dan sembilan layar admin, settings identitas, registry dengan dependencies, CPT dasar, content repository dan revisions, izin/audit, installer FT bundled, parent theme fallback, maintenance, public/admin REST, policy/guard/tools dan inbound MCP, outbound proposal/human apply, private release/check/update melalui WP upgrader, build deterministik dan runtime inventory.

`release/inventory.json` adalah peta source → path paket → consumer → bukti. Nama file dari dokumen FWF-13 adalah usulan; implementasi menggabungkan tanggung jawab kecil tanpa membuat file/class kosong. Koneksi dinamis dibuktikan lewat integration/HTTP tests, bukan static check saja.

Alpha.2 menambahkan setup tiga langkah dengan status aktual/preflight/recovery guidance, editor custom field manusia, shared typed schema untuk tiga modul, metadata revisions/restore/conflict, scoped custom fields pada MCP, serta demo lokal berisi tiga konten dan dua ilustrasi AI. Header memakai Judul Situs/Tagline WordPress dan footer credit Asukayalab. Schema baru additive; nilai lama tidak dimigrasi/reset. Revisi builder menambahkan Jenis Konten, Field Groups, Kategori & Tag, generator listing, urutan menu, native editor fields dan scoped schema discovery. Repeater dan pemilih media dengan pencarian/pagination belum tersedia (dropdown hingga 100 pilihan terbaru). Project menjadi satu-satunya bawaan. Contract dan batas transaksi native ada di CONTENT-SCHEMA.md. Perapian UX menambahkan pembatalan, drag field/menu, buka/tutup semua field, centang massal dan generator limit/multiple-term. Panduan koneksi serta batas AI tersedia di guides/AI-CONNECTIONS.md.

Alpha.3 menyediakan contoh child theme generik Falcon Reference (ZIP terpisah), template beranda/Page/archive/detail Project, design tokens CSS per proyek dan fallback native Page tanpa FP. Beranda coming-soon saja yang memaksakan noindex; child beranda mengikuti visibility WordPress. Metadata berpassword ditolak di helper FT yang dipakai seluruh template. Versi FP/FT sekarang berasal dari map komponen yang terpisah dari versi rilis produk; header/API/dashboard diisi build, bukan hardcode versi lama. Contoh dipasang lokal untuk Live Preview tanpa aktivasi; pengaturan desain global melalui form dan installer repo klien masih belum tersedia.

## Gerbang rilis

| Gate | Status dan batas |
|---|---|
| Q01 Integrasi | Static inventory dan jalur runtime inti lokal lulus; contoh child theme generik membuktikan empat route lokal. Installer repo proyek dan pilot klien/Rizal belum diuji. |
| Q02 Packaging | ZIP/runtime/asset/hash/FT bundle dan repeat-build lulus; Build deterministik dan versi FP/FT independen diuji; ZIP contoh proyek terpisah dengan inventory/hash/compatibility. Provenance commit/dirty aktual di manifest artefak. Lisensi distribusi belum ditentukan. |
| Q03 Installer | Fresh/existing/retry/same-newer-older/corrupt/local role denial diuji. Preflight permission/temp/disk dan guidance retry tersedia; simulasi credential filesystem dan traversal ZIP diuji. Disk exhaustion nyata, credential wizard interaktif, recovery insiden dan matrix host belum lengkap. |
| Q04 Content | Native fields/revision/draft visibility, schema validation dan module disable/dependency diuji. Typed fields, media/relasi modul contoh, metadata conflict/revisions/restore serta edit manusia diuji. Builder dinamis dan taxonomy/listing diuji lokal; guard invalid/stale native form dan field-required publish diterapkan; contract pilot Rizal, repeater, adapter Gutenberg gabungan dan transaksi universal belum tersedia. Bukti regresi terbaru ada di EVIDENCE.md. |
| Q05 Admin/security | Admin/editor HTTP screens/action/nonce serta agent REST/XML-RPC boundaries diuji. Independent security review belum dilakukan. |
| Q06 Outbound | Adapter/proposal/review/error/disconnect dapat diuji dengan fixture; real provider request belum tersedia. **Belum lulus gate rilis.** |
| Q07 Inbound | Node client HTTP MCP initialization/discovery/read/create/edit/revoke lulus lokal. Client target dan staging HTTPS eksternal belum diuji. **Belum lulus gate rilis.** |
| Q08 Update/recovery | Actual WordPress plugin self-update dan theme upgrader diuji lokal dengan mocked GitHub metadata/download; pilihan tag prerelease local/staging, clean provenance, kandidat yang direview, corrupt/redirect/no-downgrade dan pre-install failure checks. Private release nyata, recovery insiden dan installer paket proyek belum lulus. |
| Q09 Data | Initial migration retry/settings preserve/nonpurge checks. Backup/restore rehearsal dan RPO/RTO belum tersedia. |
| Q10 UX | Frontend/default, child homepage/detail Project pada desktop/HP dan admin lokal direview browser; status ukuran dan bukti aktual di EVIDENCE. HTTP admin menguji human fields/nonce/stale denial. Matrix Safari/Android, seluruh keyboard flow dan host belum lengkap. |

## Akses/bahan yang diperlukan untuk kelulusan selanjutnya

- Repo private target dan owner aktual untuk remote/release/update acceptance; jangan mengarang repo.
- Credential OpenAI dan model available, policy privacy/usage live, konfigurasi server-side. Jangan mengirim secret lewat chat.
- Staging WordPress HTTPS yang dapat dijangkau client, serta pilihan client target (agent generic atau integrasi ChatGPT langsung dengan auth yang didukung).
- Backup/restore target dan rehearsal; lisensi/notice sebelum distribusi.
- V8/CV/aset untuk pilot Rizal, terpisah dari core AI.

Pada sesi implementasi awal belum ada remote GitHub. Checkout utama kini memakai remote private asukayalab/FalconWF dan runtime lokal sudah memakai mount clone. Belum ada release/update acceptance nyata atau provider request berbayar; pemasangan situs publik oleh user tidak dihitung sebagai kelulusan seluruh gate.
