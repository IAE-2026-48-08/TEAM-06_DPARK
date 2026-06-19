## Informasi Proyek

| Komponen            | Detail                                     |
| ------------------- | ------------------------------------------ |
| Nama Proyek         | DPark Membership Service                   |
| Framework           | Laravel 12                                 |
| Database            | MySQL 8.4                                  |
| Teknologi Tambahan  | GraphQL (Lighthouse), Docker, Laravel Sail |
| Sistem Operasi      | Windows 10/11                              |
| Container Platform  | Docker Desktop                             |
| Metode Deployment   | Docker Compose (Laravel Sail)              |
| Port Aplikasi       | 8000                                       |
| Port Database       | 3306                                       |
| Metode Pengembangan | AI-Assisted Development                    |

---

# 1. Persiapan Lingkungan Pengembangan

## Tujuan

Memastikan aplikasi Laravel dapat dijalankan dan diuji pada lingkungan lokal sebelum dilakukan containerization menggunakan Docker.

## Permasalahan

Setelah menjalankan aplikasi menggunakan:

```bash
php artisan serve
```

halaman yang muncul hanya halaman default Laravel.

## Analisis

Berdasarkan hasil diskusi dengan AI, aplikasi yang dikembangkan merupakan backend service berbasis REST API sehingga tidak memiliki antarmuka frontend pada root URL (`/`).

## Solusi

Melakukan pengujian endpoint API secara langsung:

```http
GET /api/v1/members
GET /api/v1/vouchers
```

## Hasil

Endpoint berhasil merespons data dalam format JSON sehingga backend service dapat dinyatakan berjalan dengan baik.

---

# 2. Migrasi Database dari SQLite ke MySQL

## Permasalahan

Saat aplikasi dijalankan muncul error:

```text
Database file at path [laravel] does not exist.
Ensure this is an absolute path to the database.
```

## Analisis

AI mengidentifikasi bahwa Laravel masih menggunakan konfigurasi SQLite, sedangkan database yang digunakan telah dipindahkan ke MySQL.

## Tindakan

Mengubah konfigurasi database pada file `.env`:


## Hasil

Laravel berhasil terhubung dengan database MySQL tanpa error.

---

# 3. Migrasi dan Seeder Database

## Tujuan

Membuat struktur tabel yang diperlukan oleh Membership Service.

## Aktivitas

Menjalankan migrasi Laravel untuk membuat tabel:

* users
* cache
* jobs
* members
* vouchers
* member_vouchers

Selanjutnya menjalankan proses seeding untuk mengisi data awal.

## Hasil

Seluruh tabel berhasil dibuat dan database siap digunakan.

---

# 4. Implementasi GraphQL

## Tujuan

Menambahkan GraphQL sebagai alternatif akses data selain REST API.

## Aktivitas Bersama AI

1. Analisis kebutuhan GraphQL.
2. Instalasi package Lighthouse GraphQL.
3. Konfigurasi schema GraphQL.
4. Pembuatan query untuk data Member dan Voucher.
5. Pengujian melalui GraphQL Playground.

## Contoh Query

```graphql
query {
  members {
    id
    name
    membership_type
    discount_percentage
  }
}
```

## Hasil

GraphQL berhasil diintegrasikan dan mampu mengakses data Membership Service dengan baik.

---

# 5. Analisis Struktur Project untuk Docker

## Tujuan

Memastikan project telah mendukung deployment menggunakan Docker.

## Aktivitas

Melakukan pemeriksaan struktur project:

```text
vendor/laravel/sail
```

serta memverifikasi keberadaan file:

```text
compose.yaml
```

## Hasil

Project telah menggunakan Laravel Sail sehingga deployment dapat dilakukan menggunakan Docker Compose tanpa perlu membuat Dockerfile baru.

---

# 6. Verifikasi Docker Desktop

## Aktivitas

Menjalankan perintah:

```bash
docker ps
```

## Hasil

Docker Desktop terdeteksi berjalan normal dan siap digunakan untuk deployment.

---

# 7. Troubleshooting Laravel Sail

## Permasalahan

Saat menjalankan:

```bash
vendor/bin/sail up -d
```

muncul error:

```text
execvpe(/bin/bash) failed: No such file or directory
```

## Analisis

Laravel Sail memerlukan lingkungan Linux melalui WSL (Windows Subsystem for Linux). Sistem hanya memiliki distribusi:

```text
docker-desktop
```

tanpa distribusi Linux seperti Ubuntu.

## Solusi

Menggunakan Docker Compose secara langsung:

```bash
docker compose up -d
```

---

# 8. Troubleshooting Build Docker

## Permasalahan

Saat build image Docker muncul error:

```text
groupadd: invalid group ID 'sail'
```

## Analisis

Variabel environment yang dibutuhkan Laravel Sail belum tersedia:

```env
WWWUSER
WWWGROUP
```

## Solusi

Menambahkan konfigurasi berikut ke file `.env`:

```env
WWWUSER=1000
WWWGROUP=1000
APP_PORT=8000
```

Kemudian melakukan rebuild:

```bash
docker compose build --no-cache
```

## Hasil

Build image berhasil diselesaikan:

```text
Image sail-8.5/app Built
```

---

# 9. Deployment Aplikasi Menggunakan Docker

## Aktivitas

Menjalankan:

```bash
docker compose up -d
```

## Hasil

Container berhasil dibuat:

```text
dpark-membership-laravel.test-1
dpark-membership-mysql-1
```

Verifikasi menggunakan:

```bash
docker ps
```

menunjukkan seluruh container berstatus:

```text
Up
```

---

# 10. Migrasi Database di Dalam Container

## Aktivitas

Menjalankan:

```bash
docker compose exec laravel.test php artisan migrate
```

## Hasil

Migrasi berhasil dijalankan pada database MySQL di dalam container Docker.

Tabel yang berhasil dibuat:

* users
* cache
* jobs
* members
* vouchers
* member_vouchers

---

# 11. Pengujian Endpoint API

## Endpoint

```http
GET /api/v1/members
```

## Pengujian

```bash
curl http://localhost:8000/api/v1/members
```

## Hasil

```json
{
  "success": true,
  "message": "Daftar seluruh member berhasil diambil.",
  "data": []
}
```

## Analisis

* API berhasil diakses.
* Routing berjalan normal.
* Koneksi database berhasil.
* Tabel berhasil dibaca.
* Belum terdapat data member sehingga array masih kosong.

---

# Kesimpulan

Pemanfaatan AI pada proyek ini membantu proses pengembangan dan deployment melalui:

1. Analisis error Laravel.
2. Migrasi database dari SQLite ke MySQL.
3. Implementasi GraphQL menggunakan Lighthouse.
4. Perancangan deployment Docker.
5. Troubleshooting Laravel Sail dan Docker Compose.
6. Validasi endpoint REST API.
7. Verifikasi konektivitas database.
8. Pengujian aplikasi setelah deployment.

## Hasil Akhir

Deployment DPark Membership Service berhasil dilakukan menggunakan Docker Desktop dengan konfigurasi Laravel Sail dan MySQL.

Aplikasi dapat diakses melalui:

```text
http://localhost:8000/api/v1/members
```

Status implementasi:

**BERHASIL**
