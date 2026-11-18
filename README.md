# SIPAR

Sistem Informasi Peminjaman Alat RPL SMK Negeri 5 Surakarta. Aplikasi Laravel 12 dengan antarmuka Bootstrap 5, database MySQL, akses berbasis peran, katalog berbasis kode alat, approval peminjaman, pengembalian, dan rekap transaksi.

## Menjalankan aplikasi

1. Pastikan PHP 8.2+ dengan ekstensi `gd`, `zip`, dan `xml` aktif, Composer, Node.js/npm, dan MySQL tersedia. Pada XAMPP, aktifkan `extension=gd` di `php.ini` sebelum menjalankan Composer.
2. Pasang dependency dan siapkan konfigurasi:

   ```powershell
   composer install
   if (!(Test-Path .env)) { Copy-Item .env.example .env }
   php artisan key:generate
   ```

3. Buat database MySQL bernama `sipar`, lalu atur `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` pada `.env`.
4. Aplikasi menggunakan pencarian kode alat manual pada katalog siswa dan halaman pengembalian teknisi. Pastikan `ENABLE_QR_SCANNER=false` pada `.env`, lalu jalankan `php artisan config:clear` pada deployment yang memakai config cache.
5. Atur `ADMIN_PASSWORD` dan `TECHNICIAN_PASSWORD` dengan password rahasia minimal 10 karakter di `.env`, lalu jalankan migrasi dan data awal. Seeder hanya membuat akun admin jika belum ada; seeding ulang tidak mengganti password admin yang sudah tersimpan.

   ```powershell
   php artisan migrate --seed
   ```

`UserSeeder` terpisah dari `DatabaseSeeder` dan tidak dijalankan otomatis saat deploy. Untuk membuat atau memperbarui akun SIPAR, isi `SIPAR_ADMIN_PASSWORD`, `SIPAR_TEST_STUDENT_PASSWORD`, dan `TECHNICIAN_PASSWORD` melalui environment secrets. Username teknisi dapat diatur dengan `TECHNICIAN_USERNAME` (default `teknisi`) dan email teknisi dengan `TECHNICIAN_EMAIL` (default `teknisi@sipar.sch.id`); password teknisi minimal 10 karakter. Jalankan `php artisan config:clear`, lalu `php artisan db:seed --class=UserSeeder --force` satu kali. Seeder memperbarui akun dengan email yang sama, mempertahankan ID dan relasi riwayat, serta mengubah password; pastikan rotasi kredensial ini memang dikehendaki. Password Admin dan Siswa Test yang singkat sebaiknya diganti dengan kredensial yang lebih kuat setelah akun uji dibuat. Password harus diatur sebagai secrets, tidak dimasukkan ke kode atau Git.

6. Jalankan aplikasi:

   ```powershell
   php artisan serve
   ```

   Buka `http://127.0.0.1:8000`. Aset CSS/JS utama menggunakan Bootstrap CDN. Vite dapat dibangun dengan `npm install` dan `npm run build` bila diperlukan.

## Deploy ke Railway dengan database persisten

SQLite di filesystem container Railway tidak cocok untuk penyimpanan permanen karena filesystem aplikasi dapat diganti saat deploy/restart. Gunakan layanan MySQL Railway:

1. Tambahkan layanan **MySQL** di project Railway yang sama dengan aplikasi.
2. Pada service aplikasi, buka **Variables** dan tambahkan referensi ke service MySQL. Jika nama service databasenya `MySQL`, atur:

   ```env
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```

   Ganti `MySQL` pada setiap referensi jika layanan database Anda memakai nama lain. Gunakan private/internal host yang diberikan Railway; jangan gunakan `127.0.0.1` untuk koneksi antar-service.
3. Atur **Pre-deploy Command** pada service aplikasi menjadi `php artisan migrate --force` agar migrasi berjalan ke database Railway setiap deploy. Katalog resmi berisi 14 produk, dengan Mikrofon dan Sound System sebagai item terpisah. Migrasi pemisahan menyimpan snapshot transaksi dan unit lama; migrasi tersebut akan berhenti jika masih ada transaksi `Pending` atau `Approved` yang menggunakan entri lama `Mic + sound`. Selesaikan transaksi terkait sebelum deploy ulang. Jangan gunakan `migrate:fresh` pada database berisi data.
4. Untuk database baru, atur `ADMIN_PASSWORD` dan `TECHNICIAN_PASSWORD` sebagai secrets dengan panjang minimal 10 karakter, lalu jalankan `php artisan db:seed --force` satu kali melalui shell/deploy job terkontrol. Seeder inventaris menyetel ulang katalog dan stok ke daftar resmi, mengarsipkan relasi historis, serta menolak berjalan saat ada transaksi aktif. Seeder juga membuat transaksi demo aktif `PJM-DEMO-TERLAMBAT` dengan jatuh tempo tujuh hari sebelumnya untuk menguji indikator keterlambatan dan denda Rp5.000 per hari. Jangan jalankan ulang sebagai bagian dari startup/redeploy; seeding juga tidak mengganti password admin yang sudah ada.
5. Deploy ulang, lalu pastikan log deploy menunjukkan migrasi berhasil. Data yang sebelumnya tersimpan di SQLite lokal/container tidak otomatis dipindahkan; impor sekali ke database Railway jika perlu dipertahankan.

