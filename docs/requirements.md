Anda diminta untuk membuat aplikasi berbasis mobile atau web dengan basis data relasional yang memenuhi spesifikasi kebutuhan sebagai berikut:

Sebuah perusahaan penyewaan garasi kendaraan memiliki sistem bisnis sebagai
berikut:

- Garasi disewakan secara harian atau bulanan
- Terdapat tiga tipe garasi, yaitu garasi sepeda, garasi mobil bensin, dan garasi mobil listrik.
- Sebuah sepeda atau sepeda motor hanya dapat berada di garasi sepeda
- Mobil hanya dapat berada di garasi mobil
- Mobil listrik dapat berada di garasi mobil atau mobil listrik
- Mobil listrik yang berada di garasi mobil listrik harus mengisi listrik hingga penuh. Pengisian dicatat sebagai transaksi isi listrik tersendiri setelah penyewaan berjalan (bukan dibayar saat sewa).
- Harga sewa harian dan jumlah setiap tipe garasi dimasukkan oleh admin.
- Harga sewa bulanan adalah 25 x harga sewa harian
- Harga isi listrik per kWh dimasukkan admin saat membuat garasi mobil listrik (satu harga per batch, disimpan pada setiap garasi). Kapasitas maksimum kW diisi penyewa pada data kendaraan mobil listrik. Tidak ada kecepatan isi listrik dan tidak ada pengaturan sistem global.
- Seorang penyewa dapat menyewa lebih dari satu garasi pada waktu yang bersamaan.

Aplikasi memiliki dua role pengguna:
○ Admin
○ Penyewa

Seorang Penyewa dapat mengakses fitur:
1. Mendaftar sebagai Penyewa, dengan memasukkan informasi:
■ NIK (Nomor KTP)
■ Nama lengkap
■ Nomor Telepon
■ Email (sebagai username)
■ Password
2. Sewa Baru sebuah garasi.
2.1. Penyewa harus memasukkan informasi:
● Detail Kendaraan: Tipe (sepeda, sepeda motor, mobil, mobil listrik), nomor plat kendaraan
(kecuali sepeda), merek dan tipe, kapasitas baterai maksimum dalam kW (hanya untuk mobil listrik)
● Tipe Sewa: Harian / Bulanan
● Jumlah hari (hanya untuk sewa harian, maksimum jumlah hari pada bulan tanggal mulai dikurangi 1)
● Tanggal Mulai Sewa: default hari ini, penyewa boleh memilih tanggal mulai lain (booking)
● Tanggal Selesai Sewa (isi otomatis)
● Tipe Garasi: pilihan disesuaikan dengan tipe kendaraan
● Aplikasi menampilkan daftar garasi dengan tipe dipilih yang tersedia pada tanggal mulai sewa sampai tanggal selesai sewa
● Bukti Pelunasan Biaya (upload gambar oleh penyewa), Catatan: Total Biaya Sewa dihitung oleh sistem dan ditampilkan kepada penyewa. Pembayaran selalu di awal, dan sewa baru hanya membayar biaya sewa garasi (biaya isi listrik dibayar terpisah, lihat 2.3)
2.2. Setelah penyewa berhasil menyimpan data, garasi berstatus 'used' selama tanggal sewa mencakup hari ini, dan 'available' bila tidak (status dihitung dari tanggal penyewaan, tidak disimpan sebagai kolom)
2.3. Isi listrik (hanya mobil listrik di garasi mobil listrik, selama penyewaan berjalan). Penyewa dapat menambah transaksi isi listrik berulang kali, masing-masing dengan:
● Jumlah baterai saat ini dalam kW (antara 0 dan kapasitas maksimum kendaraan)
● Bukti Pelunasan Biaya (upload gambar oleh penyewa). Sistem menghitung kWh terisi dan biayanya dan menampilkannya sebelum disimpan
3. Admin dapat mengakses fitur:
3.1. Buat Admin dapat membuat sejumlah garasi baru secara berkelompok sesuai tipe garasi (Sepeda, Mobil, dan Mobil Listrik) dengan memasukkan info:
■ Tipe garasi
■ ID garasi otomatik dibuat dengan kode S+<3 digit> untuk sepeda, C+<3 digit> untuk mobil, dan E+<3 digit>
untuk mobil listrik, Tiga digit tersebut adalah hasil incremental setiap kali garasi dengan tipe yang sama
dibuat, Contoh: Garasi Sepeda pertama mempunyai kode S001, Garasi Mobil kesepuluh berkode C010
■ Jumlah garasi (maksimum 999 per tipe)
■ Harga sewa harian (berlaku untuk seluruh garasi dalam batch tersebut)
■ Harga per kWh (hanya untuk garasi Mobil Listrik, berlaku untuk seluruh garasi dalam batch tersebut)
3.2. Verifikasi Pendaftar Baru. Setelah cek validasi KTP (centang manual), admin bisa menerima atau menolak pendaftaran Penyewa Baru. Status pendaftar: pending, approved, atau rejected. Hanya penyewa approved yang dapat login.
3.3. Perpanjang Status Garasi.
■ Aplikasi menampilkan daftar garasi yang sedang disewa pada hari itu
■ Admin memilih salah satu garasi
■ Admin dapat mengubah tanggal Selesai Sewa (hanya memperpanjang, tidak boleh memperpendek, tidak ada batas maksimal hari).
■ Perpanjangan ditolak jika bertabrakan dengan booking berikutnya pada garasi yang sama; admin hanya dapat memperpanjang sampai sehari sebelum booking tersebut.
■ Aplikasi menghitung sisa biaya yang harus dibayarkan: jumlah hari tambahan x harga sewa harian (berlaku untuk sewa harian maupun bulanan)
■ Admin mengupload bukti bayar
3.4. Laporan sewa garasi. Aplikasi dapat menampilkan hasil persewaan garasi:
■ Per bulan dalam tahun tertentu
■ Per hari dalam bulan tertentu
■ Per Tipe Garasi
■ Per Penyewa
■ Setiap laporan menampilkan jumlah penyewaan dan total pendapatan (sewa, isi listrik, dan perpanjangan)

