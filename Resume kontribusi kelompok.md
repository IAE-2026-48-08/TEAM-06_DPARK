Resume Kontribusi Kelompok — TEAM-06 DPark Bandung

<img width="1918" height="911" alt="image" src="https://github.com/user-attachments/assets/10bdbd3c-2bba-4008-a8b8-13ad96dcdffd" />


NamaNIMServiceGitHub UsernameAvifta Yulya Rismawan102022430031Service B — Transaksi ParkiravifyulyaArrival Sudrajat102022400119Service C — Membership/VoucherArrivalSudrajatRafly Rheinzeda102022400347Service A — Lahan & Lokasi—


1. Avifta Yulya Rismawan — Service B (Transaksi Parkir)

Ringkasan Tanggung Jawab

Bertanggung jawab penuh atas Service B, termasuk seluruh integrasi ke Service A & C dan ke Central Infrastructure dosen. Untuk konteks Tugas Besar (merger 3 service), Avifta juga mengerjakan sebagian besar penggabungan & pengujian end-to-end (Gateway, docker-compose gabungan, dan validasi alur Gateway → Transaksi → Lokasi → Membership).

Detail Modul yang Dibangun


Core Business Logic (TransactionController.php): endpoint index, show, store, update untuk siklus hidup transaksi parkir; hitung tarif otomatis berdasarkan durasi & jenis kendaraan dikurangi diskon membership.
Integrasi Antar Service: callMembershipVerification() ke Service C dan callLokasiCheckIn() ke Service A, dengan fault tolerance (try-catch agar transaksi tidak gagal total jika service lain down).
Modul 1 — Federated SSO: login M2M ke SSO Dosen, decode JWT, mapping team_id & processed_by_nim ke tabel lokal.
Modul 2 — SOAP XML Client (SoapAuditService.php): transformasi JSON → SOAP Envelope XML, kirim ke endpoint dosen, simpan ReceiptNumber.
Modul 3 — AMQP Publisher (RabbitMQPublisher.php): publish event transaction.completed ke RabbitMQ Dosen, dijalankan berurutan setelah SOAP Audit sukses.
Infrastruktur & Integrasi Docker: Dockerfile, docker-compose.yml gabungan untuk seluruh service + gateway, troubleshooting storage Docker penuh & error transaksi lintas-service saat proses integrasi.
Dokumentasi: analisis_tugas_3.md, HistoriPromptAI.MD.


Bukti Commit

History: https://github.com/IAE-2026-48-08/TEAM-06_DPARK/commits/main/service-transaksi?author=avifyulya

TanggalCommitHash2026-06-20feat: integrasi end-to-end service-transaksi, service-lokasi, service-membership + nginx gatewayca7ea812026-06-19fix: include service-transaksi files (remove nested git)6e33d712026-06-19init: struktur repo gabungan + service-transaksi7159c1c


2. Arrival Sudrajat — Service C (Membership / Voucher)

Ringkasan Tanggung Jawab

Kontribusi Arrival terbatas pada perbaikan satu isu teknis: bug lokasi_check_in yang sebelumnya mengembalikan success: false saat alur transaksi end-to-end dites (Gateway, Service Lokasi, Transaksi, dan Membership sudah berjalan normal sebelum perbaikan ini).

Detail Pekerjaan


Memperbaiki kegagalan pada panggilan check-in dari Service B ke Service A (lokasi_check_in: success: false) sehingga alur end-to-end checkout (Gateway → Transaksi → Lokasi → Membership) bisa lengkap tervalidasi.


Bukti Commit

TanggalCommitHash2026-06-21update dan perbaikan pada kodeedc72dd


3. Rafly Rheinzeda — Service A (Lahan & Lokasi)

Ringkasan Tanggung Jawab

Tidak ada kontribusi kode dari Rafly pada repository ini hingga resume ini dibuat.

Detail Pekerjaan

(tidak ada)

Bukti Commit

(tidak ditemukan commit atas nama Rafly di repository)
