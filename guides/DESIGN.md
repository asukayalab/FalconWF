# Desain Global

Buka Falcon WF → Desain Global setelah Falcon Theme atau child theme yang kompatibel aktif. Pemasangan theme tidak otomatis mengaktifkan theme. Form memerlukan FT alpha.4 atau lebih baru yang menyediakan contract ini.

1. Pilih font teks dan font judul dari font lokal perangkat.
2. Isi warna dalam format #RRGGBB (contoh #20322c).
3. Isi ukuran teks, jarak baris, lebar konten, jarak tepi dan ukuran maksimum H1–H6 sesuai rentang yang tampil.
4. Simpan desain, lalu periksa halaman frontend. Tidak ada perubahan Judul Situs, konten atau theme aktif.

Kosong berarti mengikuti desain bawaan proyek. Gunakan tombol Kembalikan desain bawaan atau kosongkan seluruh field lalu simpan untuk mengembalikan desain bawaan theme aktif. Pengaturan parent dan setiap child terpisah; setiap instalasi menyimpan nilainya sendiri. Saat FP dinonaktifkan, FT tetap membaca dan menampilkan pengaturan yang tersimpan. Theme yang tidak menggunakan token Falcon tidak menerima perubahan ini.

Jika form lain lebih dahulu menyimpan desain atau theme aktif berubah, muat ulang form. Perubahan lama ditolak untuk menjaga nilai terbaru. Pengaturan desain tidak diekspos sebagai tool AI.

H1–H6 adalah ukuran visual maksimum. Ukuran menyesuaikan layar kecil. Tentukan tingkat heading lewat struktur konten; mengganti ukuran font tidak mengganti tag heading. Ini belum menyediakan title/description/canonical/schema SEO.

## Contract untuk pembuat child theme

FT inc/design.php adalah owner field, validasi, font stack, range, pembacaan opsi dan renderer. FP Dashboard → Settings melakukan capability/nonce → lock → revision → validasi FT → option; FT assets → inline --fwf-* → parent/child CSS → template.

| Input | CSS token | Unit / nilai |
|---|---|---|
| body_font, heading_font | --fwf-body-font, --fwf-heading-font | stack font lokal allowlist |
| ink, paper, accent, muted, line | --fwf-ink, --fwf-paper, --fwf-accent, --fwf-muted, --fwf-line | warna hex enam digit |
| body_size | --fwf-body-size | px, 14–24 |
| line_height | --fwf-line-height | tanpa unit, 1.2–2 |
| content_width | --fwf-content-width | px, 640–1600 |
| spacing | --fwf-spacing | px, 16–96; padding mobile dibatasi |
| h1–h6 | --fwf-h1 sampai --fwf-h6 | px, bounded per field; gunakan clamp responsif |

Gunakan fallback di child CSS, misalnya `--reference-ink:var(--fwf-ink,#20322c)` dan `color:var(--reference-ink)`. Jangan mendefinisikan ulang --fwf-* atau membuat form/validasi yang menduplikasi contract FT. Komponen seperti kartu/label dapat punya layout/skala bawaan tersendiri; layout final tetap milik child. Native inline formatting dari editor atau CSS eksternal dapat mengalahkan global typography.

Nilai disimpan non-autoload pada fwf_design_{stylesheet}. Pengaturan database tidak dimasukkan ke ZIP atau Git desain klien. Backup situs harus mencakup opsi ini. Tidak menerima arbitrary CSS, remote font URL atau script.
