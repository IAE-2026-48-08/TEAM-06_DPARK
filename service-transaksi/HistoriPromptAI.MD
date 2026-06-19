AI Prompting Log — Tugas 2 IAE
BBK2HAB3 - Integrasi Aplikasi Enterprise

1. MySQL Tidak Bisa Connect di Docker
Prompt:
Kenapa Laravel terus error SQLSTATE[HY000] [2002] Connection refused padahal config .env sudah bener dan container MySQL-nya udah jalan?
Hasil / Insight:
Ternyata container Laravel-nya start duluan sebelum MySQL beneran siap nerima koneksi. Solusinya tambahin healthcheck di service db biar app nunggu sampai MySQL benar-benar ready, baru deh container app ikut jalan.

2. php artisan migrate Gagal Terus
Prompt:
Udah coba jalanin docker exec app php artisan migrate tapi tetap muncul Connection refused, padahal container db-nya statusnya running. Gimana caranya?
Hasil / Insight:
Status 'running' di Docker itu beda sama 'siap nerima koneksi'. Harus pakai kondisi service_healthy di depends_on supaya Laravel beneran nunggu MySQL-nya ready, bukan cuma nungguin containernya nyala.

3. Pindah ke SQLite Biar Lebih Simple
Prompt:
Capek debug MySQL terus, bisa gak saya ganti pakai SQLite aja? Apa yang perlu diubah di .env sama Dockerfile-nya?
Hasil / Insight:
Bisa banget. Ganti DB_CONNECTION ke sqlite, terus DB_DATABASE ke path file .sqlite-nya. Di Dockerfile hapus extension pdo_mysql, ganti ke pdo sama pdo_sqlite. Service db di docker-compose.yml juga gak perlu lagi, bisa dihapus sekalian.

4. Docker Daemon Tidak Bisa Diakses
Prompt:
Tiba-tiba muncul error: failed to connect to the docker API at npipe:////./pipe/dockerDesktopLinuxEngine. Padahal kemarin masih bisa. Ini kenapa?
Hasil / Insight:
Docker Desktop-nya belum kebuka atau daemon-nya belum aktif. Tinggal buka Docker Desktop, tunggu sampai icon di taskbar berhenti loading. Kalau icon-nya udah diam, baru coba lagi.

5. Build Gagal karena Konflik Package adduser
Prompt:
Waktu build image muncul error: dpkg: error processing package adduser -- end of file on stdin at conffile prompt. Build-nya berhenti di tengah. Gimana fixnya?
Hasil / Insight:
apt-get-nya minta konfirmasi manual pas install package, tapi di Docker gak ada yang bisa jawab. Solusinya tambahin DEBIAN_FRONTEND=noninteractive sama flag --no-install-recommends di Dockerfile biar proses installnya otomatis tanpa nanya-nanya dulu.

6. Build Error write metadata_v2.db: input/output error
Prompt:
Build Docker tiba-tiba error terus dengan pesan write metadata_v2.db: input/output error, padahal kodenya sama kayak sebelumnya. Ini kenapa bisa terjadi?
Hasil / Insight:
Bukan error di kode, tapi storage Docker-nya udah penuh kegencet cache image lama yang numpuk. Harus bersihin dulu pakai docker system prune -a --volumes -f, terus restart Docker Desktop-nya, baru build ulang.

7. Disk Penuh karena Numpuk Image Docker
Prompt:
Tiap build ulang, storage laptop makin berkurang padahal project-nya kecil. Kok bisa? Gimana cara bersihinnya?
Hasil / Insight:
Setiap kali build, Docker nyimpen layer image baru tapi yang lama gak otomatis keapus. Bisa bersihin pakai docker system prune -a -f buat hapus semua image nganggur, docker builder prune -f buat bersihin build cache, sama docker volume prune -f buat hapus volume yang udah gak kepake.

8. Endpoint GET /api/v1/transactions Tidak Ditemukan
Prompt:
Udah bikin route di routes/api.php tapi waktu diakses lewat browser muncul 'The route api/v1/transactions could not be found.' Padahal route-nya udah ditulis bener.
Hasil / Insight:
Di Laravel 11 file routes/api.php itu gak otomatis ke-load, beda sama versi sebelumnya. Harus didaftarin manual di bootstrap/app.php lewat withRouting() dengan ngasih path ke file api.php-nya. Setelah itu route-nya langsung ke-detect.

9. Response Endpoint Tidak Sesuai Format Kontrak
Prompt:
Endpoint GET /api/v1/transactions saya berhasil jalan tapi response-nya cuma ngeluarin array data mentah aja. Katanya harus dibungkus format tertentu sesuai standar kontrak, gimana caranya?
Hasil / Insight:
Semua response harus dibungkus wrapper JSON yang konsisten dengan field status, message, data, dan meta berisi service_name sama api_version. Ini biar service lain gampang parsing-nya. Jadi di controller return-nya harus pakai struktur itu, bukan cuma langsung return data-nya doang.

10. Middleware API Key Tidak Terbaca
Prompt:
Udah bikin ApiKeyMiddleware.php dan daftarin di bootstrap/app.php, tapi waktu endpoint diakses malah muncul error 'Target class App\Http\Middleware\ApiKeyMiddleware does not exist'. Gimana solusinya?
Hasil / Insight:
File middleware-nya kemungkinan belum beneran terbuat atau namespace-nya salah. Paling gampang buat ulang pakai php artisan make:middleware ApiKeyMiddleware biar struktur dan namespace-nya otomatis bener, terus isi logika validasi X-IAE-KEY-nya di method handle().

