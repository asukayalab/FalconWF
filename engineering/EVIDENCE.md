# Bukti verifikasi build lokal awal

Tanggal 7 Oktober 2026. Source snapshot: commit awal implementasi yang memuat file ini. Referensi commit/digest build aktual tersedia pada `dist/release-manifest.json`; artefak tidak masuk Git.

Environment: Docker Linux dari laptop Mac, WordPress 7.1.2, PHP 8.3.35, MariaDB 10.11, Node 24.15.0. Compose mencatat image digests, port HTTP hanya loopback. Tidak ada website production yang diuji atau diubah.

| Perintah/pemeriksaan | Hasil aktual |
|---|---|
| `npm run verify` | Static inventory/namespace 36 source paths lulus; 2 build tests lulus. ZIP paths/allowlist/version/hash/generated CSS dan identical FT bundle diverifikasi; repeated ZIP build byte-identical pada environment ini. |
| PHP lint ZIP | 35 PHP files lulus pada PHP 8.3.35. |
| `tests/integration.php` | 43 assertion lulus: installed ZIP/bootstrap, migration retry/preserve, registry missing/cyclic dependencies, theme installation/retry/version safety, role denial, native revisions/schema/stale edit, draft/public/password visibility, lock ownership, checksum refusal, idempotency dan nonpurge. |
| `tests/provider.php` | Mock-only outbound lulus: selected field/context minimization, denial tanpa network, proposal tidak auto-apply, human apply, replay/stale rejection, disconnect. Bukan panggilan OpenAI nyata. |
| `tests/update.php` | Mock GitHub transport; actual WP Theme_Upgrader lulus. Stable metadata/runtime/type/backup, corrupt ZIP/foreign redirect refusal, active theme retained dan no downgrade. Original FT fixture dipulihkan sesudah test. |
| `tests/immutable.php` | Immutable/file-mod-disabled menolak code write. |
| `tests/mcp.test.mjs` | 16 cek actual HTTP lokal lulus: initialize/discovery, scoped published read, object/field denial, create/edit draft, revision conflict, idempotency, forbidden tool, XML-RPC/native REST bypass denial, Origin, unauthenticated, scope/password revoke. Bukan sertifikasi koneksi ChatGPT/OAuth/hosting eksternal. |
| `tests/admin.test.mjs` | 18 cek actual HTTP lokal lulus: disposable admin/editor login, 9 layar, nonce, identity submit, direct unauthorized UI/action denial dan credential tidak muncul di HTML. Bukan visual QA seluruh dashboard. |
| `tests/frontend.test.mjs` | 8 cek actual HTTP lokal lulus: FT default, noindex/skip link, CSS available, maintenance 503/Retry-After/login, FP actual deactivate/reactivate fallback. |
| Browser preview | Default FT dilihat pada 880px desktop dan 390px mobile. Teks/header/footer terbaca dan tidak tampak overflow pada dua ukuran ini. Viewport dikembalikan. Keyboard/Safari/Android/browser matrix belum disertifikasi. |
| Git ignore | docs, session-notes, local/.env, build/dist dikecualikan. Tidak ada credential, data WordPress atau bundle generated yang distage. |

Integration tests memakai fixture yang dibuat untuk tes dan dibersihkan. WordPress lokal dengan FP/FT serta modul dasar tetap tersedia untuk review.

## Akar masalah yang diperbaiki selama verifikasi

- Official CLI container memerlukan `wp` sebagai executable eksplisit; wrapper awal diperbaiki di `scripts/local.mjs`, tanpa handler tandingan.
- wp-config Docker mengambil config tambahan dari environment masing-masing container; CLI kini membawa environment local/direct yang sama, sehingga tests tidak salah dianggap production.
- WordPress lokal memakai permalink default. Client MCP kini memakai URL dari `rest_url()`, bukan hardcode `/wp-json` yang menghasilkan halaman HTML pada konfigurasi itu.
- Agent native REST dan application-password XML-RPC diblokir agar policy MCP tidak dapat dilewati melalui API WordPress lain.
- Initial migration version hanya maju setelah audit table benar-benar tersedia; incompatible schema menampilkan error aman tanpa mematikan frontend WordPress.

