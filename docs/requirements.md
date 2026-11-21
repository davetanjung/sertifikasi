Anda diminta untuk membuat aplikasi berbasis mobile atau web dengan basis data relasional yang memenuhi spesifikasi kebutuhan sebagai berikut:

Sebuah perusahaan penyewaan garasi kendaraan memiliki sistem bisnis sebagai
berikut:

- Garasi disewakan secara harian atau bulanan
- Terdapat tiga tipe garasi, yaitu garasi sepeda, garasi mobil bensin, dan garasi mobil listrik.
- Sebuah sepeda atau sepeda motor hanya dapat berada di garasi sepeda
- Mobil hanya dapat berada di garasi mobil
- Mobil listrik dapat berada di garasi mobil atau mobil listrik
- Mobil listrik yang berada di garasi mobil listrik harus mengisi listrik hingga penuh.
- Harga sewa harian dan jumlah setiap tipe garasi dimasukkan oleh admin.
- Harga sewa bulanan adalah 25 x harga sewa harian
- Harga isi listrik per kWh, kapasitas maksimum kW per mobil, dan kecepatan isi listrik (1 kW per jam) diatur oleh admin satu kali sebagai pengaturan sistem, dan berlaku sama untuk semua garasi mobil listrik
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
(kecuali sepeda), merek dan tipe, jumlah baterai saat ini dalam kW (hanya untuk mobil listrik yang dipilihkan garasi mobil listrik)
● Tipe Sewa: Harian / Bulanan
● Jumlah hari (hanya untuk sewa harian, maksimum jumlah hari pada bulan tanggal mulai dikurangi 1)
● Tanggal Mulai Sewa: default hari ini, penyewa boleh memilih tanggal mulai lain (booking)
● Tanggal Selesai Sewa (isi otomatis)
● Tipe Garasi: pilihan disesuaikan dengan tipe kendaraan
● Aplikasi menampilkan daftar garasi dengan tipe dipilih yang tersedia pada tanggal mulai sewa sampai tanggal selesai sewa
● Bukti Pelunasan Biaya (upload gambar oleh penyewa), Catatan: Total Biaya Sewa dihitung oleh sistem dan ditampilkan kepada penyewa. Pembayaran selalu di awal. Untuk garasi mobil listrik, invoice menampilkan dua jumlah terpisah: biaya sewa garasi dan biaya isi listrik
2.2. Setelah penyewa berhasil menyimpan data, status garasi berubah menjadi ‘used’
3. Admin dapat mengakses fitur:
3.1. Buat Admin dapat membuat sejumlah garasi baru secara berkelompok sesuai tipe garasi (Sepeda, Mobil, dan Mobil Listrik) dengan memasukkan info:
■ Tipe garasi
■ ID garasi otomatik dibuat dengan kode S+<3 digit> untuk sepeda, C+<3 digit> untuk mobil, dan E+<3 digit>
untuk mobil listrik, Tiga digit tersebut adalah hasil incremental setiap kali garasi dengan tipe yang sama
dibuat, Contoh: Garasi Sepeda pertama mempunyai kode S001, Garasi Mobil kesepuluh berkode C010
■ Jumlah garasi (maksimum 999 per tipe)
■ Harga sewa
■ Tidak ada input tambahan untuk garasi Mobil Listrik (harga per kWh, kW maksimum, dan kecepatan isi diatur di pengaturan sistem)
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
- Sewa harian maksimum adalah jumlah hari pada bulan tanggal mulai dikurangi 1.
- Tanggal mulai sewa default hari ini, tetapi penyewa boleh memilih tanggal mulai lain (booking). Ketersediaan garasi dicek berdasarkan tumpang tindih tanggal, bukan hanya status 'used'.
- Data kendaraan dimasukkan pada saat penyewaan garasi.
- Satu penyewa dapat menyewa banyak garasi/kendaraan.
- Validasi KTP hanya verifikasi manual oleh admin dengan centang, tanpa teknologi otomatis.
- Pembayaran selalu di awal. Bukti bayar sewa baru diupload oleh penyewa dan hanya disimpan sebagai arsip (tidak ada konfirmasi admin); penyewaan aktif setelah disimpan.
- Perpanjangan sewa hanya dilakukan oleh admin pada sewa yang sudah ada (bukan membuat sewa baru). Yang diubah hanya tanggal selesai. Admin yang mengupload bukti bayar, dan sistem menambahkan jumlah biaya tambahan.
- Biaya isi listrik (hanya untuk mobil listrik di garasi mobil listrik): penyewa mengisi sekali saat sewa jumlah baterai saat ini (kW, bukan persen). Sistem menghitung kebutuhan = kW maksimum - baterai saat ini, lalu biaya = kebutuhan x harga per kWh. Contoh: maksimum 10 kW, baterai saat ini 2 kW, maka bayar 8 x harga per kWh. Kecepatan isi 1 kW per jam hanya dipakai untuk menampilkan estimasi lama pengisian. Baterai saat ini harus antara 0 dan kW maksimum. Mobil listrik di garasi mobil biasa tidak dikenakan biaya isi listrik.
- Harga per kWh, kW maksimum, dan kecepatan isi dikelola admin sebagai satu pengaturan sistem. Jumlah biaya yang sudah dihitung pada sebuah penyewaan (sewa, isi listrik, beserta nilai harga/kW yang dipakai) disimpan pada penyewaan tersebut dan tidak berubah meskipun pengaturan diubah kemudian.
- Garasi kembali tersedia secara otomatis berdasarkan tanggal (sehari setelah tanggal selesai sewa), tanpa job terjadwal. Status 'used' ditentukan dari penyewaan yang sedang berjalan.
- Akun admin pertama dibuat lewat seeder. Admin tidak dapat mendaftar lewat aplikasi; hanya penyewa yang dapat mendaftar sendiri.
- Nomor plat wajib untuk sepeda motor, mobil, dan mobil listrik; sepeda tidak memiliki nomor plat. Sepeda dan sepeda motor sama-sama hanya dapat berada di garasi sepeda.
- Jika pembuatan garasi berkelompok membuat jumlah garasi satu tipe melebihi 999, seluruh batch ditolak dengan pesan sisa garasi yang masih dapat dibuat untuk tipe tersebut, dan tidak ada garasi yang dibuat.
