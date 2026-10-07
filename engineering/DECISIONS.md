# Keputusan implementasi awal

7 Oktober 2026. Ini keputusan teknis implementer yang dapat direview, bukan klaim semua detail sudah disetujui user atau software sudah siap produksi.

| Area | Pilihan dan alasan |
|---|---|
| Identitas | Plugin `falcon-wf`, theme `falcon-theme`, PHP namespace `FalconWF`, REST `falcon-wf/v1`, text domain sesuai komponen. |
| Versi | Product target Falcon WF 0.1; build awal `0.1.0-alpha.1`, kini produk/FP/FT `0.1.0-alpha.6`. `release/components.json` menjadi input metadata build. Stable belum diterbitkan. |
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
