# ERD - Penyewaan Garasi

```mermaid
erDiagram
    users ||--o{ vehicles : "memiliki"
    users ||--o{ rentals : "menyewa"
    vehicles ||--o{ rentals : "dipakai pada"
    garages ||--o{ rentals : "disewa pada"
    rentals ||--o{ charging_transactions : "riwayat isi listrik"

    users {
        uuid id PK
        string name
        string email UK
        string password
        string role "admin | tenant, default tenant"
        string nik UK "16 digit"
        string phone
        string registration_status "pending | approved | rejected, default pending, index"
    }

    vehicles {
        uuid id PK
        uuid user_id FK
        string type "bicycle | motorcycle | car | electric_car"
        string plate_number UK "null hanya untuk bicycle"
        string brand
        string model
        decimal maximum_capacity_kw "nullable, hanya mobil listrik"
    }

    garages {
        uuid id PK
        string code UK "S001, C010, E003"
        string type "bicycle | car | electric_car"
        decimal daily_price
        decimal price_per_kwh "nullable, hanya garasi mobil listrik"
    }

    rentals {
        uuid id PK
        uuid user_id FK
        uuid garage_id FK
        uuid vehicle_id FK
        string code "RNT-YYYYMMDD-NNNN, sama untuk sewa dan perpanjangannya"
        string status "new | extension, default new"
        string rental_type "daily | monthly"
        date start_date
        date end_date
        smallint days "hari pada baris ini"
        decimal daily_price "salinan harga saat sewa"
        decimal rent_amount "total biaya sewa baris ini"
        string payment_proof_path "penyewa (new) atau admin (extension)"
    }

    charging_transactions {
        uuid id PK
        uuid rental_id FK "baris rentals dengan status new"
        decimal current_capacity_kw
        decimal charged_kwh "maximum_capacity_kw dikurangi current_capacity_kw"
        decimal price_per_kwh "salinan harga garasi saat transaksi"
        decimal amount "charged_kwh x price_per_kwh"
        string payment_proof_path
    }
```

## Aturan unik dan index
- `users`: unik pada `email` dan `nik`; index pada `registration_status` (daftar pendaftar per status).
- `vehicles`: unik pada `plate_number` (boleh null untuk sepeda).
- `garages`: unik pada `code`.
- `rentals`: unik pada (`code`, `start_date`) sehingga perpanjangan tidak tersimpan dua kali dan pencarian berdasarkan `code` terlayani; index terpisah pada `garage_id`, `start_date` dan `end_date` untuk pengecekan ketersediaan dan tabrakan perpanjangan.

## Perilaku hapus dan ubah (foreign key)
- `rentals.user_id`, `rentals.garage_id`, `rentals.vehicle_id` dan `charging_transactions.rental_id`: restrict untuk hapus (riwayat keuangan tidak boleh hilang).
- `vehicles.user_id`: cascade untuk hapus.
- Semua foreign key memakai restrict untuk ubah (`restrictOnUpdate`).
- Catatan lengkap tabel dan konstanta ada pada bagian "ERD notes" di `.claude/agents/backend.md`.
