# Target connector ChatGPT

Keputusan target 9 Oktober 2026: client pertama adalah ChatGPT melalui custom MCP connector/plugin. Status: fondasi OAuth pada build lokal produk/FP alpha.17; belum ada acceptance ChatGPT nyata atau pemasangan alpha.17 di hosting.

## Gap yang sudah diverifikasi

MCP Falcon existing ada pada REST `falcon-wf/v1/mcp`, dengan HTTP Basic Application Password + UUID grant, actor `fwf_agent`, scope dan revision. URL aktual harus mengikuti `rest_url`, bukan diasumsikan `/wp-json` selalu tersedia. Basic bukan pilihan autentikasi custom MCP yang didokumentasikan ChatGPT. Jangan menaruh Application Password pada URL, query, source, log atau chat; jangan membuka tools konten anonim sebagai jalan pintas.

Sumber resmi dibaca 9 Oktober 2026:
- https://developers.openai.com/api/docs/guides/custom-mcp-server — remote SSE/streaming HTTP, auth OAuth/noauth/mixed.
- https://developers.openai.com/plugins/build/auth — OAuth2.1, discovery protected resource/authorization server, PKCE, resource binding, identifikasi client, revocation dan metadata per-tool.

## Contract implementasi lokal

1. Endpoint MCP yang sama memakai adapter auth Bearer/OAuth menuju actor + grant existing. Policy/AgentTools/Repository tetap satu pemilik izin dan write. Tidak membuat endpoint/tool konten kedua.
2. WordPress AS internal menyediakan metadata discovery, registrasi client statis dengan redirect allowlist, authorization code sekali pakai/pendek, PKCE S256, resource binding, token storage hashed dan revocation. Jangan menganggap parameter client dari browser dapat dipercaya atau menerima redirect arbitrer.
3. Approval administrator mengikat koneksi ke actor Falcon Agent dan scope konkret. Token hanya mewakili grant tersebut; perubahan scope, actor/password/grant dicabut dan expiry harus menolak token. Jangan meminjam hak administrator ke AI.
4. Native admin boundary tetap capability + nonce. Tool boundary tetap action/type/object/field + expected_revision/idempotency. Tidak ada publish/delete/schema/system/update/theme-switch tool.
5. Auth discovery/initialize/tools metadata harus sesuai MCP/client; uji denial sebelum menjalankan tool, error OAuth aman dan tidak membocorkan credential. Per-tool annotations/securitySchemes mencerminkan read versus draft write.
6. Pengujian lokal mencakup: PKCE salah, code replay, redirect/resource/client mismatch, expiry/revoke, scope asing, permission owner dicabut, revision conflict, token tidak dapat membuka REST native/admin/XML-RPC. Mock OAuth bukan acceptance ChatGPT.
7. Setelah installer baru direview dan dipasang ke HTTPS staging, user menautkan ChatGPT melalui consent nyata. Uji discovery, read scoped, create/edit draft, denial dan revoke dari ChatGPT. Langkah ini memerlukan pilihan connector tersedia pada akun pengguna; dokumentasi publik tidak membuktikan akses akun.

Outbound WordPress → OpenAI API tetap gate terpisah: credential server-side, model/billing aktual, request live, proposal dan human apply. Langganan ChatGPT tidak otomatis menjadi API key.

## Setup public client dan umur credential

OAuth.php owns discovery, registrasi client manusia, consent, code/token/revoke dan cleanup. Administrator mendaftarkan nama serta callback HTTPS persis dari layar setup ChatGPT pada Falcon WF → AI. Public client memakai token endpoint auth `none`, tanpa client secret; DCR/CIMD belum tersedia. Jangan mengarang callback atau menganggap account ChatGPT pasti menyediakan custom connector.

Consent memilih actor dengan grant existing dan menampilkan scope. Nonce terikat request dan sesi login; callback, client, resource, PKCE S256 dan state diperiksa sebelum redirect. Code berlaku5menit sekali pakai, access maksimal1jam, refresh maksimal30hari dibatasi expiry grant. Storage non-autoload menyimpan hash credential; respons token no-store. Refresh berotasi dan menolak replay; lease per credential dan keluarga sesi mencegah race refresh/revoke.

Setiap request memeriksa ulang actor/role, UUID Application Password, revision/expiry grant, client dan capability owner/consent owner. Scope disimpan ulang atau akses dicabut membatalkan token lama. Draft baru memperluas ID grant tanpa mengganti revision persetujuan. Backup/restore mengecualikan namespace operasional fwf_oauth_. Basic client tetap tersedia melalui boundary yang sama.

Standard discovery tersedia melalui path well-known issuer/resource dan REST metadata. MCP memakai POST; GET memberikan405. Host routing/reverse proxy HTTPS serta alur akun ChatGPT harus diterima secara nyata sesudah pemasangan manusia. Tidak ada tool baru publish/delete/system.
