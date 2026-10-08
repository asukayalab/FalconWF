# Status implementasi Falcon WF

Build produk/FP: 0.1.0-alpha.16; FT: 0.1.0-alpha.6. Target akhir tetap Falcon WF 0.1. Ini prerelease lokal, belum production-ready.

Sudah diimplementasikan: bootstrap/lifecycle/initial migration, satu menu dan sebelas layar admin, settings identitas, registry dengan dependencies, CPT dasar, content repository dan revisions, izin/audit, installer FT bundled, parent theme fallback, maintenance, public/admin REST, policy/guard/tools dan inbound MCP, outbound proposal/human apply, public/private release/check/update melalui WP upgrader, build deterministik dan runtime inventory.

`release/inventory.json` adalah peta source → path paket → consumer → bukti. Nama file dari dokumen FWF-13 adalah usulan; implementasi menggabungkan tanggung jawab kecil tanpa membuat file/class kosong. Koneksi dinamis dibuktikan lewat integration/HTTP tests, bukan static check saja.

Alpha.2 menambahkan setup tiga langkah dengan status aktual/preflight/recovery guidance, editor custom field manusia, shared typed schema untuk tiga modul, metadata revisions/restore/conflict, scoped custom fields pada MCP, serta demo lokal berisi tiga konten dan dua ilustrasi AI. Header memakai Judul Situs/Tagline WordPress dan footer credit Asukayalab. Schema baru additive; nilai lama tidak dimigrasi/reset. Revisi builder menambahkan Jenis Konten, Field Groups, Kategori & Tag, generator listing, urutan menu, native editor fields dan scoped schema discovery. Repeater dan pemilih media dengan pencarian/pagination belum tersedia (dropdown hingga 100 pilihan terbaru). Project menjadi satu-satunya bawaan. Contract dan batas transaksi native ada di CONTENT-SCHEMA.md. Perapian UX menambahkan pembatalan, drag field/menu, buka/tutup semua field, centang massal dan generator limit/multiple-term. Panduan koneksi serta batas AI tersedia di guides/AI-CONNECTIONS.md.

Alpha.3 menyediakan contoh child theme generik Falcon Reference (ZIP terpisah), template beranda/Page/archive/detail Project, design tokens CSS per proyek dan fallback native Page tanpa FP. Beranda coming-soon saja yang memaksakan noindex; child beranda mengikuti visibility WordPress. Metadata berpassword ditolak di helper FT yang dipakai seluruh template. Versi FP/FT sekarang berasal dari map komponen yang terpisah dari versi rilis produk; header/API/dashboard diisi build, bukan hardcode versi lama. Contoh dipasang lokal untuk Live Preview tanpa aktivasi; installer repo klien masih belum tersedia.

Alpha.4 menambahkan Desain Global untuk theme aktif: font lokal, warna, teks/H1–H6, lebar dan spacing. Contract/validasi/renderer dimiliki FT; FP menyediakan form manusia dengan capability, nonce, revision dan lock. Nilai per stylesheet tetap dirender tanpa FP; kosong mengikuti theme default. Child Reference alpha.2 memakai token yang sama. Form hanya tersedia ketika FT kompatibel aktif; bukan installer repo atau fitur SEO metadata.

Alpha.5 menambahkan koneksi repo proyek terpisah, check/review/install/update satu child theme dengan manifest proyek, compatibility FP/FT dan ZIP validation bersama core. Tidak mengaktifkan theme atau mengubah konten. Live distribusi GitHub proyek dan plugin proyek tetap belum diuji/didukung.

## Gerbang rilis

