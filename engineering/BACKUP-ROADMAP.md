# Urutan kerja backup menuju FWF 0.1

| Urutan | Pekerjaan | Bukti kelulusan yang diperlukan |
|---|---|---|
| 1 — implementasi alpha.12; pengujian host tetap pending | Backup berkala: pilihan harian/mingguan, jam/zona waktu, komponen dan jadwal berikutnya; cegah overlap, catat gagal/berhasil, gunakan antrean dan retensi yang sama. | Cron nyata Docker, terlambat/tidak ada traffic, permission berubah, deaktivasi/reaktivasi, retry tanpa jadwal ganda. |
| 2 — implementasi alpha.13; host rehearsal pending | Media terpilih termasuk report: pilih attachment dan seluruh varian/dependensi; preview estimasi ukuran. | Pilihan tidak memasukkan media lain; relasi file/metadata dan restore tetap tersambung. |
| 3 — fondasi lokal alpha.14; host/failover certification pending | Pemulihan saat proses terputus dan WordPress gagal bootstrap; rekonsiliasi journal/commit yang belum pasti. | Failure drill terisolasi sebelum/selama/setelah commit, jalur operator yang reviewable tanpa replay otomatis ambigu. |
| 4 | Google Drive atau storage lain: owner/aplikasi OAuth teridentifikasi, koneksi server-side, upload/download bertahap, retry dan integritas. | Akun/cloud nyata dengan consent owner, timeout/token expiry/putus transfer, tidak membocorkan secret atau membuat ZIP publik. |
| 5 | Restore dari cloud memakai validator lokal yang sama, latihan staging hosting dan target RPO/RTO. | Download/verifikasi/review/safety/restore nyata; cloud retention berbeda dari local retention, tidak ikut terhapus tanpa kebijakan eksplisit. |
| 6 | Panduan pengguna sederhana, audit keamanan dan persiapan rilis0.1. | Instruksi lengkap pengisian/aktivasi/kegagalan; dua arah AI dan update nyata tetap wajib melewati gerbang rilis. |

Journal/rescue lokal alpha.14 menangani proses terputus yang bisa dibuktikan; koneksi DB ambigu/host failure tetap perlu operator. Retensi/perlindungan lokal selesai implementasi alpha.11; jadwal berkala lokal di alpha.12 setelah bukti verifikasi tercatat di EVIDENCE.md. Ini urutan implementasi, bukan deklarasi semua gate selesai. Desain Global tetap dibekukan; ingatkan referensi Oxygen Builder saat area tersebut dibahas kembali. docs/ dan session-notes/ tetap lokal saja.
