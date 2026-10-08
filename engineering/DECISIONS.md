# Keputusan implementasi awal

7 Oktober 2026. Ini keputusan teknis implementer yang dapat direview, bukan klaim semua detail sudah disetujui user atau software sudah siap produksi.

| Area | Pilihan dan alasan |
|---|---|
| Identitas | Plugin `falcon-wf`, theme `falcon-theme`, PHP namespace `FalconWF`, REST `falcon-wf/v1`, text domain sesuai komponen. |
| Versi | Product target Falcon WF 0.1; build awal `0.1.0-alpha.1`, kini produk/FP `0.1.0-alpha.14`, FT `0.1.0-alpha.6`. `release/components.json` menjadi input metadata build. Stable belum diterbitkan. |
| Runtime | Minimum WP 6.7/PHP 8.3, single-site. Kombinasi yang benar-benar diuji: WP 7.1.2/PHP 8.3.35/MariaDB 10.11 Docker. Range lain belum disertifikasi. |
| Persistence | Posts native, title/content/excerpt dan revisions native. Modul Projects/Publications/Learning memakai CPT. Field proyek lanjutan belum disediakan. Settings/operation/proposal di options non-autoload; audit di tabel terpisah. |
| Loader | Autoloader namespace internal kecil tanpa dependency Composer. Tidak membuat Composer/vendor kosong. Composer dapat diperkenalkan saat ada dependency PHP nyata. |
| Build | Node >=22 tanpa dependency npm; Python 3 stdlib untuk ZIP deterministik. CSS source → filename hashed → manifest → enqueue. Tidak membuat JS kosong. |
| Lokal | Docker Compose terisolasi, port 8091 hanya 127.0.0.1, DB tidak diekspos. Image digest dikunci setelah runtime aktual berhasil diuji. Credential lokal di `local/.env`, file mode 0600 dan di-ignore. |
| Installer | FT bundled tervalidasi path/hash/version, hanya pasang jika belum ada; same/newer version no-op. Older FT diarahkan ke updater, bukan overwrite diam-diam. Switch aksi manusia terpisah dengan nonce dan konfirmasi. State setup: not_started/installed/activated/skipped. |
| Filesystem | Dukungan implementasi awal direct writable WordPress. Immutable/file-mod-disabled diblokir. Credential-based filesystem diarahkan ke alur WP manual/pipeline; wizard credential interaktif belum dibangun. |
| Izin | Administrator memperoleh capability FWF; editor konten memakai capability WP. Actor connector adalah user khusus `fwf_agent`, tidak memiliki publish/delete/system capability. Agent dilarang dashboard, REST native, dan application-password XML-RPC. |
| Inbound | MCP stateless JSON response, protokol 2025-03-26, autentikasi WP application password + UUID grant. HTTPS wajib kecuali WP_ENVIRONMENT_TYPE=local. Client Node HTTP sudah diuji; koneksi ChatGPT/OAuth belum diverifikasi. |
| Scope | Per actor/credential, action/type/object/field allowlist, expiry 30 hari. Create draft menambahkan ID hasil ke scope actor; mutation rechecks policy dan revision. Grant dicabut terpisah dari password WP. |
| Outbound | OpenAI Responses endpoint fixed, server-side `FWF_OPENAI_API_KEY`, model dipilih operator. `store=false`, satu field draft yang dipilih, proposal sebelum human apply. Tidak ada tools provider atau auto-publish. |
| Limit awal | Inbound 30 tool request/menit/actor. Outbound 20 request/hari/situs, max output 1.200 token/request. Tidak menjanjikan budget rupiah/dolar tanpa harga/model terverifikasi. |
| Retention | Proposal 24 jam; completed idempotency records 7 hari; audit 30 hari. Daily WP-Cron cleanup. Pending/unknown operations dipertahankan untuk rekonsiliasi manual. Nilai dapat diubah pada tahap policy live. |
| Error/audit | Stable error codes dan safe messages; audit hanya kolom allowlist, request ID sama dalam satu request PHP. Tidak menyimpan prompt/response/token dalam audit. Pending operation record mencegah retry setelah hasil tidak diketahui. |
| Private release | Repo owner/name fixed, server-side `FWF_GITHUB_TOKEN` dengan Contents read terbatas ke repo. Manifest stable/clean source, package ID/type/version/runtime/hash diverifikasi. Mendukung asset 200 dan 302; Authorization tidak diteruskan ke signed download host. |
| Update | Satu komponen per aksi manual, backup/staging confirmation, current/target version dan lock. WP upgrader untuk write/recovery kode; rollback database tidak dijanjikan. Paket proyek dan pipeline immutable belum didukung. |
| Ekstensi | `fwf_ready` menyediakan Bootstrap setelah plugins_loaded; `fwf_register_modules` pada init menyediakan Registry untuk descriptor proyek. Missing/cyclic dependencies dan duplicate IDs/CPT ditolak. |
| Git/dokumen | docs dan session-notes lokal saja. engineering memuat kontrak/status/evidence teknis, bukan salinan seluruh referensi atau transcript. Awalnya Git lokal tanpa remote; checkout utama kini remote asukayalab/FalconWF. Publish/release tetap tindakan terpisah. |