## Batas kelulusan

Q06/Q07/Q08/Q09 dan keseluruhan matriks QA belum lengkap. Real provider access, staging HTTPS/client target, private GitHub release, plugin self-update, full failure/recovery/restore drills, licence dan pilot assets masih diperlukan. Semua mock ditandai mock. Tidak ada push, private release, production deploy atau klaim bahwa seluruh Falcon WF 0.1 selesai.

## Alpha.2 lokal — 7 Oktober 2026

Versi paket `0.1.0-alpha.2`; environment tetap WP 7.1.2 / PHP 8.3.35 / MariaDB 10.11. Source provenance/dirty ada pada manifest artefak aktual. Tidak ada provider request live, remote GitHub, atau deployment production.

- `npm run verify`: inventory 37 path, dua build tests dan ZIP/hash/FT nested bundle/repeat bytes lulus.
- Lint paket final: 36 file PHP lulus pada suite utama.
- Suite utama: 43 core assertions; 24 typed-content/preflight assertions; 21 HTTP MCP checks; 24 HTTP admin checks; 10 HTTP frontend checks lulus. Total **122**; mock provider, mock GitHub transport dengan actual theme upgrader, dan immutable suite juga lulus secara terpisah.
- `tests/content.php`: typed roundtrip, enum/date/URL/media/relation refusal, cross-type fields, metadata revision hash, native meta revision restore, stale/concurrent refusal, validation-before-write, human published metadata edit mempertahankan status, persistence failure tidak sukses, native REST meta hidden, credential filesystem guidance dan hash-valid traversal ZIP refusal.
- `tests/admin.test.mjs`: editor fields nyata melalui HTTP, nonce dan stale form refusal, saved typed values, dan hanya satu render Ringkasan. `tests/mcp.test.mjs`: custom field create/read/edit lewat HTTP mengikuti scope; field di luar grant dan string untuk integer ditolak.
- `tests/frontend.test.mjs` diulang setelah penambahan tabindex pada main dan pemasangan FT final: 10 checks lulus. Header diuji menggunakan Judul Situs/Tagline fixture yang berbeda dari identitas Falcon; opsi fixture dikembalikan. Footer credit/link Asukayalab sesuai request.
- `node tests/demo.test.mjs`: **8** cek tambahan lulus: seeding ulang mempertahankan tiga objek, tiga halaman publik/structured fields, tiga cover request berhasil, dan relasi publikasi/materi → proyek. Demo hanya di local, tidak mengubah homepage. Dua ilustrasi AI disimpan di examples/content/assets, lalu diimpor ke Media Library dengan alt text.

Temuan dan perbaikan selama QA:

1. Callback menu utama/submenu Ringkasan berbeda pada slug sama menyebabkan render ganda. Kini menggunakan callback yang sama; diuji melalui HTTP dan browser.
2. Skip link awal hanya memindahkan scroll. Main FT kini tabindex=-1; browser membuktikan activeElement adalah main setelah Enter.
3. WP-CLI memaksa filesystem direct pada filter prioritas lebih tinggi. Fixture simulasi FTP dipindah ke prioritas terakhir; bukan melemahkan preflight produk.
4. Definisi WP_DEBUG ganda pada config tambahan Docker diganti WORDPRESS_DEBUG environment, memakai definisi resmi image.
5. GET frontend sesudah jeda WP-CLI sempat memakai socket keep-alive yang sudah ditutup Apache. Fixture GET kini meminta Connection: close; suite dan focused frontend rerun lulus. Tidak menambahkan retry mutation.

WordPress updater fixture sempat memunculkan warning bahwa update-check WordPress.org tidak dapat membuat koneksi secure; pengujian private updater tetap mock-only dan lulus. Ini tidak menjadi bukti koneksi eksternal/live berhasil.

Matrix host/Safari/Android, full keyboard flows, credential wizard interaktif, real disk exhaustion, real provider/client HTTPS/GitHub, plugin self-update dan backup/restore penuh tetap belum lulus. Field builder UI/repeater belum dibuat.

