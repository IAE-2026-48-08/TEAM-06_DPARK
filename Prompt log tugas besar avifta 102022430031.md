AI Prompting Log — Tugas Besar (Merger Service) IAE
BBK2HAB3 - Integrasi Aplikasi Enterprise

1. Storage Docker Penuh Saat Build Ulang 3 Service Sekaligus
   <img width="1891" height="887" alt="image" src="https://github.com/user-attachments/assets/84a52c1d-caea-4630-bafe-37a3d952df97" />
   <img width="1918" height="1007" alt="image" src="https://github.com/user-attachments/assets/d3d57167-1763-4407-9afd-0b19ea4fcbd0" />
Prompt:
Build docker-compose buat 3 service sekaligus (transaksi, lokasi, membership) tiba-tiba gagal terus, muncul error storage/disk penuh padahal sebelumnya pas service sendiri-sendiri lancar. Kenapa pas digabung jadi error gini?
Hasil / Insight:
Karena sekarang ada 3 image Laravel + 3 image MySQL yang dibuild & disimpan barengan, jadi kebutuhan storage-nya jauh lebih besar dibanding pas masing-masing service jalan sendiri. Layer image lama dari proses build sebelumnya juga numpuk dan gak otomatis terhapus. Solusinya bersihin dulu pakai `docker system prune -a --volumes -f` dan `docker builder prune -f` sebelum build ulang, baru jalankan `docker compose up --build` lagi.

2. Transaksi Gagal (500) Saat Service B Connect ke Service A/C
Prompt:
Pas testing alur end-to-end, request bikin transaksi baru kadang balik error 500 atau timeout, kelihatannya pas Service B coba manggil Service A (lokasi) atau Service C (membership) di dalam container. Padahal kalau dites satu-satu (curl langsung ke masing-masing service) hasilnya normal. Kenapa bisa gagal pas dipanggil dari service lain?
Hasil / Insight:
Masalahnya di koneksi antar container pas semua service baru saja di-build ulang bareng — container Service A/C kadang belum benar-benar "ready" nerima request padahal status di Docker udah "running", jadi Service B yang manggil lebih cepat bakal connection refused/timeout. Selain itu juga dicek ulang apakah `SERVICE_LOKASI_URL` dan `SERVICE_MEMBERSHIP_URL` di environment Service B udah mengarah ke nama container yang benar sesuai `docker-compose.yml` (bukan ke `localhost`), karena komunikasi antar container di Docker network harus pakai nama service, bukan localhost.

3. Menentukan Penyebab lokasi_check_in success: false
Prompt:
Endpoint transaksi udah berhasil dibuat (201), membership check juga jalan, tapi field lokasi_check_in di response selalu success: false. Service lokasi-nya sendiri kalau dicek via GET /api/v1/locations datanya ada dan normal. Apa yang perlu dicek buat nemu sumber masalahnya?
Hasil / Insight:
Karena Service B nyimpen transaksi tanpa validasi dulu apakah location_id yang dikirim itu valid/ada slot kosong di Service A, jadi transaksi tetap kebuat (201) walau panggilan check-in ke Service A-nya sendiri ditolak (400/404) karena ID lokasi gak match atau slot kendaraan tipe itu udah penuh. Disarankan cek response body lengkap dari Service A (bukan cuma flag success-nya), dan pastikan data seed lokasi yang dipakai buat testing punya slot kosong untuk tipe kendaraan yang ditest.