11. Cara Bikin Endpoint POST untuk Tambah Transaksi
Prompt:
Gimana cara bikin endpoint POST /api/v1/transactions yang bisa nerima data dari body request, terus validasi inputnya, dan simpan ke database? Kalau validasi gagal harus gimana response-nya?
Hasil / Insight:
Pakai Validator::make() di controller buat validasi field yang masuk. Kalau gagal return response JSON dengan status error dan errors berisi detail field yang bermasalah, HTTP 422. Kalau lolos validasi, simpan ke database pakai Transaction::create() dan return response dengan status success, HTTP 201.

12. Endpoint PUT untuk Update Transaksi Keluar
Prompt:
Gimana logika perhitungan biaya parkir di endpoint PUT /api/v1/transactions/{id}? Terus gimana cara ngitung durasi dari entry_time sampai exit_time-nya?
Hasil / Insight:
Pakai Carbon::parse() buat konversi entry_time ke object Carbon, terus diffInHours() buat ngitung selisih jamnya. Tarif beda tergantung vehicle_type, misal mobil 5000/jam dan motor 2000/jam. Total biaya dihitung dari durasi dikali tarif dikurangi discount kalau ada, minimum 0.

13. Swagger UI Tidak Muncul Endpoint Sama Sekali
Prompt:
Udah install L5-Swagger dan jalanin php artisan l5-swagger:generate, tapi waktu buka /api/documentation cuma muncul halaman kosong atau warning 'Required @OA\PathItem() not found'. Kenapa?
Hasil / Insight:
Ini biasanya karena mixing style anotasi. Kalau AppServiceProvider pakai PHP Attributes style (#[OA\...]), maka controller juga harus pakai style yang sama, gak bisa campur sama docblock style (/** @OA\... */). Harus konsisten salah satu.

14. Cara Proteksi Endpoint Swagger dengan API Key
Prompt:
Gimana caranya supaya di Swagger UI ada tombol Authorize buat masukin API Key sebelum nyoba endpoint? Sekarang muncul tapi pas klik Execute tetap 401.
Hasil / Insight:
Harus definisiin SecurityScheme dulu dengan type apiKey, in header, dan name X-IAE-KEY. Terus tiap endpoint dikasih anotasi security yang ngarah ke scheme itu. Nama scheme harus konsisten persis sama di SecurityScheme dan di tiap endpoint, termasuk huruf besar kecilnya.

15. Install Lighthouse GraphQL Gagal di Docker
Prompt:
Waktu jalanin composer require nuwave/lighthouse muncul error soal versi PHP tidak kompatibel. Lighthouse butuh PHP berapa dan gimana cara fixnya?
Hasil / Insight:
Lighthouse versi terbaru butuh PHP 8.1 ke atas. Kalau Dockerfile masih pakai php:8.2-cli seharusnya cukup, tapi kalau pakai Laravel 11 ada kemungkinan perlu php:8.3-cli. Tinggal ganti base image di Dockerfile dan build ulang.

16. Cara Bikin Skema GraphQL untuk Model Transaction
Prompt:
Gimana cara nulis skema GraphQL yang bener untuk model Transaction di Lighthouse? Terus bedanya type sama input itu apa, dan kapan masing-masing dipakenya?
Hasil / Insight:
type dipakai buat mendefinisikan struktur data yang dikembalikan, sedangkan input khusus buat parameter yang dikirim di mutation. Buat query cukup extend type Query dengan directive @all buat ambil semua data atau @find buat ambil by ID. Enum bisa dipakai buat field yang nilainya terbatas kayak vehicle_type atau status.

17. GraphQL Error __PHP_Incomplete_Class di Lighthouse
Prompt:
Waktu buka GraphQL Playground muncul error: Nuwave\Lighthouse\Cache\QueryCache fromStoreOrParse(): Return value must be of type DocumentNode, __PHP_Incomplete_Class returned. Ini kenapa?
Hasil / Insight:
Ini masalah cache Lighthouse yang corrupt atau stale, biasanya terjadi setelah rebuild Docker atau update dependency. Solusinya clear semua cache dulu pakai php artisan cache:clear dan php artisan lighthouse:clear-cache, atau hapus manual isi folder storage/framework/cache/data/.

18. GraphQL Query Tidak Bisa Akses Data
Prompt:
Udah setup Lighthouse dan skema GraphQL-nya, tapi waktu coba query di Playground muncul null atau error 'field transactions does not exist'. Padahal di skema udah ditulis.
Hasil / Insight:
Kemungkinan file skema belum ke-import dengan benar di schema.graphql utama. Harus tambahin #import models/transaction.graphql di file schema.graphql. Selain itu pastiin nama model di directive @all atau @find sesuai persis sama nama class Eloquent model-nya.

19. Cara Jalankan Seeder di Docker
Prompt:
Gimana cara jalanin seeder buat ngisi data dummy ke database SQLite setelah container jalan? Perintahnya lewat docker exec atau langsung dari dalam container?
Hasil / Insight:
Bisa pakai docker exec dpark_app php artisan db:seed dari luar container, atau masuk dulu ke dalam container pakai docker exec -it dpark_app bash lalu jalanin php artisan db:seed langsung. Pastiin file database.sqlite-nya udah ada dan migration sudah dijalanin duluan sebelum seed.

20. Cara Clear Cache Lighthouse di Dalam Docker
Prompt:
Mau clear cache Lighthouse tapi containernya jalan di Docker. Perintah php artisan harus dijalanin dari mana dan gimana caranya?
Hasil / Insight:
Pakai docker exec diikuti nama container dan perintah artisan-nya. Contoh: docker exec dpark_app php artisan cache:clear dan docker exec dpark_app php artisan lighthouse:clear-cache. Kalau mau hapus manual bisa pakai docker exec dpark_app rm -rf /var/www/storage/framework/cache/data/.