Review browser Alpha.2: detail proyek dilihat pada lebar aktual 740px lalu 540px; editor field admin pada 540px. Tidak ditemukan horizontal overflow pada DOM yang diperiksa. Cover, label/input, data proyek dan relasi terlihat. Enter pada skip link terbukti memfokuskan main setelah perbaikan. Percobaan override 390px pada browser ini tetap melaporkan lebar aktual 540px; **tidak diklaim sebagai kelulusan 390px**. Override dibersihkan. Screenshot lokal ada di `local/artifacts/alpha2-fields.jpg` dan `alpha2-demo-mobile.jpg` (nama mobile, ukuran aktual 540px). Seluruh keyboard flow dan device/browser matrix tetap belum diuji.

## Alpha.2 builder revision — 7 Oktober 2026

- `npm run verify`: 41 declared runtime paths; 2 deterministic packaging/hash/inventory tests passed. `git diff --check` passed.
- Installed FP ZIP and refreshed existing FT files in isolated localhost:8091 runtime; theme selection retained.
- Runtime integration: 43 core assertions, 24 typed content/preflight assertions. Provider/updater fixtures passed with mocked transport; updater emitted WordPress.org network warnings. This is not live provider/private-release acceptance.
- New `tests/builder.php`: 21 assertions: custom hierarchical type, location on Posts/Pages/type, required/typed validation, native save_post, stale metadata/definition rejection, immutable field type contract, taxonomy/listing safety, dynamic MCP inventory, public REST and FT visibility, nonpurging disable.
- MCP HTTP: 23 checks including scoped describe_schema and cross-type denial; rerun after removing inactive legacy fields from discovery. No direct ChatGPT/OAuth acceptance.
- Admin HTTP: 34 checks; native metadata save/stale refusal, create type and field group through actual admin boundary, nonce/cap denial, real menu-order save/reset. First expanded run failed because fixture title was changed and guarded cleanup rejected it; test identity fixed, abandoned fixtures removed, target rerun passed. Fixture snapshot/restore preserves builder settings during HTTP tests.
- Frontend HTTP: 10 checks passed after FT file refresh, including real FP deactivation/reactivation fallback.
- Demo seed/public listing: 5 checks passed; Project and Karya Bangunan preserve IDs on repeated seed, generated image serves, taxonomy shortcode resolves. Seeder leaves only Project active.
- Combined final targeted checks: 160 runtime/HTTP/demo assertions/checks plus 2 packaging tests. Results were collected across core suite and targeted reruns after relevant fixes, not represented as one uninterrupted test command.
- Browser: verified Project after Pages, field rows/add/type/duplicate/remove, automatic key and preservation of manually entered key. Native form input/save tested by HTTP; structural definition mutation tested by PHP and HTTP.
- Actual builder viewport 390×844, document width 390: no horizontal overflow. Actual desktop width 1280, document width 1280: grid layout reviewed. Temporary viewport override reset.
- Browser evidence (ignored local artifacts): builder-mobile-390.png; builder-fields-desktop.png; karya-bangunan-listing.png. Browser listing shows fictional Project card for category Bangunan. Screenshots do not constitute complete Android/Safari/accessibility acceptance.
- Docs/session notes/artifacts checked ignored; no external deployment, GitHub remote, provider request or credentials in release artifacts.
- Remaining limits: no repeater/conditional/rich-text/decimal/media search, no atomic native content+metadata rollback/publish gating, no visual block query editor or metadata filter UI, no AI structural mutation tools, no ChatGPT subscription OAuth. See CONTENT-SCHEMA.md.


## Content Builder UI/UX verification — 2026-10-07