Lingkup pekerjaan dalam proyek ini adalah:
1. merancang use-case diagram, class diagram, dan entity-relationship
diagram sesuai dengan spesifikasi kebutuhan yang telah ditentukan
2. mengimplementasikan rancangan dengan:
a. membuat kode program sesuai dengan guideline dan best practice
b. menerapkan pemrograman terstruktur dan berorientasi objek.
c. melengkapi kode program dengan dokumentasi kode program
d. menggunakan library atau komponen pra-existing
e. membuat basis data relasional dan akses agara aplikasi terhubung dengan basis data tersebut
3. merancang dan melakukan skenario unit testing

Aturan tambahan:
- Sewa bulanan hanya 1 bulan, dihitung dari tanggal mulai sewa sebanyak jumlah hari pada bulan tersebut. Harga sewa bulanan tetap 25 x harga sewa harian, berapa pun jumlah hari pada bulan itu.
- Sewa harian maksimum adalah jumlah hari pada bulan tanggal mulai dikurangi 1. Tanggal selesai = tanggal mulai + jumlah hari - 1.
- Tanggal mulai sewa default hari ini, penyewa boleh memilih tanggal mulai yang lebih akhir (booking), tetapi tidak boleh tanggal yang sudah lewat. Status garasi ('used' atau 'available') tidak disimpan, melainkan dihitung dari tanggal penyewaan: 'used' bila ada baris sewa (baru maupun perpanjangan) yang mencakup hari ini. Ketersediaan untuk tanggal tertentu dihitung dari tumpang tindih tanggal penyewaan. Formulir sewa hanya menampilkan garasi yang bebas dan sesuai tipe kendaraan (bisa dicari berdasarkan kode, daftar dibatasi dengan tombol "show more").
- Kendaraan didaftarkan penyewa lebih dulu (tipe, nomor plat, merek, model, dan kapasitas maksimum kW untuk mobil listrik), lalu dipilih saat penyewaan garasi. Satu kendaraan dapat dipakai pada beberapa penyewaan, tetapi tidak boleh berada di dua garasi pada tanggal yang tumpang tindih.
- Satu penyewa dapat menyewa banyak garasi/kendaraan.
- Validasi KTP hanya verifikasi manual oleh admin dengan centang, tanpa teknologi otomatis.
- Pembayaran selalu di awal dan hanya untuk biaya sewa garasi. Bukti bayar sewa baru berupa gambar (maksimum 2 MB) yang diupload oleh penyewa dan hanya disimpan sebagai arsip (tidak ada konfirmasi admin); penyewaan aktif setelah disimpan.
- Perpanjangan sewa hanya dilakukan oleh admin pada sewa yang sudah ada dan masih berjalan (tidak bisa setelah sewa selesai). Yang diubah hanya tanggal selesai, dan hanya ke tanggal yang lebih akhir. Perpanjangan disimpan sebagai baris baru di tabel `rentals` dengan status `extension`, kode sewa yang sama dengan sewa aslinya, dan tanggal mulai sehari setelah tanggal selesai sebelumnya (tanpa tabel perpanjangan dan tanpa parent id). Biaya = jumlah hari tambahan x harga sewa harian sewa tersebut (juga untuk sewa bulanan). Perpanjangan ditolak jika bertabrakan dengan booking lain pada garasi atau kendaraan yang sama. Admin yang mengupload bukti bayar.
- Biaya isi listrik (hanya untuk mobil listrik di garasi mobil listrik): dicatat sebagai transaksi terpisah di tabel `charging_transactions` yang menunjuk ke baris sewa baru (bukan baris perpanjangan). Setelah penyewaan berjalan (perpanjangan dihitung sebagai masih berjalan), penyewa dapat menambah transaksi sebanyak yang diinginkan, masing-masing dengan bukti bayar sendiri. Penyewa mengisi jumlah baterai saat ini (kW, bukan persen, antara 0 dan kW maksimum kendaraan). Sistem menghitung kWh terisi = kW maksimum - baterai saat ini, lalu biaya = kWh terisi x harga per kWh garasi. Contoh: maksimum 10 kW, baterai saat ini 2 kW, maka bayar 8 x harga per kWh. Baterai yang sudah penuh ditolak. Mobil listrik di garasi mobil biasa tidak dikenakan biaya isi listrik.
- Harga per kWh disimpan pada garasi mobil listrik (diisi admin per batch), dan kapasitas maksimum kW pada kendaraan (diisi penyewa). Harga sewa harian disalin ke setiap penyewaan, dan harga per kWh serta jumlah biaya disalin ke setiap transaksi isi listrik, sehingga riwayat lama tidak berubah meskipun harga garasi berubah kemudian. Tidak ada kecepatan isi listrik dan tidak ada tabel pengaturan sistem.
- Garasi kembali tersedia secara otomatis berdasarkan tanggal (sehari setelah tanggal selesai sewa), tanpa job terjadwal.
- Akun admin pertama dibuat lewat seeder (`admin@test`). Admin tidak dapat mendaftar lewat aplikasi; hanya penyewa yang dapat mendaftar sendiri (NIK 16 digit), dengan status awal pending. Setelah password benar, login menampilkan pesan terpisah untuk status pending dan rejected.
- Nomor plat wajib dan unik untuk sepeda motor, mobil, dan mobil listrik; sepeda tidak memiliki nomor plat. Sepeda dan sepeda motor sama-sama hanya dapat berada di garasi sepeda.
- Aplikasi tidak memiliki fitur ubah dan hapus data; halaman hanya berupa daftar, tambah, dan lihat detail. Kode penyewaan berformat `RNT-YYYYMMDD-NNNN` (nomor urut 4 digit per hari).
- Laporan hanya untuk admin, dengan filter tahun, bulan, tipe garasi, dan penyewa (tahun paling awal 2026). Jumlah penyewaan hanya menghitung baris sewa baru. Pendapatan sewa adalah jumlah biaya sewa baris baru dan perpanjangan (dikelompokkan menurut tanggal mulai barisnya), pendapatan isi listrik dikelompokkan menurut tanggal transaksi dicatat, dan total adalah jumlah keduanya.
- Jika pembuatan garasi berkelompok membuat jumlah garasi satu tipe melebihi 999, seluruh batch ditolak dengan pesan sisa garasi yang masih dapat dibuat untuk tipe tersebut, dan tidak ada garasi yang dibuat.