Sumber verifikasi teknis: [WordPress requirements](https://wordpress.org/about/requirements/), [PHP support](https://www.php.net/supported-versions.php), [Theme installer](https://developer.wordpress.org/reference/classes/theme_upgrader/install/), [REST auth](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/), [Application password constraints](https://developer.wordpress.org/reference/hooks/wp_authenticate_application_password_errors/), [Responses text](https://developers.openai.com/api/docs/guides/text), [MCP transport](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports), [GitHub assets](https://docs.github.com/en/rest/releases/assets?apiVersion=2022-11-28).

## Tambahan Alpha.2 — 7 Oktober 2026

- Custom fields memakai post meta native dengan satu contract typed di Content/Schema, editor manusia terpisah dari body editor dan revisions native. Tidak menambah dependency ACF/Meta Box. Field builder UI/repeater belum tersedia.
- Human field edit dapat mempertahankan status publish; adapter agent tetap draft-only. Custom fields tidak ikut outbound provider secara otomatis. Scope inbound tetap per-field/type/object.
- Judul/header FT mengikuti blogname dan blogdescription WordPress sesuai arahan user; identitas proyek FP tidak menimpa judul situs. Footer credit menautkan http://asukayalab.com.
- Konten/gambar demo fiktif berada di examples/content, di luar installer runtime. Seeder local-only, idempotent, tidak menimpa edit manusia dan tidak mengubah homepage.

## Revisi builder — 7 Oktober 2026

- Mengikuti koreksi pengguna, fixed content directory diganti Content Builder: content types, field groups, taxonomy definitions, listing generator dan admin menu ordering. Input nilai dipindahkan ke metabox editor native; editor/action terpisah lama dihapus.
- Definitions menyimpan schema situs; Schema memvalidasi nilai; Registry mendaftarkan CPT/taxonomy; Repository menyimpan nilai. Tidak menggunakan dependency ACF/Meta Box.
- Project satu-satunya bawaan, posisi menu setelah Pages. Definisi/field dapat dinonaktifkan tanpa purging; legacy Publications/Learning disimpan sebagai compatibility path dan bukan default UI/demo.
- Kategori/tag dan jenis konten menjadi filter listing. Slug mengatur URL. Metadata tetap detail nilai, tidak dipakai sebagai pengganti klasifikasi. Shortcode fixed cards pertama, bukan visual query builder.
- Field baru default private. AI describe_schema hanya memberi schema dalam grant, tanpa tools structural/admin mutation. UI AI membedakan MCP dan API; paket ChatGPT/OAuth masih pending eligibility/auth implementation.
- Historis revisi builder awal: native main-content saving dan metadata saving tidak atomic; invalid/stale field ditolak setelah native content tersimpan. Behavior ini digantikan guard pre-write pada bagian Native save guard di bawah; transaksi universal tetap tidak dijanjikan.


- Listing multi-selection uses OR within a single taxonomy. Check all means any assigned term, includes future terms, and excludes unclassified items; empty selection prevents copying. Limits stay within 1–50. Type-dependent, nonce-protected term loading uses 200-term pages.
- Builder drag handles preserve arrow alternatives and separate reordering from saving. Cancel links discard unsaved form state. AI onboarding documents existing HTTP Basic and server-side API setup; it does not claim direct subscription/OAuth/client acceptance.

## Native save guard dan harness — 7 Oktober 2026

- Jenis dengan field Falcon memakai editor native klasik untuk satu form save boundary. Gutenberg legacy metaboxs melakukan request setelah content REST write; adapter gabungan belum tersedia. Jenis tanpa field Falcon tetap memakai editor normalnya.
- Validasi nonce/capability/revisi dan lock schema/object mendahului native write. Metadata tervalidasi disimpan sebelum transisi native publication; handler save_post lama diganti early-save guard, bukan dipertahankan sebagai jalur paralel. Native REST dan insert/update publication memvalidasi contract field tersimpan; scheduler recheck sebelum core publish.
- Tidak menjanjikan transaksi universal: native SQL/hook failure setelah metadata tersimpan perlu rekonsiliasi; privileged wp_publish_post/direct SQL dari plugin lain di luar boundary ini. Rincian di CONTENT-SCHEMA.md.
- Runner integrasi menangkap konfigurasi situs lalu restore dalam finally, termasuk child failure. Snapshot bertanda owner dan tidak ditimpa saat stale; docs/notes/credential/artefak tetap lokal ignored.

## Jalur prerelease lokal/staging — 7 Oktober 2026

- Tag kosong tetap stable terbaru. Operator memilih tag alpha/beta/rc tertentu pada Proyek & Koneksi; runtime menolak prerelease di development/production dan hanya menerima local/staging. Credential GitHub tetap server-side read-only.
- Manifest prerelease status development, dirty false, source_commit SHA dan versi rilis cocok dengan tag. Versi rilis produk terpisah dari versi komponen; paket boleh berbeda versi. Tidak mengubah alpha menjadi stable.
- Apply mengambil ulang metadata dan menuntut paket sama dengan kandidat yang telah diperiksa; metadata/tag/connection berubah memerlukan check/review ulang.
- Plugin self-update menggunakan WP Plugin_Upgrader bulk path dengan satu target agar status aktif dipertahankan; jalur upgrade tunggal yang menonaktifkan plugin diganti. Tidak menambahkan handler update paralel atau aktivasi ulang otomatis.
- Pengujian tetap Docker lokal sesuai pilihan user. GitHub metadata/download fixture bukan live acceptance. Kontrak paket klien di PROJECT-PACKAGES.md; installer proyek dan global design/SEO tetap tahap berikutnya.

## Contoh proyek dan versi komponen — 7 Oktober 2026

- Falcon Reference adalah contoh generik lokal, source/inventory di examples/projects/falcon-reference; tidak mengklaim desain Rizal atau membuat repo klien. Build terpisah menghasilkan ZIP delapan file runtime dan project manifest di dist/projects; tidak dibundel ke FP/FT.
- release/components.json memiliki versi rilis produk dan map versi FP/FT. Build memasukkan token header/API/dashboard, installer manifest serta artifact dari versi yang tepat. Metadata npm/lock disinkronkan dan diperiksa. Tes build terisolasi memakai versi FT berbeda untuk membuktikan tidak terikat versi FP/produk.
- Parent template fallback coming-soon memasang filter robots hanya saat template tersebut dirender. Child front page tetap mengikuti WP site visibility. contentDetails FT menjadi satu boundary penolakan metadata ketika password post belum terbuka, termasuk consumer parent dan child.
- Home memakai Page statis yang dipilih manusia atau site title/tagline saat latest-posts. Listing home memakai shortcode FP yang sudah ada; archive memakai main query publish/password-free dan pagination. Tidak mengubah homepage, membuat konten demo atau mengaktifkan child pada instalasi pengguna otomatis.
- Design tokens CSS per contoh mengatur font judul/body, warna, spacing dan skala heading. System fonts tidak mengirim request font eksternal. Form pengaturan global serta owner metadata SEO lanjutan tetap pekerjaan berikutnya.
- Test fixture memilih child sementara hanya di Docker lalu restore options, konten fixture dan file child lama. Instalasi contoh sesudah pengujian hanya untuk Live Preview, tanpa Activate & Publish. Batas kompatibilitas minimum parent alpha.3 karena helper password dan robots sudah diperbaiki di versi tersebut.

## Desain Global — Alpha.4

- FT inc/design.php menjadi satu owner contract field/font allowlist/range/validasi, pembacaan dan inline CSS. FP Settings hanya melakukan locked revision-checked persistence; Dashboard menyediakan capability fwf_manage_system + nonce, bukan API AI.
- Option fwf_design_{stylesheet} disimpan non-autoload per installation/theme. FT/child kompatibel aktif diperlukan untuk form. Tidak membaca/mengubah theme lain; form lama setelah theme/revision berubah ditolak. Tidak mengaktifkan theme saat save.
- Nilai kosong menghapus override, mengikuti fallback CSS desain proyek. Seluruh payload divalidasi sebelum write; array/injection/unknown fields ditolak. Font lokal empat stack, warna #RRGGBB, angka bounded; tidak menerima arbitrary CSS, URL font atau HTML.
- Child Reference memakai --fwf-* melalui fallback --reference-*; override tidak bergantung urutan child enqueue dan tetap dirender saat FP deactivated. Template baru harus memakai token ini; arbitrary third-party theme/inline style tidak otomatis diubah.
- Pengaturan H1–H6 mengatur ukuran visual responsif; tidak mengganti hierarchy markup atau menyediakan SEO/GEO metadata. Reset hanya opsi desain scope aktif; konten dipertahankan.

## Paket proyek — Alpha.5

- ProjectManager owns project connection/manifest/apply, separate from core UpdateManager allowlist. Shared GitHubClient has bounded credential/manifest selectors; core defaults unchanged, project selects FWF_PROJECT_GITHUB_TOKEN/project-manifest.json. Shared Verifier remote replaces core inline ZIP routine; public core facade forwards for compatibility.
- One configured child theme only. No project plugin or automatic activation. Connection/review on existing screens. Strict clean provenance, release/tag identity, FP/FT ranges, no downgrade, shared update lock and backup/directfs checks. Apply refetches reviewed metadata and uses actual WP Theme_Upgrader; transient restored.
- Only FP changes in this step; product/FP alpha.5, FT remains alpha.4. Reference remains alpha.2; project build adds required release version to manifest without changing runtime ZIP.
- Faults in option persistence/check audit invalidate review; final audit failure reports installed code needs review. Test updater writes/failure/retry and cleanup run in separate PHP requests; WordPress restores/deletes temp backups at shutdown. Snapshot/file owner checks and filesystem stat refresh verify final cleanup.
- Live Github/client/host acceptance remains pending; fixtures do not substitute mandatory live inbound/outbound AI 0.1 gates.

## SEO/GEO dasar — Alpha.6

- FT Seo owns stored mode, revision and public rendering; FP provides capability/nonce/locked revision persistence. Single site-level fwf_seo option, external default. Values retained on disable/deactivation; no AI system tools.
- Native Page excerpt support is exposed by FT; no parallel per-post SEO metadata editor. Preserve native title/robots/sitemap/singular canonical. Supplemental canonical only latest-posts home. Explicit public excerpts only; no shortcode/content rendering or custom fields copied to metadata. WebPage, home WebSite and Open Graph website describe existing pages, not invented author/organization/article facts.
- Known SEO plugins and fwf_seo_external_owner filter suppress Falcon output. Other SEO adapters must use external mode; full third-party compatibility matrix pending. No special GEO file/schema or ranking guarantee. Scope/policy documented in SEO.md.

## Backup lokal — Alpha.7

- Backup Manager owns package/validation/recovery, human Dashboard owns nonce/capability. ZIP has no password; private directory outside WP, mode0700 and archive0600. Same-site HMAC/hash/inventory and code/WP/schema identity; restore local/staging only. No AI tools.
- Preserve accounts, auth/grants, server configuration, operational data and active theme/plugin choices. This is supported site-content recovery, not a full server/account migration. Unknown third-party DB data has no universal secret-redaction guarantee. Details in BACKUP.md.
- Restore is reviewed, revision checked and preceded by safety archive; files journaled/replaced, database transactional, handled failures revert both. Extra files preserved. No universal crash atomicity/quiescence/host recovery claim; unfinished journal blocks subsequent jobs pending reconciliation.
- FP/product alpha.7; FT remains alpha.6. Cloud/schedule/retention/media selection and production DR acceptance remain later authorized phases. User manual deferred to pre-release as requested.

## Ukuran dan stream backup — Alpha.8

- Tidak ada batas tetap byte/file/row dari alpha.7 (64MiB files, 32MiB DB, 5000 files, 50000 rows). File ZIP/hash/journal/restore memakai stream/copy; DB dibaca per100 rows dan disimpan JSONL per tabel. Format manifest schema2, hanya kompatibel instalasi/versi yang sama.
- UI menghitung ukuran file per komponen dan perkiraan encoding database sebelum kompresi; total pilihan berubah saat checkbox berubah dan settings tidak dihitung ganda bila database dipilih. Ukuran ZIP akhir tetap ditampilkan di daftar arsip.
- Kapasitas disk, ukuran metadata/row terbesar, memory_limit, upload PHP dan timeout server tetap berlaku. Disk headroom dinilai dari data pilihan dan resource tersedia, bukan fixed archive cap. Import menampilkan batas upload aktual server. Worker persistent/resumable dan crash reconciliation tetap pekerjaan berikutnya.
- Product/FP alpha.8; FT tetap alpha.6. Tidak ada perubahan situs production, cloud/scheduler atau kelulusan disaster recovery production.

## Antrean backup — Alpha.9

- Backup/Manager owns both the persisted worker and synchronous restore safety backup: one snapshot/ZIP engine, no duplicate archive path. Private job state uses atomic rename/checkpoints; WP-Cron executes one stage/batch. The human create action queues work and returns; authenticated nonce-protected status polling shows progress and completion.
- A single transaction captures DB and copies/hashes selected files to private staging. If capture dies, retry discards only owned partial staging and captures anew. Packing then resumes across PHP processes in batches of up to8 complete files; a corrupt archive is rebuilt from the immutable snapshot after explicit retry. No single-entry/sub-row timeout escape or distributed snapshot claim.
- Backup lease uses OS flock on private storage: process death releases it, without stealing a live writer. Legacy option locks require operator review; other updater locks retain their prior conservative policy. Server filesystem must support flock. Restore crash journals are still manual reconciliation, not automatically replayed by backup workers.
- Worker rechecks original admin capability and exact site/code identity. Human continue/cancel controls require independent nonces. No AI/REST system tools. Cancel removes staging, never site content or completed ZIP. Deactivation unschedules workers and preserves job/archive data; resume manually after activation. Old-code unfinished jobs cannot run; cancel/start anew.
- WP-Cron is visit-driven; reliable unattended jobs require configured server cron. Periodic backup schedules, retention, Drive and selected attachments remain later stages. Product/FP alpha.9; FT alpha.6.

## Kartu arsip dan catatan — Alpha.10

- Daftar arsip menampilkan dua baris: waktu situs WordPress, lalu UUID.zip dan ukuran ZIP aktual. Per-file Download ZIP/Restore/Delete berkelompok; Restore memakai validator/review/checkbox existing, tidak melewati safety snapshot.
- Catatan opsional saat antrean dibuat dan edit per arsip: plain text maksimal1000 Unicode characters/4000bytes, sanitized at write and escaped at render. Manager owns storage in non-autoload fwf_backup_note_UUID options; protected operational namespace means restore does not rewind notes. Notes are local installation metadata, not embedded inside downloadable ZIP/import/cloud.
- Delete is a dedicated capability/nonce boundary: review records selected ID/SHA256 for10minutes, explicit confirmation and fresh hash required, shared backup lease and pending recovery-journal guard. Removes only chosen ZIP, note and terminal job metadata. Active jobs and live restore writers cannot be deleted. No site content/theme/plugin is purged. Cancel review clears its transient. Older-code archives can be deleted without restoring them.
- Product/FPalpha.10, FTalpha.6; same-site/version restore contract remains. No cloud or periodic backup implementation added in this UI step.

## Retensi dan perlindungan — Alpha.11

- Manager owns policy, protection and all deletion. Non-autoload fwf_backup_retention/protected_UUID/result remain operational metadata excluded from rewind. Human policy save uses nonce/capability/revision and shared backup lease; protect/unprotect use nonce/capability and the same lease.
- Retention defaults disabled, limit5 (1–1000). Saving settings or unprotecting never prunes immediately. Only a fully validated newly finalized background ZIP triggers retention, in a separate persisted retaining stage. Ordinary archives count toward quota; protected archives consume disk but are excluded. New archive is always retained. Pending journal/active-job/hash failures stop deletion and are reported; no deletion before new archive validation.
- Protected archive manual Delete is disabled in UI and rejected by Manager even with a reviewed hash/confirmation. Four per-file actions remain visible; lock indicator and inverse button reflect saved state. Protection is an application deletion guard, not encryption or protection against host-level access.
- Synchronous restore safety snapshots bypass pruning so the source cannot disappear during restore. They may exceed quota until a later successful background backup. Retention uses removeLocked under the already-held lease, sharing note/job cleanup with confirmed manual deletion.
- Product/FPalpha.11, FTalpha.6. Periodic scheduling/cloud transport and restore crash reconciliation remain pending; no production acceptance is implied.

## Backup berkala — Alpha.12

- Backup/Scheduler owns calendar policy, revisions, cron events, slot claims and a bounded operational run history. Manager remains the single snapshot/ZIP/retention owner. No AI scheduling/system endpoint; human save uses system capability, nonce, policy revision and a private OS schedule lease.
- One daily/weekly schedule per installation, explicit hour/minute and weekday, selected components. Disabled by default. Timezone is captured from WordPress at save; save again after changing site timezone. Next event is a UTC single event calculated from calendar wall-clock time, not a fixed86400second recurrence. Real host execution still requires visits or configured server cron.
- Generation+due uniquely identifies each slot. State stores the slot claim and pending/unknown record in the same option write before queueing, and a future slot is armed. Bootstrap/save preserves a pending record missing from history before replacing state. Late execution queues one current snapshot, never a flood of missed periods. Active jobs/backup lease contention skip with a recorded reason; other failures are reported and next period remains independent. Unknown gap between durable claim and queue/result write is retained for operator review, never blindly replayed.
- Original admin owner is rechecked by scheduler and Manager workers. Revoked owner permission blocks the schedule until an admin saves again. Deactivation clears periodic and job worker hooks while preserving policy/jobs/archives/history; activation rearms the saved periodic schedule, without automatically resuming old unfinished jobs or switching themes.
- Job terminal checkpoints emit a narrow internal notification to Scheduler; schedule result tracks actual done/failed/cancelled, including retention warning. UI read-only reconciliation can obtain terminal result if notification was lease-blocked. Keep20terminal run records plus all pending/unknown records. Operational fwf_backup_* options remain excluded from restore rewind and ZIP.
- Local backup-worker now dispatches scheduled hooks only with --due-now, retaining its existing job worker path. Private backup and schedule leases share Packages/Lock OS implementation; no database lease stealing. Shared/network filesystems and fatal persistence recovery still require host certification.
- Product/FPalpha.12, FTalpha.6. Selected attachments, crash rescue, cloud and stable0.1 acceptance remain pending.

## Media terpilih — Alpha.13

- MediaSelection exclusively owns selected attachment normalization, native record/meta contract, dependency closure and scoped restoration. Manager remains the archive/queue/transaction/journal owner; Scheduler persists selected IDs and queues the same engine. Native Media Library and authenticated AJAX estimates are human interfaces, with no AI backup/restore tool added.
- Selected mode packages original attachment IDs plus original/derivative/edit-backup files and `_thumbnail_id` cover dependencies. Native metadata allowlist only; plugin custom fields are preserved and excluded. Missing declared dependencies, traversal/symlink, serialized objects/depth, duplicate IDs/meta, outside primary-file ownership or live ID/GUID collision reject the operation.
- Whole-site database and selected media are mutually exclusive to keep restore scope explicit. Existing all-uploads mode keeps its established byte-only semantics without database. Signed manifest schema3 records roots and exact closure inventory; exact FP/WP/site compatibility remains required.
- Selected restore shares existing transaction and file journal, preserves unrelated records/custom metadata, and checks existing author/parent dependencies. Full supported database/uploads safety snapshot and conservative live revision are deliberate recovery costs; do not imply the safety copy has the smaller selected size. No deletion hooks, account reset, theme switch or cross-site ID mapping.
- Product/FPalpha.13, FTalpha.6. Local runtime evidence is recorded separately; cloud/host crash rescue/stable0.1 gates remain pending.

## Restore terputus dan rescue — Alpha.14

- Recovery owns the durable undo state, commit decision, operator review and cleanup, used by Manager for both handled failures and interrupted-operation resolution. Prior journal.json/direct-copy rollback implementation removed. No alternate ZIP/SQL restore engine and no AI system endpoint.
- Journal schema1 independent of archive schema3. Private0700 restore-UUID directory, undo copies/checkpoint/key0600. Private256bit `.recovery-key` initialized under backup lease and excluded from Git/archive; HMAC binds immutable context (ABSPATH, database, options table), roots, entry paths/old hashes/new hashes/modes, original update-lock owner and safety archive. State temp write+flush+fsync+rename precedes file replacement.
- A MariaDB connection named lock supplements shared backup flock; new process obtains it before reading outcome so a still-active old database connection cannot be treated as rolled back. Unique non-autoload fwf_backup_restore_commit_UUID proof is inserted in the existing transaction on the original connection just before COMMIT. No proof after connection termination means rollback only when the journal has no recorded connection ambiguity. Lost/reconnected sessions retain uncertain state; absent proof cannot authorize automatic file rollback.
- Proven commit preserves restored DB/files; proven noncommit restores old file bytes/modes and removes only new restore files. Entire target/undo inventory is checked first; third-party newer file bytes, signature/path/symlink errors and foreign update locks block writes. Human capability/nonce/10minute review hash/confirmation and fresh filesystem/lock/proof state are required. No database statement replay.
- Terminal committed/rolled_back checkpoint precedes proof/owner-lock cleanup. Journal is atomically retired outside pending namespace before private slot cleanup; interrupted cleanup can leave completed-restore-UUID for operator review/cleanup_only through the same engine; never replay content writes or overwrite newer live bytes. Damaged/empty state still requires direct operator inspection. Original pending journals block new backups and retain update owner lock. Legacy journals are not automatically interpreted/replayed.
- rescue.php accepts PHP CLI only, SHORTINIT wp-load skips normal plugins/themes, and uses the same Recovery validator/operator action. --inspect supplies a review hash; --apply requires that exact --review plus --confirm. Database/config/core/db/cache drop-ins must still load; a trusted extracted installer can provide clean rescue/autoload/classes if plugin code itself is broken. Local/staging writable, single-site only.
- Product/FPalpha.14, FTalpha.6. Process-death and bootstrap fixture evidence is local; host power-loss, network/shared filesystem, DBA failover and production RPO/RTO remain separate gates.