- Installed rebuilt Alpha.2 into local WordPress. `npm run verify`: 41 source paths; 2 packaging/determinism tests pass.
- `npm run test:integration`: PHP lint 39; runtime 43, content 24, builder 24, MCP HTTP 23, admin HTTP 38, frontend HTTP 10 assertions pass (162 total). Provider/updater/immutable fixture checks also pass. WordPress.org TLS warnings remain in updater fixture; mock GitHub upgrade assertions pass. No live API request or target AI-client acceptance.
- New runtime assertions: multiple-term OR, taxonomy wildcard, configured limit; AJAX term retrieval, invalid nonce, insufficient role and unrelated type/taxonomy rejection.
- Browser: field drag moved cover after year; Batal Perubahan restored persisted field/location state. Menu drag moved Posts after Media without saving. Open-all/close-all and location check-all work. Listing Bangunan with limit 6 produced exact shortcode; Check all produced wildcard; Uncheck all disabled copy. Found WP button styles overriding hidden attribute, corrected with scoped hidden rule and verified pagination button disappears.
- Pointer capture re-established after row movement. Final browser drag/menu and listing checks pass. 390px layout reports document width 390 (no horizontal overflow); native touch and Safari not tested.
- Local ignored visual artifacts: `local/artifacts/builder-listing-uiux.png`, `local/artifacts/builder-uiux-mobile.png`. Practical AI guide follows implemented auth/tools/provider constraints; no real credentials configured.

## Native save/publication guard — 7 Oktober 2026, clone FalconWF

Source base: main/66a78a1 with local uncommitted changes (manifest dirty=true; no source push/release). Product remains development 0.1.0-alpha.2. Docker mount now uses clone dist/tests/examples, retaining falcon-wf-local volumes; WP7.1.2/PHP8.3.35, Node24.15.0.

- npm run verify passed: 41 declared source paths; 2 packaging/determinism tests, exact ZIP allowlist/checksums/nested FT bundle. Source edits are in packages; generated output was rebuilt.
- Final npm run test:integration exit0: PHP package lint39; core43 +content24 +builder24 +native-save35 +MCP HTTP23 +admin HTTP43 +frontend HTTP10 = **202** runtime/HTTP assertions/checks. A separate actual child-failure regression also passed (1): nonzero failure retained and original site settings restored. Provider/updater/immutable fixtures passed separately.
- Native save tests cover Posts/Pages/Project, required fields, native content and metadata/schema conflicts, malformed/unknown fields, unauthorized actor, owned locks, metadata persistence failure before publish, REST publish/private/future/create, forbidden protected REST metadata, scheduler revalidation and unchanged editor choice without fields. HTTP tests prove actual classic POST rejection (400/403/409) preserves original content revision/status and valid publish succeeds.
- The initial new native test counter printed0 due WP-CLI eval scope; global binding corrected, final run reports35. No failed assertion was represented as pass. Later source review found protected metadata supplied to native REST could incorrectly be treated as persisted; guard now rejects it before the core write, with two final regressions.
- Shared runner snapshots exact settings bytes/autoload flags, owner-checks recovery and restores in finally even after child failure. Frontend fixture now preserves original maintenance/plugin state, and does not restore someone else's stale snapshot on capture failure. Test artifacts/settings/credentials are not packaged.
- Before/after final suite hashes for posts/postmeta/terms/term_taxonomy/term_relationships, uploads and selected native/Falcon settings were identical. Existing theme remained falcon-theme; no user content reset or volume removal. Audit/test operational records are separate from this content/config invariant.
- Runtime audit: **47** installed plugin/theme/generated files match build staging byte hashes. Additional syntax checks: all10 JS files (scripts/tests/builder asset), all3 Python sources parsed; all18 PHP test/fixture sources linted in the container. git diff --check passed.
- Browser: Project list retained one demo Project; native classic editor shows existing title/body, cover/location/year/stage and new validation explanation in one form. No content Update/publish was clicked in this browser review. Opening WordPress edit screen can refresh its normal edit-lock metadata; content/status were unchanged.
- WordPress.org secure-connection warnings remain on the mock updater fixture, as before. They are upstream/environment warnings, not live private release acceptance. Provider remains mock-only; target AI client/HTTPS acceptance remains pending.

Scope and limits: validation refusal now precedes native writes for supported form boundaries; metadata persistence is staged before native publication. This is **not universal transaction/rollback** for native SQL or third-party hook failures. Classic editor is selected for active Falcon-field types until a combined Gutenberg adapter exists. Privileged wp_publish_post/direct SQL from third-party code bypasses insert/update validation. See CONTENT-SCHEMA.md. Browser/device/host matrix and independent security review remain pending; no stable-release claim.

