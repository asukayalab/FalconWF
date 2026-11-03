# Project blueprint — alpha.22

FP alpha.22 dan FT alpha.7. Kontrak implementasi lokal; bukan acceptance hosting.

## Kepemilikan dan paket

Child theme membawa `falcon-blueprint.json` di root ZIP. Schema: `packages/falcon-plugin/resources/project-blueprint.schema.json`. Builder memvalidasi schema, identitas/versi proyek, role unik, template dan minimum FP/FT. Manifest mengikat file dengan SHA-256. Verifier memeriksa kontrak/hash sebelum installer menulis. JSON lain tidak otomatis diizinkan. Metadata blueprint tidak dieksekusi sebagai kode.

Contoh lengkap: `examples/projects/falcon-blueprint-reference`. Build dengan `node scripts/build-project.mjs --source examples/projects/falcon-blueprint-reference --output dist/blueprint-reference`. Manifest proyek dan blueprint harus memiliki project_id/version sama. Placeholder source `@@PROJECT_VERSION@@` dan `@@PROJECT_ID@@` diisi builder. Template memakai helper FT `FalconTheme\field($id, $key, $fallback)` dan `FalconTheme\projectPageUrl($project, $role)`; URL diambil dari permalink aktual Page yang sudah publish, bukan tebakan slug.

## Alur manusia

1. Build/rilis proyek melalui distribusi proyek existing, pasang FP/FT yang kompatibel dan child theme.
2. Preview lalu aktifkan child theme secara eksplisit melalui WordPress. Setup membaca blueprint theme aktif; pemasangan tidak mengaktifkan otomatis.
3. Falcon → Halaman & Desain → Setup website dari paket. Pilih Page existing atau draft baru untuk setiap role. Pilih pertahankan struktur lama atau terapkan struktur paket. Untuk definisi existing, default mempertahankan; definisi yang belum ada dapat dibuat.
4. Review tidak membuat Page. Tinjau ringkasan dan konfirmasi Apply. Capability sistem dan modul, nonce, token review sekali pakai, revision dan lease diperiksa.
5. Halaman baru selalu draft. Tinjau judul/body/field/menu, preview dan publish sendiri. Homepage dan aktivasi theme tetap keputusan manusia.

## Data dan keselamatan

Blueprint mengorkestrasi Definitions (schema), PageDesign (native template), native posts/terms dan Settings (mode SEO). Tidak ada editor/database konten kedua. Field group Page wajib menyebut page_roles; saat Apply diubah menjadi daftar ID Page. Definitions menyimpan batas ini; form manusia dan repository/MCP mengikuti konteks object sama. Discovery type boleh menunjukkan union schema; field object tetap discope.

Existing Page tidak diubah judul, slug, body, status atau nilai field. Mode struktur baru menerapkan template dan definisi yang dipilih, bukan menyalin konten Git ke body. Default field tidak menimpa nilai tersimpan. Published Page yang tidak memenuhi required field baru ditolak. Jenis/target field yang sudah memiliki kontrak tidak dikonversi diam-diam; gunakan key baru atau migrasi terpisah. Group bersama yang akan memengaruhi Page di luar pilihan struktur baru ditolak: pertahankan group atau gunakan key baru.

State `fwf_blueprints` menyimpan role → ID, digest/version, definisi yang dikelola dan status/error. Revision mencakup tema/paket/schema/Page/settings relevan. Perubahan setelah review memerlukan review baru. Kegagalan parsial mempertahankan draft dan ID; periksa lalu review/apply ulang. Bukan transaksi universal, tidak menghapus Page sebagai rollback. Backup settings mencakup state ini dan `fwf_field_contracts`.

## Batas

Maksimum file 256 KiB, 20 role Page, masing-masing 30 definisi per section, 100 term; pemeriksaan Page dibatasi 500 object. Tidak mengimpor media/body/menu atau mengubah permalink/homepage/robots/arbitrary options. SEO hanya pilihan mode renderer external/falcon existing; notes/instructions adalah panduan manusia. Tidak menyediakan schema mutation tool AI baru. Cloud/HTTPS client acceptance dan paket klien nyata belum dibuktikan oleh fixture lokal.
