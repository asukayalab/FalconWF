# Contoh lokal Falcon WF

Jalankan `npm run local:seed` setelah build/install lokal. Seeder hanya bekerja di WP_ENVIRONMENT_TYPE=local dan mempertahankan edit manusia.

Bawaan baru: satu Project fiktif dengan ilustrasi AI, Kategori Project → Bangunan, serta halaman Karya Bangunan yang memanggil shortcode listing. Publikasi/Learning lama tidak dihapus, tetapi tidak diaktifkan/dibuat oleh seeder baru. JSON tetap mempertahankan referensi contoh lama untuk kompatibilitas.

Semua gambar adalah ilustrasi AI untuk demo, bukan foto atau fakta proyek nyata. Seed tidak mengubah homepage. File/aset contoh tidak masuk release ZIP dan database/uploads lokal tidak masuk Git.

Shortcode: `[falcon_listing type="fwf_project" taxonomy="fwf_project_cat" term="bangunan" limit="12"]`.
