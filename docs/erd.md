# ERD - Penyewaan Garasi

```mermaid
erDiagram
    users ||--o{ vehicles : "memiliki"
    users ||--o{ rentals : "menyewa"
    users ||--o{ rental_extensions : "admin memperpanjang"
    vehicles ||--o{ rentals : "dipakai pada"
    garages ||--o{ rentals : "disewa pada"
    rentals ||--o{ rental_extensions : "diperpanjang lewat"

    users {
        bigint id PK
        string name
        string email UK
        string password
        string role "admin | tenant"
        string nik UK "null untuk admin"
        string phone "null untuk admin"
        string registration_status "pending | approved | rejected"
    }

    vehicles {
        bigint id PK
        bigint user_id FK
        string type "bicycle | motorcycle | car | electric_car"
        string plate_number "null hanya untuk bicycle"
        string brand
        string model
    }

    garages {
        bigint id PK
        string code UK "S001, C010, E003"
        string type "bicycle | car | electric_car"
        smallint number "1 sampai 999, urut per tipe"
        decimal daily_price
    }

    rentals {
        bigint id PK
        bigint user_id FK
        bigint garage_id FK
        bigint vehicle_id FK
        string rental_type "daily | monthly"
        date start_date
        date end_date
        smallint days
        decimal daily_price "salinan harga saat sewa"
        decimal rent_amount
        decimal battery_current_kw "null jika bukan EV di garasi EV"
        decimal battery_max_kw "salinan pengaturan"
        decimal price_per_kwh "salinan pengaturan"
        decimal charging_speed_kw_per_hour "salinan pengaturan"
        decimal charging_amount "null jika tidak ada isi listrik"
        decimal total_amount
        string payment_proof_path "diupload penyewa"
    }

    rental_extensions {
        bigint id PK
        bigint rental_id FK
        bigint admin_id FK
        date previous_end_date
        date new_end_date
        smallint added_days
        decimal daily_price "salinan dari rentals.daily_price"
        decimal amount
        string payment_proof_path "diupload admin"
    }

    charging_settings {
        bigint id PK
        decimal price_per_kwh
        decimal max_kw
        decimal speed_kw_per_hour "1"
    }
```

`charging_settings` berdiri sendiri: satu baris untuk seluruh sistem, tanpa relasi. Penyewaan hanya menyalin nilainya ke `rentals`.

## Penjelasan singkat
- **users**: satu tabel untuk admin dan penyewa, dibedakan kolom `role`. `registration_status` dipakai verifikasi KTP; hanya `approved` yang boleh login.
- **vehicles**: kendaraan milik penyewa, bisa dipakai ulang di beberapa penyewaan. Plat hanya kosong untuk sepeda.
- **garages**: `code` dibuat dari awalan tipe (S/C/E) + `number` 3 digit. Ketersediaan tidak disimpan; dihitung dari tanggal pada `rentals` dan `rental_extensions`.
- **rentals**: satu baris per penyewaan. Harga dan pengaturan isi listrik disalin ke baris ini supaya invoice lama tidak berubah. Kolom baterai dan isi listrik kosong kecuali mobil listrik di garasi mobil listrik. `end_date` selalu berisi tanggal selesai terbaru (setelah perpanjangan).
- **rental_extensions**: riwayat perpanjangan oleh admin, termasuk biaya tambahan dan bukti bayar.