## Prerelease dan self-update lokal — 7 Oktober 2026, 18:56 WIB

- `npm run verify`: inventory 41 path, dua tes packaging/repeat build lulus. Manifest kini memuat versi rilis produk, terpisah dari versi paket.
- `npm run test:integration`: exit 0. PHP lint 39 runtime files; core 43, typed content 24, builder 24, native guard 35, MCP 23, admin HTTP 46, frontend HTTP 10 (205 assertions bernomor), ditambah fixture provider/updater/immutable, release policy unit check empat environment dan regresi pemulihan child failure.
- `tests/update.php` memanggil upgrader WordPress aktual untuk theme stable/prerelease dan FP self-update. Metadata/download GitHub dimock. Dibuktikan: tag/version/status/provenance, versi komponen independen, duplicate asset, hash/runtime malformed, stable/prerelease mismatch, kandidat berubah sebelum apply, redirect asing/corrupt refusal, no downgrade, kegagalan pre-install menjaga file/status aktif, retry berhasil, self-update menjaga active_plugins/theme. Fixture memulihkan byte plugin/theme setelah pengujian.
- Jalur plugin upgrade tunggal ternyata menonaktifkan FP; diganti bulk path satu target, tanpa menambahkan auto-reactivation. Ini koreksi runtime, bukan mock success.
- Admin HTTP membuktikan field tag opsional, simpan dengan nonce dan invalid tag mempertahankan koneksi. Suite HTTP memakai Connection close agar POST tidak bergantung pada socket keepalive yang kedaluwarsa saat CLI fixture bekerja; tidak menambahkan retry mutation.
- Snapshot harness memulihkan dan memverifikasi konfigurasi asli setelah suite. Log lokal ignored: local/artifacts/prerelease-integration.log. Warning WordPress.org berasal dari HTTP fixture yang menolak jaringan eksternal.
- Pengujian dibatasi Docker lokal sesuai pilihan user; belum private GitHub release/download nyata, host staging, restore rehearsal database/uploads, client package installer atau live AI acceptance. Gate Q08/Q09 tetap terbuka. Panduan operator guides/UPDATES.md dan kontrak proyek engineering/PROJECT-PACKAGES.md tersedia.

## Alpha.3 — contoh paket proyek lokal dan template — 7 Oktober 2026, 19:35 WIB

- Start: commit user c3d3e86 (Guard native content saves and improve prerelease self-updates) terverifikasi di HEAD; working tree bersih. Tracking lokal main ahead 1 terhadap origin/main, bukan verifikasi jaringan GitHub. Tidak ada docs/session-notes/env/build/dist tracked.
- verify: inventory core41, **4 build tests** lulus, termasuk build terisolasi dengan versi FT berbeda dari FP/produk. Core dan project ZIP repeat-build byte-identical; project delapan runtime paths/hash/parent/compatibility serta placeholder version replacement diperiksa. Source export mengambil versi metadata, bukan nama alpha.2 hardcoded.
- integration final exit0: PHP lint **45** (39 core + 6 child), core43/content24/builder24/native35/MCP23/admin46/frontend10/project25 = **230 assertions bernomor**, ditambah fixture provider/updater/immutable, policy unit empat environment dan child-failure recovery. **47 FP/FT installed file hashes** cocok ZIP build sebelum dan sesudah suite; snapshot konfigurasi asli restored/verified. Log ignored: local/artifacts/reference-project-integration.log.
- Project fixture memanggil Theme_Upgrader install aktual tanpa mengganti theme; kemudian memilih child sementara dalam Docker untuk pengujian, lalu memulihkan options/file child sebelumnya dan menghapus ID konten fixture miliknya. Home/Page/Project archive/detail benar memakai child hierarchy; archive/home menolak draft/private/password; guest draft/private404; protected detail memakai password form tanpa body/metadata. Public field tampil melalui helper FT, field private tidak tampil. Latest-posts/static Page Reading modes dan Page tanpa FP diuji.
- Coming-soon parent tetap noindex pada situs public; child front page bisa diindeks saat blog_public1 dan tetap noindex saat blog_public0. Filter global front page diganti filter milik template coming-soon. Metadata password guard ada pada satu helper FT.
- Browser Live Preview (tanpa Activate & Publish): homepage pada viewport desktop1280x900; mobile iframe320px, homepage/detail contentWidth320=viewport320, satu H1. Project detail menampilkan field publik dan content asli demo. Kartu single-item dibatasi grid auto-fill agar thumbnail tidak membentang memenuhi desktop. Bukti JPG lokal ignored: reference-desktop.jpg, reference-mobile.jpg, reference-mobile-project.jpg. Viewport reset, kontrol kembali Desktop; tab preview dipertahankan.
- Tambahan syntax: **13 JS** node --check, **4 Python** AST parse; git diff --check lulus. Release tetap development alpha.3, dirty true; tidak publish atau upload hosting. Versi contoh alpha.1 independen dari core.
- Status akhir WP: FP alpha.3 aktif; FT alpha.3, active theme falcon-theme; Falcon Reference alpha.1 installed/inactive; fwf_reference_test tidak tertinggal. Perubahan baru belum commit/push. Tidak ada GitHub repo klien dibuat.
- Ini reference lokal, bukan client/Rizal acceptance. Form global design settings, installer/updater repo klien, SEO metadata/schema lanjutan, live AI dua arah, live private release, backup/restore rehearsal dan matrix browser/host masih pending.

