# Menghubungkan AI ke Falcon WF Alpha.2

Panduan ini mengikuti kemampuan build lokal saat ini. Ada dua jalur: WordPress meminta proposal teks ke OpenAI API, atau agent MCP mengakses draft WordPress. Koneksi provider live dan penerimaan oleh aplikasi ChatGPT/Codex belum diuji; pengujian MCP memakai client HTTP otomatis, pengujian provider memakai mock. Langganan ChatGPT belum bisa dipakai untuk login langsung ke Falcon WF.

## A. OpenAI API dari dashboard

1. Siapkan API key dan model yang tersedia untuk akun API Anda. Build ini memerlukan API key; login/akun langganan ChatGPT bukan pengganti key di koneksi ini.
2. Administrator server memasang secret sebagai environment `FWF_OPENAI_API_KEY` pada **proses PHP WordPress**, lalu menambahkan kode berikut ke `wp-config.php`, sebelum WordPress dimuat:

```php
define('FWF_OPENAI_API_KEY', getenv('FWF_OPENAI_API_KEY') ?: '');
```

Pada Docker lokal, menambah variabel ke terminal atau `local/.env` saja belum meneruskannya ke container. Tambahkan environment berikut ke service `wordpress` di `local/compose.yml`, lalu recreate service melalui `npm run local:up`:

```yaml
environment:
  FWF_OPENAI_API_KEY: ${FWF_OPENAI_API_KEY:-}
```

Gabungkan dengan mapping environment yang sudah ada; jangan membuat key `environment` kedua. Simpan nilai secret hanya di `local/.env` yang diabaikan Git; jangan menulis nilainya pada compose, kode, screenshot, atau session notes. Untuk container lokal, pasang definisi PHP melalui `WORDPRESS_CONFIG_EXTRA` yang sudah ada, sebelum bootstrap WordPress. Recreate container diperlukan sesudah environment berubah. Build saat ini belum memasang key apa pun.

3. Buka **Falcon WF → AI**, aktifkan izin outbound, isi model, pilih field konteks `title`, `body`, atau `summary`, lalu simpan.
4. Siapkan konten berstatus **draft**, catat ID-nya. Isi ID draft, pilih satu field dan tulis instruksi editorial. Klik **Kirim konteks terpilih dan buat proposal**.
5. Periksa proposal. Klik **Setujui dan terapkan ke draft** bila sesuai. Penerbitan tetap dilakukan manusia melalui editor WordPress.
6. **Putuskan outbound** menghentikan fitur ini; key server tidak ikut dihapus. Cabut key di penyedia/server jika ingin benar-benar membuang credential.

Yang dikirim: instruksi editorial, nama field, dan teks satu field terpilih. Batas aplikasi: 20 percobaan request/hari per instalasi, reset UTC; maksimum output 1.200 token/request; timeout 30 detik; proposal berlaku 24 jam dan hanya bisa diterapkan oleh pembuatnya. Kegagalan request tetap menggunakan anggaran aplikasi. Tidak ada retry otomatis. `store:false` dikirim ke API; ini bukan janji bahwa semua kebijakan retensi penyedia ditiadakan. Penggunaan live mengikuti billing dan kebijakan akun API.

Jika key tidak terdeteksi, periksa environment pada container PHP dan definisi constant, tanpa mencetak secret. Jika model/request ditolak, periksa model, izin akun, dan log provider; draft tetap utuh. Jika draft berubah setelah proposal dibuat, buat proposal baru karena revisi lama ditolak.

## B. Agent MCP ke WordPress

### Persiapan administrator

1. Di **Users → Add New**, buat user khusus dengan role **Falcon Agent**. Jangan gunakan administrator sebagai credential agent.
2. Administrator membuka profil user itu dan membuat **Application Password** bernama, misalnya, `Falcon local agent`. Simpan password yang ditampilkan sekali di penyimpanan credential client. Ini berbeda dari password login user. Agent sendiri tidak boleh membuka dashboard.
3. Catat ID user dan UUID application password. Jika UUID tidak ditampilkan di profil, gunakan WP-CLI (hanya menampilkan UUID/nama, bukan secret):

```sh
docker compose --env-file local/.env -f local/compose.yml run --rm cli wp user get LOGIN_AGENT --field=ID
docker compose --env-file local/.env -f local/compose.yml run --rm cli wp user application-password list LOGIN_AGENT --fields=uuid,name --format=table
```

