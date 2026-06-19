# Log Pelaksanaan Prompt Engineering — Integrasi Aplikasi Enterprise (Tugas 3)

Dokumen ini merupakan catatan teknis (*engineering log*) yang mendokumentasikan tahapan interaksi, perancangan, dan instruksi algoritmik (*prompting*) yang diterapkan dalam pengembangan Modul 5 (SSO, SOAP, dan RabbitMQ) pada sistem DPark Membership.

---

## 1. Teknik Prompt Engineering yang Diterapkan

Pengembangan dilakukan secara bertahap (*iterative development*) menggunakan beberapa pendekatan *Prompt Engineering* berstandar industri:

1. **Contextual & System-Level Prompting**: Memberikan batasan konteks arsitektur di awal (menggunakan framework Laravel 12) untuk memastikan *output* kode sesuai dengan pola MVC dan standar PSR.
2. **Chain-of-Thought (CoT) Prompting**: Memecah masalah integrasi yang kompleks (seperti Federated SSO) menjadi beberapa sub-tugas logis: pengunduhan JWKS, ekstraksi payload JWT, dan pemetaan *Role* ke database lokal.
3. **Role-Playing Prompting**: Menempatkan AI sebagai *Backend & Integration Engineer* yang harus mematuhi spesifikasi *legacy system* (SOAP XML) dan sistem asinkron terpusat (RabbitMQ Cloud Dosen).
4. **Iterative Debugging & Refinement**: Melakukan *refactoring* kode secara langsung saat menemukan *edge cases*, seperti penyesuaian kredensial `KEY-MHS-169` dan `warga31@ktp.iae.id`.

---

## 2. Log Eksekusi & Tahapan Instruksi (Prompt Phases)

### Fase 1: Pemetaan Arsitektur & Analisis Kebutuhan
- **Fokus Instruksi**: Menganalisis struktur *database* yang sudah ada (Model `Member`, `Voucher`) dan mendesain titik integrasi untuk layanan eksternal.
- **Hasil Sintesis**: Sistem mengidentifikasi bahwa endpoint `verifyMembership` merupakan *State-Changing Transaction* yang paling kritis, sehingga seluruh fitur integrasi (SSO, SOAP, RabbitMQ) dipusatkan pada alur ini.

### Fase 2: Implementasi Autentikasi Federated (SSO & JWKS)
- **Fokus Instruksi**: Merancang mekanisme *login* M2M (Machine-to-Machine) dan decoding JWT berbasis RS256 tanpa melakukan *hardcoding* pada Public Key.
- **Hasil Sintesis**: 
  - Pembuatan `SsoService.php` untuk mengambil *keyset* secara dinamis dari endpoint `/api/v1/auth/jwks`.
  - Implementasi fungsi `verifyAndDecodeJwt()` menggunakan pustaka *firebase/php-jwt*.
  - Pembuatan skema tabel `local_roles` untuk pemetaan pengguna dari sistem terpusat ke sistem lokal.

### Fase 3: Integrasi Sistem Audit Legacy (SOAP Client)
- **Fokus Instruksi**: Membangun *client* SOAP yang efisien menggunakan HTTP request biasa (raw XML Envelope) untuk menghindari *overhead* dari ekstensi bawaan `SoapClient` milik PHP.
- **Hasil Sintesis**: Pembuatan `SoapAuditService.php` yang mampu membungkus payload `ReceiptNumber` dan mengirimkannya bersamaan dengan Bearer Token SSO secara aman.

### Fase 4: Implementasi Komunikasi Asinkron (AMQP / RabbitMQ)
- **Fokus Instruksi**: Membuat modul *publisher* untuk mengirimkan notifikasi aktivitas ke *exchange* `iae.central.exchange` dengan metode *REST API Publisher*.
- **Hasil Sintesis**: Pembuatan `AmqpPublisherService.php` yang dieksekusi secara *non-blocking* setelah transaksi verifikasi *membership* dinyatakan sukses.

### Fase 5: Finalisasi, Konfigurasi Kredensial, & Pengujian
- **Fokus Instruksi**: Menyesuaikan seluruh kredensial *environment* ke entitas spesifik milik pengguna (API Key: `KEY-MHS-169` dan subjek: `warga31@ktp.iae.id`), serta membersihkan kode *testing* sementara.
- **Hasil Sintesis**: 
  - Penyesuaian `MemberController.php` dan `SsoService.php`.
  - Simulasi *push event* langsung ke RabbitMQ untuk memastikan pemetaan data sudah terbaca valid di dalam *dashboard* Cloud Dosen.
  - Pembersihan (*cleanup*) berkas-berkas pengujian untuk memastikan *repository* bersih.

---

## 3. Hasil Akhir (Deliverables)

Seluruh instruksi telah berhasil dikonversi menjadi *source code* modular yang diletakkan pada direktori standar aplikasi Laravel:
- **Controllers**: `MemberController.php`, `SsoController.php`
- **Services**: `SsoService.php`, `SoapAuditService.php`, `AmqpPublisherService.php`
- **Middleware**: `SsoAuthMiddleware.php`
- **Database Mapping**: `LocalRole.php` beserta *Migration Schema*.

Sistem kini sepenuhnya *compliant* dengan rubrikasi Tugas 3 Integrasi Aplikasi Enterprise.