| Gate | Status dan batas |
|---|---|
| Q01 Integrasi | Static inventory dan jalur runtime inti lokal lulus; contoh child theme generik membuktikan empat route lokal. Installer repo proyek diuji fixture/actual WordPress lokal; live repo dan pilot klien/Rizal belum diterima. |
| Q02 Packaging | ZIP/runtime/asset/hash/FT bundle dan repeat-build lulus; Build deterministik dan versi FP/FT independen diuji; ZIP contoh proyek terpisah dengan inventory/hash/compatibility. Provenance commit/dirty aktual di manifest artefak. Lisensi distribusi belum ditentukan. |
| Q03 Installer | Fresh/existing/retry/same-newer-older/corrupt/local role denial diuji. Preflight permission/temp/disk dan guidance retry tersedia; simulasi credential filesystem dan traversal ZIP diuji. Disk exhaustion nyata, credential wizard interaktif, recovery insiden dan matrix host belum lengkap. |
| Q04 Content | Native fields/revision/draft visibility, schema validation dan module disable/dependency diuji. Typed fields, media/relasi modul contoh, metadata conflict/revisions/restore serta edit manusia diuji. Builder dinamis dan taxonomy/listing diuji lokal; guard invalid/stale native form dan field-required publish diterapkan; contract pilot Rizal, repeater, adapter Gutenberg gabungan dan transaksi universal belum tersedia. Bukti regresi terbaru ada di EVIDENCE.md. |
| Q05 Admin/security | Admin/editor HTTP screens/action/nonce serta agent REST/XML-RPC boundaries diuji. Independent security review belum dilakukan. |
| Q06 Outbound | Adapter/proposal/review/error/disconnect dapat diuji dengan fixture; real provider request belum tersedia. **Belum lulus gate rilis.** |
| Q07 Inbound | Node client HTTP MCP initialization/discovery/read/create/edit/revoke lulus lokal. Client target dan staging HTTPS eksternal belum diuji. **Belum lulus gate rilis.** |
| Q08 Update/recovery | Actual WordPress plugin self-update dan theme upgrader diuji lokal dengan mocked GitHub metadata/download; pilihan tag prerelease local/staging, clean provenance, kandidat yang direview, corrupt/redirect/no-downgrade dan pre-install failure checks. Transport publik sungguhan dan hash/ZIP FPalpha.14/FTalpha.6 terverifikasi tanpa token pada alpha.15. Pemasangan update hosting, recovery insiden dan live distribusi paket proyek belum lulus. |
| Q09 Data | Initial migration retry/settings preserve/nonpurge checks. Fondasi backup/restore local/staging bounded tersedia; fixture tabel kloning menguji pemulihan/rollback. Antrean/progres/retry backup tersedia lokal. Backup berkala lokal tersedia alpha.12 dan media terpilih alpha.13; journal/rescue terputus lokal tersedia alpha.14; cloud, host rehearsal dan RPO/RTO belum tersedia. |
| Q10 UX | Frontend/default, child homepage/detail Project pada desktop/HP dan admin lokal direview browser; status ukuran dan bukti aktual di EVIDENCE. HTTP admin menguji human fields/nonce/stale denial. Matrix Safari/Android, seluruh keyboard flow dan host belum lengkap. |
| Q11 Panduan pengguna | Panduan lengkap FWF 0.1 (pengisian/aktivasi koneksi core, proyek dan dua arah AI, desain, SEO serta recovery) belum dibuat; disiapkan menjelang rilis sesuai permintaan user. |

## Akses/bahan yang diperlukan untuk kelulusan selanjutnya

- Repo target dan owner aktual untuk remote/release/update acceptance; jangan mengarang repo.
- Credential OpenAI dan model available, policy privacy/usage live, konfigurasi server-side. Jangan mengirim secret lewat chat.
- Staging WordPress HTTPS yang dapat dijangkau client, serta pilihan client target (agent generic atau integrasi ChatGPT langsung dengan auth yang didukung).
- Backup/restore target dan rehearsal; lisensi/notice sebelum distribusi.
- V8/CV/aset untuk pilot Rizal, terpisah dari core AI.

Pada sesi implementasi awal belum ada remote GitHub. Checkout utama kini memakai remote asukayalab/FalconWF (publik sejak 8 Oktober 2026) dan runtime lokal sudah memakai mount clone. Belum ada release/update acceptance nyata atau provider request berbayar; pemasangan situs publik oleh user tidak dihitung sebagai kelulusan seluruh gate.

Alpha.6 menyediakan SEO/GEO metadata dasar opt-in melalui Identitas & Kontak. FT owns renderer/config; native WordPress title/canonical/robots/sitemap dipertahankan. Public singular/latest-posts home saja; archive/search/pagination, preview/private/password dan coming-soon tidak mendapat metadata tambahan. Lihat SEO.md. Live search/rich-result/AI citation acceptance tidak diklaim. Panduan pengguna lengkap menjadi deliverable menjelang rilis 0.1, sesuai permintaan user. Desain Global tetap dibekukan; referensi Oxygen Builder diingatkan saat area itu dibahas lagi.

Alpha.7 menambahkan Backup & Restore lokal: pilihan komponen, ZIP tanpa password, private volume, download/import/review dan human confirmed restore dengan safety snapshot. Batas dan exclusions wajib dibaca di BACKUP.md; bukan backup seluruh server/account atau kelulusan disaster recovery production. Jadwal/retensi/media terpilih/Google Drive masih tahap berikutnya.

Alpha.8 menambahkan estimasi tiap komponen dan total pilihan tanpa double count settings/database. Fixed64MiB files dan32MiB DB dilepas; file diproses stream dan row DB diekspor per batch ke JSONL. Batas resource/timeout/upload hosting tetap berlaku; persistent jobs, crash recovery, jadwal dan cloud belum selesai. Kontrak terkini: BACKUP.md.

