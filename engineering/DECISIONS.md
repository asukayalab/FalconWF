# Keputusan implementasi awal

7 Oktober 2026. Ini keputusan teknis implementer yang dapat direview, bukan klaim semua detail sudah disetujui user atau software sudah siap produksi.

| Area | Pilihan dan alasan |
|---|---|
| Identitas | Plugin `falcon-wf`, theme `falcon-theme`, PHP namespace `FalconWF`, REST `falcon-wf/v1`, text domain sesuai komponen. |
| Versi | Product target Falcon WF 0.1; build pengembangan `0.1.0-alpha.1`. `release/components.json` menjadi input metadata build. Stable belum diterbitkan. |
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
| Git/dokumen | docs dan session-notes lokal saja. engineering memuat kontrak/status/evidence teknis, bukan salinan seluruh referensi atau transcript. Git lokal; belum ada remote/push. |

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
- Native main-content saving dan metadata saving tidak atomic; jika field invalid/stale, nilai field ditolak dan notice ditampilkan, namun native content yang telah disimpan tidak di-rollback. Ini batas yang harus diperbaiki sebelum full editor/publish validation acceptance.


- Listing multi-selection uses OR within a single taxonomy. Check all means any assigned term, includes future terms, and excludes unclassified items; empty selection prevents copying. Limits stay within 1–50. Type-dependent, nonce-protected term loading uses 200-term pages.
- Builder drag handles preserve arrow alternatives and separate reordering from saving. Cancel links discard unsaved form state. AI onboarding documents existing HTTP Basic and server-side API setup; it does not claim direct subscription/OAuth/client acceptance.