## Alpha.4 — Desain Global lokal

- FP/FT 0.1.0-alpha.4 dan contoh child Reference 0.1.0-alpha.2. Development/dirty manifest; belum release production dan belum push.
- npm run verify: 42 runtime source paths, empat build tests, independent component versions, paket child terpisah dan repeat-byte deterministic lulus.
- npm run test:integration exit 0 pada build final: lint 46 PHP files; core 43 + content 24 + builder 24 + native guard 35 + design 14 + MCP 23 + admin 56 + frontend 10 + project 31 = 260 numbered checks. Provider/update fixtures, immutable/policy dan child-failure restoration tetap lulus; bukan live provider/GitHub/client acceptance.
- Design contract menguji CSS injection/URL font/array/unknown/range invalid, all-or-nothing validation, stale form, theme mismatch, lock ownership, reset dan corrupt-option fallback. HTTP admin menguji sepuluh layar, actual save/frontend tokens, nonce/capability/editor denial, conflict, invalid input dan reset control.
- Child HTTP menguji token pada home/Page/archive/detail, retention render setelah FP deactivation serta scope child tidak bocor ke parent. Snapshot fixture/global restore verified. Installed FP/FT 48 file byte hashes identik ZIP sebelum/sesudah suite.
- Browser computed styles pada child fixture desktop 1280: body system UI, ink rgb(19,87,36), H1 68px, padding 32px sesuai input; document scroll width 1280. Customizer mobile iframe 320 pada home/detail: H1 28px, padding 24px, document width dan scroll width 320. Ini dua ukuran lokal, bukan matrix seluruh browser/Android/Safari. Temporary viewport reset dan fixture theme/settings/content/files restored.
- Screenshot lokal ignored: local/artifacts/design-mobile-project.jpg dan design-global-admin.jpg. Log final ignored: local/artifacts/design-integration.log. Theme aktif akhir parent Falcon Theme; contoh child diperbarui terpisah untuk preview tanpa aktivasi.
- Tidak mengubah ar.obie.my.id, menerbitkan release, mengirim provider request atau menambahkan repo klien. Global design bukan SEO metadata; installer repo proyek serta gate live AI/update/recovery tetap pending.

## Alpha.5 — Paket desain proyek