4. Buka **Falcon WF → AI → Agent → WordPress**. Isi ID user, UUID, ID konten yang boleh diakses (pisahkan koma), actions, fields, dan types. Simpan **Berikan scope explicit**. Grant berlaku 30 hari. Default semuanya ditolak.
5. Untuk percobaan awal, izinkan `read_published`, jenis `fwf_project`, satu ID Project publik, dan field `title`/`summary`. Tambahkan `create_draft`/`edit_draft` jika sudah siap. `read_draft` merupakan izin terpisah. Field wajib dalam group harus ikut grant jika diperlukan untuk membuat draft.

### Pengaturan client

Gunakan client MCP yang mendukung request HTTP JSON-RPC stateless dan **HTTP Basic**:

| Pengaturan | Nilai lokal |
| --- | --- |
| Endpoint | `http://localhost:8091/wp-json/falcon-wf/v1/mcp` |
| Authentication | HTTP Basic |
| Username | Login user Falcon Agent |
| Password | Application Password khusus tadi |
| Protocol version | `2025-03-26` |

Untuk server nonlokal, HTTPS diwajibkan. `localhost` hanya menunjuk mesin tempat client berjalan: client cloud/HP tidak bisa menjangkau laptop lewat URL lokal ini. Cloud memerlukan endpoint HTTPS yang dapat dijangkau serta client/auth yang kompatibel. Jangan menganggap memasukkan URL ini ke ChatGPT atau Codex langsung sudah cukup: OAuth, koneksi langsung memakai langganan ChatGPT, dan adapter STDIO Codex belum tersedia dalam build ini.

Uji tahap pertama `initialize`, berikutnya `tools/list`, lalu `tools/call` untuk `describe_schema`. Contoh payload discovery (kirim dengan auth client, jangan menaruh secret dalam payload):

```json
{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-03-26","capabilities":{},"clientInfo":{"name":"falcon-local-client","version":"1.0"}}}
```

```json
{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"describe_schema","arguments":{"type":"fwf_project"}}}
```

Lanjutkan membaca ID konten yang diizinkan dengan `read_content`, sertakan `fields` eksplisit sesuai grant. Untuk menulis, gunakan `idempotency_key` unik per operasi; retry operasi yang sama dengan key dan payload yang sama. `edit_draft` wajib menyertakan `expected_revision` dari pembacaan terakhir. Konflik harus dibaca ulang, bukan ditimpa paksa.

### Batas akses yang tersedia

| Tool | Kemampuan |
| --- | --- |
| `describe_schema` | Membaca definisi field untuk type/field dalam grant; tidak mengubah struktur |
| `read_content` | Membaca konten berdasarkan ID dan field dalam grant; published/draft memakai izin terpisah |
| `create_draft` | Membuat draft pada type yang diizinkan, memvalidasi field bertipe dan wajib |
| `edit_draft` | Mengubah draft dalam scope dan revisi yang masih sesuai |

Draft baru otomatis ditambahkan ke daftar objek scope agent pembuatnya. Draft yang dibaca juga harus memenuhi izin kepemilikan/editor WordPress. Maksimum 30 panggilan tool/menit per actor. Field custom dinamis mengikuti schema dan grant, sedangkan generator API dashboard saat ini hanya title/body/summary.

Belum tersedia: pencarian/list konten lewat tool, penetapan term taksonomi lewat tool, upload media, membuat/mengubah content type/field group/taksonomi, publishing, menghapus konten, mengganti theme, mengubah setting, atau menjalankan update. Agent tidak bisa melewati scope melalui dashboard, REST WordPress umum, atau XML-RPC.

### Memutuskan dan memeriksa koneksi

- **Cabut scope inbound** segera menolak panggilan berikutnya. Cabut juga Application Password di profil user untuk menonaktifkan credential.
- `FWF_AUTH`: periksa endpoint, HTTPS/environment lokal, role user, password, UUID grant, dan kedaluwarsa.
- `FWF_PERMISSION`: periksa action/type/ID/field; jangan langsung memberikan seluruh izin. Untuk `read_content`, pilih hanya fields dalam grant.
- `FWF_CONFLICT`: draft berubah; baca revisi terbaru sebelum mengedit lagi.
- `FWF_VALIDATION`: periksa tipe nilai (integer harus angka JSON), required fields dan pilihan enum. Gunakan `describe_schema` sebelum menyusun payload.

## Status penerimaan

Tes lokal mencakup autentikasi HTTP, discovery, scope per objek/field/type, membuat/mengedit draft, revisi, idempotensi, revokasi, serta penolakan akses REST/XML-RPC. Tes provider mencakup konteks terpilih, proposal, persetujuan manusia, konflik dan disconnect dengan mock. Koneksi provider sungguhan dan client AI pilihan pengguna tetap membutuhkan uji penerimaan tersendiri dengan credential pengguna. Alpha.2 belum dinyatakan siap produksi.