Untuk mempertahankan foto alat dan bukti transaksi saat container diganti, tambahkan **Railway Volume** pada service aplikasi dengan mount path `/app/storage/app/public`. Aplikasi menyimpan upload pada disk Laravel `public` dan membuat `public/storage` melalui `php artisan storage:link`; database hanya menyimpan path file, sedangkan isi file harus berada di volume persisten.

Unggahan foto bukti dan foto alat divalidasi (JPG/PNG/WEBP, maksimal 5 MB), dibatasi hingga 12 megapiksel, lalu diperkecil ke sisi terpanjang maksimal 1920 px sebelum disimpan. JPEG dan WEBP dikompresi pada kualitas 82; PNG mempertahankan kompresi lossless. Pemrosesan memerlukan ekstensi PHP GD, yang disediakan oleh konfigurasi Nixpacks Railway dan harus diaktifkan pada PHP lokal.

Konfigurasi juga mengenali variabel bawaan service MySQL Railway (`MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, atau `MYSQL_URL`) sebagai fallback. Tetap set `DB_CONNECTION=mysql` pada service aplikasi agar aplikasi tidak mencoba memakai SQLite.

## Akun demo

Seeder membuat akun demo berikut. Password akun admin tidak memiliki nilai default; set `ADMIN_PASSWORD` (minimal 10 karakter) sebelum membuat akun admin pertama. Password admin yang sudah ada tidak diubah saat seeding ulang.

| Peran | NIS/NIP | Email | Password |
| --- | --- | --- | --- |
| Admin / Toolman | `ADMIN` | `admin@sipar.sch.id` | Diatur melalui `ADMIN_PASSWORD` |
| Siswa | `20230001` | `siswa@sipar.sch.id` | `password123` |

Siswa juga dapat mendaftar sendiri melalui halaman masuk. Pendaftaran publik hanya dapat membuat akun dengan role `siswa`.

## Modul

- Admin: dashboard, CRUD alat/kategori/akun siswa, approval/tolak seluruh pengajuan, serta laporan dan rekap riwayat pengembalian. Persetujuan memeriksa dan mengunci stok untuk semua item secara atomik; Asset ID dicatat saat penyerahan teknisi.
- Teknisi: menerima tugas setelah pengajuan Admin disetujui, mencatat penyerahan dan penerimaan fisik dengan bukti foto kamera. Stok diperbarui langsung saat pengembalian dicatat, tanpa approval lanjutan Admin.
- Siswa: katalog dan pencarian kode alat, keranjang multi-item dengan masa pinjam maksimal lima hari, status/riwayat transaksi, profil, serta perubahan password.
- Teknisi: pencarian transaksi melalui Kode Booking atau NIS, pencatatan Asset ID/serial number dan foto saat serah terima, serta pengembalian per item dengan kondisi dan pembaruan stok otomatis.
- Admin: persetujuan mengunci stok seluruh keranjang dalam satu transaksi database dan mencatat lokasi serta jam pengambilan untuk siswa.
- Jadwal Laravel harian menandai transaksi melewati jatuh tempo dengan flag overdue dan reminder; pastikan `php artisan schedule:run` dijalankan setiap menit pada deployment.
- Stok baru berkurang saat admin menyetujui pengajuan. Stok dikunci dan diperiksa ulang dalam transaksi database saat pengajuan diproses. Jumlah total direkonsiliasi sebagai tersedia, maintenance, rusak, dan sedang dipinjam.
- Pengembalian dicatat per alat dan jumlah, sehingga satu transaksi multi-alat dapat dikembalikan bertahap. Status transaksi menjadi `Selesai` setelah seluruh jumlah item kembali. Kondisi `Baik` mengembalikan unit ke stok tersedia, `Maintenance` menambah stok maintenance, `Rusak` menambah stok rusak, dan `Hilang` mengurangi stok total. Admin hanya dapat memindahkan unit maintenance/rusak ke stok tersedia dengan menyatakan kondisi asal dan jumlah unit yang benar-benar selesai diperbaiki. Denda keterlambatan Rp 5.000 per hari dibagi proporsional menurut jumlah item transaksi, dengan koreksi pembulatan saat item terakhir kembali.
- Akun siswa yang memiliki riwayat peminjaman tidak dapat dihapus. Pembuatan peminjaman dan penghapusan akun diserialisasi dengan row lock; foreign key database juga menolak penghapusan yang akan menghapus riwayat.
- `Export Excel (.xlsx)` menghasilkan workbook Excel asli melalui Laravel Excel. `Unduh PDF` menghasilkan laporan PDF dari server.

## Pemeriksaan

```powershell
php artisan test
```

Suite berjalan pada SQLite in-memory dan mencakup migrasi, pembatasan role, serta alur pengajuan sampai pengembalian terlambat.
#   S I P A R   -   S i s t e m   P e m i n j a m a n   A l a t   R P L  
 