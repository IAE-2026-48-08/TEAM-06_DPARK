# Analisis Tugas 3

## 1. Transaksi yang Dianggap Penting

Transaksi yang saya pilih adalah proses **checkout/selesai parkir** di endpoint 
`PUT /api/v1/transactions/{id}` (bagian Service A: Lahan & Lokasi di sistem DiPark).

Saya anggap ini transaksi penting karena:

- Statusnya berubah permanen dari `ongoing` jadi `completed`, dan ini gak bisa 
  diulang-ulang karena nanti waktu keluar (exit_time) sama biayanya bisa berubah 
  terus padahal harusnya sudah final.
- Ada hitungan biaya/uang di dalamnya, jadi kalau salah, bisa berdampak ke 
  tagihan user.
- Karena penting, transaksi ini saya kirim ke sistem audit SOAP biar tercatat 
  resmi, dan saya broadcast juga ke RabbitMQ biar bagian lain (misal laporan 
  keuangan) bisa tahu transaksi sudah selesai.

## 2. Siapa yang Terlibat

- **Petugas** — yang manggil API lewat header `X-IAE-KEY`
- **Transaction Service** — Laravel saya, yang ngatur data transaksi dan 
  hubungin ke SSO, SOAP, RabbitMQ dosen
- **Database** — nyimpen data transaksi termasuk `receipt_number`

## 3. Modul 1 — SSO

Sebelum kirim ke SOAP/RabbitMQ, sistem saya minta token dulu ke SSO dosen, 
pakai:
api_key: KEY-MHS-61
nim: 102022430031
Token yang didapat dipakai sebagai Bearer Token buat akses SOAP dan RabbitMQ. 
Token ini berlaku 1 jam, kalau expired harus minta ulang.

## 4. Modul 2 — SOAP

Data transaksi yang awalnya JSON, saya ubah jadi format XML (SOAP Envelope) 
sesuai format yang dosen tentukan, isinya `TeamID`, `ActivityName`, dan 
`LogContent`. Dikirim ke: POST https://iae-sso.virtualfri.id/soap/v1/audit

Kalau berhasil, balasannya kasih `ReceiptNumber` (contoh: `IAE-LOG-2026-24C4B692`), 
yang saya simpan di kolom `receipt_number` di tabel transaksi.

## 5. Modul 3 — RabbitMQ

Setelah SOAP berhasil, sistem saya kirim event `transaction.completed` ke 
RabbitMQ dosen lewat: POST https://iae-sso.virtualfri.id/api/v1/messages/publish

Event ini sudah muncul di papan pengumuman (`/board`) dosen dengan nama tim 
`TEAM-06`, jadi terbukti pesannya beneran sampai ke broker.

## 6. Bukti yang Saya Dapat

- Response SOAP berhasil, dapat `ReceiptNumber: IAE-LOG-2026-24C4B692`
- Response RabbitMQ berhasil: `{"status":"success",...}`
- Pesan saya muncul di board dosen, datanya cocok (TEAM-06, transaction_id 3, receipt_number sama)
- Data tersimpan di database lokal, kolom `receipt_number` sudah terisi