Alpha.9 menambahkan antrean backup latar belakang WP-Cron, checkpoint ZIP perbagian, progres polling dengan nonce/capability, lanjutkan/coba lagi dan pembatalan. Snapshot DB tetap satu transaksi; snapshot terputus diulang, tahap ZIP diteruskan dari snapshot privat. Backup keselamatan restore memakai mesin yang sama. Ini tidak menyelesaikan crash rescue restore atau ketergantungan waktu eksekusi per snapshot/entry; cron server dan hosting matrix tetap perlu diuji.

Alpha.10 merapikan daftar backup dua baris (tanggal/jam, filename+size), catatan admin/klien, tombol Download ZIP/Restore/Delete. Catatan terlindung dari rewind restore. Delete membutuhkan review/hash/konfirmasi dan tidak menghapus konten situs; lease/journal/active-job guards tetap berlaku.

Alpha.11 menambahkan retensi opt-in dan perlindungan arsip. Default tidak aktif, jumlah awal5 (rentang1–1000). Setelah ZIP background baru tervalidasi, arsip biasa tertua dipangkas; arsip bergembok tidak masuk kuota. Save/unprotect tidak langsung menghapus. Backup keselamatan sinkron tidak memicu retensi. Tombol keempat Lindungi backup ini/Lepas Perlindungan dan Delete disabled untuk arsip terlindungi. Urutan berikutnya: BACKUP-ROADMAP.md.

Alpha.12 menambahkan backup harian/mingguan, jam/hari, komponen, zona waktu WordPress saat save, jadwal berikutnya dan riwayat hasil. Scheduler hanya memanggil Manager queue; retensi/protection tetap sama. Slot terlambat satu susulan, overlap dilewati, generation/durable claim mencegah duplikat. Izin pemilik dicabut memblokir jadwal; deaktivasi mempertahankan data, aktivasi mengatur kembali event. Unknown scheduler crash gap tetap perlu operator review. Worker Docker memakai due-now untuk hook berkala. Cloud/media terpilih/recovery hosting tetap berikutnya.

Alpha.13 menambahkan pilihan attachment melalui Media Library pada backup manual dan berkala, estimasi file/dependensi/metadata, serta restore terbatas pada attachment terpilih. Original, thumbnail, edit-backup dan cover ikut; canonical native metadata dipulihkan, custom metadata plugin dipertahankan. Media terpilih tidak digabung database seluruh situs. Safety archive tetap database/uploads lengkap; schema3/exact-code restore berlaku. Crash rescue dan cloud berikutnya.

Alpha.14 mengganti file-journal lama dengan checkpoint privat signed/atomic sebelum write, penanda commit dalam transaksi DB yang sama dan lease koneksi MariaDB. Restore terputus direview/dikonfirmasi manusia: sebelum commit undo file; commit terbukti mempertahankan hasil lalu cleanup. CLI rescue SHORTINIT melewati plugin/theme normal. Pergantian koneksi/hasil DB tak pasti tetap diblokir untuk operator; tidak ada replay SQL/auto theme switch. Core/config/DB/drop-in rusak, power loss dan host matrix belum disertifikasi.

Alpha.15: updater repo publik tanpa token, URL GitHub dinormalisasi, perbandingan versi dan catatan release ditampilkan. Transport private opsional fallback 401/404; credential constant/environment dibaca oleh satu GitHubClient. Metadata/refetch/ZIP hash/runtime/nonce/capability serta larangan downgrade dipertahankan. Pemasangan hosting alpha.15 dan update rilis berikutnya masih menunggu tindakan operator.

Alpha.16: sumber core bawaan dan pilihan Stabil/Uji coba menggantikan tag versi manual. Pengaturan trial lama menjadi penemuan prerelease terbaru; apply memeriksa ulang release konkret dan menolak kandidat lama. Koneksi teknis dilipat untuk pengelola. Repo proyek tetap memiliki kontrak pengelola terpisah. Bukti runtime/hosting dicatat terpisah.

Prioritas 8 Oktober: selesaikan run otomatisasi rilis yang berhasil, kemudian trial update/desain/connector AI di ar.obie.my.id. Rizal ditunda sesuai keputusan pengguna. Rincian deliverable dan batas fondasi desain/MCP ada di NEXT-STEPS.md; kedua arah AI tetap wajib untuk 0.1. Dua run GitHub awal gagal dalam tes CI fresh; release otomatis dan acceptance hosted masih terbuka.

Run Actions pertama alpha.16 gagal pada guard FT sebelum bundled installer; urutan harness diperbaiki. Run kedua melewati guard/installer tetapi gagal karena volume backup baru di CI belum UID33/mode0700. Compose lokal kini menyiapkan volume privat sebelum WordPress/CLI. Full suite dari WordPress kosong di Docker terpisah dan simulasi penyiapan tiga asset rilis lulus; penerbitan GitHub tetap menunggu commit/push perbaikan dan run baru yang berhasil.