- Product/FP 0.1.0-alpha.5, FT tetap 0.1.0-alpha.4, Reference 0.1.0-alpha.2. Belum commit/push/release oleh agent; build dirty development, tidak untuk distribusi production.
- npm run verify exit0: 43 mapped runtime source paths, empat build tests, independent component versions, shared verifier/packaging/repeated bytes dan child ZIP terpisah lulus.
- npm run test:integration final exit0: lint 47 PHP; core43/content24/builder24/native35/design14/project-update37 + failure1 + retry1/MCP23/admin68/frontend10/project31 = 311 numbered checks. Provider/core-update/policy/immutable dan harness child-failure tetap lulus. Installed FP/FT 49 file byte hashes sama ZIP sebelum/sesudah; original settings restored verified.
- Project checks memakai mock GitHub; actual WP fresh install, update child aktif, backup confirmation, parent/header/path/hash/identity/range/type/provenance denial, downgrade/same-version refusal, reviewed manifest identity, DB-store denial/candidate invalidation, scoped project credential, signed redirect tanpa Authorization, role denial dan pinned prerelease discovery/apply.
- WP restores/deletes temporary update backup at shutdown. Test awal yang menjalankan beberapa writes/failure/retry sekaligus dalam satu PHP request memberi hasil tidak representatif dan menyisakan fixture yang direstore saat shutdown. Replaced with owner snapshot supervisor: run → fail → retry → cleanup, masing-masing PHP request; retry memeriksa versi recovered, cleanup memeriksa versi final setelah shutdown, active theme, file owner dan ketiadaan folder sebelum snapshot dilepas. Bukan menonaktifkan callback recovery WP.
- Pada DISALLOW_FILE_MODS, WP core dapat menolak install/update capability sebelum boundary filesystem FWF. Immutable test menerima permission/filesystem denial dan tidak menjalankan transport/write.
- UI browser: Proyek & Koneksi menampilkan panel repo/project ID/theme slug/tag/token guidance terpisah dari core; Pembaruan menyediakan check/review/apply/preview. Screenshot ignored local/artifacts/project-connections.jpg; final log local/artifacts/project-package-integration.log.
- WP.org warnings pada transport fixtures berasal dari network yang sengaja ditolak; tidak dianggap live host/connectivity acceptance. Tidak ada provider request, private release live, repo klien baru atau perubahan ar.obie.my.id. Plugin proyek/multi-theme dan live distribution/recovery host matrix masih pending.

## Alpha.6 — SEO/GEO dasar lokal

- Product/FP/FT 0.1.0-alpha.6; Reference tetap alpha.2. Metadata external default, opt-in manusia; tidak membuat rilis stable/push/deployment. Manual pengguna lengkap masuk Q11, disiapkan menjelang rilis 0.1.
- npm run verify exit0: 44 mapped source paths dan empat build tests (inventory/hash/bundle/determinism/independent component versions). Final npm run test:integration exit0: core43/content24/builder24/native35/design14/SEO26/project-update37+failure1+retry1/MCP23/admin74/frontend10/project44 = **356 numbered checks**. Provider/core-update/policy/immutable/recovery fixtures juga lulus, bukan live acceptance.
- PHP lint48 dan 50 installed FP/FT byte hashes cocok ZIP sebelum/sesudah suite. Original site settings restored/verified. Final ignored log: local/artifacts/seo-integration.log.
- SEO tests: native Page excerpt support, default-off/opt-in/reset, stale/unknown/capability/lock refusal, public explicit excerpt, body/custom-field exclusion, JSON parse/script escaping, draft/private/password/feed/preview/search/404/pagination/site-private suppression, corrupt config fail-closed, external-owner filter/known plugin constant, native owners remain registered. Known-plugin signal fixture tidak menggantikan matrix plugin SEO terpasang.
- Actual HTTP: admin dedicated nonce/role denial, mode on/off; child home/Page/Project masing-masing satu title/native canonical, satu OG dan parseable WebPage; dynamic home supplemental canonical/WebSite; password no Falcon metadata, archive delegated, saved SEO survives actual FP deactivation. Parent coming-soon remains noindex. Tidak ada private metadata di head.
- First HTTP run found selector whitespace mismatch (WP selected helper adds whitespace); assertion fixed. After exposing Page excerpt, runtime hash guard caught older bundled FT in installed FP; FP installer resynchronized as well as FT before final full pass. No failed runs counted as success; settings recovery verified after failures.
- Browser readonly review: Identitas & Kontak contains SEO/GEO mode/default/excerpt/privacy guidance and save button. Screenshot local/artifacts/seo-admin.jpg; no live configuration or browser save. No third-party plugin compatibility, Google indexing/rich-result/AI citation acceptance or host matrix claim. Live provider/client/release/backup gates remain open.
