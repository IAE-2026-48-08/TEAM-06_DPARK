# Dokumen Analisis Tugas 3 - Integrasi Aplikasi Enterprise

**Nama:** Ekky Novriza Alam  
**Kelas:** BBK2HAB3 - Integrasi Aplikasi Enterprise  
**Program Studi:** S1 Sistem Informasi  

---

## 1. Justifikasi Transaksi Kritis (State-Changing)

Dalam **DPark Membership Service (Service C)**, transaksi yang diidentifikasi sebagai transaksi kritis adalah **Verifikasi Membership (`verifyMembership`)** yang dipicu saat kendaraan hendak melakukan transaksi parkir.

### Mengapa Transaksi Ini Dinilai Kritis?
1. **Dampak Finansial Langsung (State-Changing Decision)**: Verifikasi membership menentukan apakah seorang pengguna berhak mendapatkan diskon tarif parkir (10% untuk reguler, 20% untuk premium, dan 30% untuk VIP). Kesalahan dalam penentuan status membership akan menyebabkan kesalahan perhitungan keuangan pada gate pembayaran (Service B).
2. **Kebutuhan Audit Kepatuhan (SOAP Audit)**: Setiap verifikasi yang berhasil harus dicatat secara kaku pada sistem audit pusat (Legacy SOAP) untuk memastikan bahwa diskon yang diberikan benar-benar sah, mencegah fraud (manipulasi status member), dan memiliki nomor bukti audit (`ReceiptNumber`) resmi dari Cloud Dosen.
3. **Penyebaran Data Real-Time (RabbitMQ Event)**: Aktivitas verifikasi membership harus disebarkan ke departemen lain (seperti analitik traffic dan manajemen gate parkir) secara asinkron agar tidak memblokir antrean fisik kendaraan di pintu masuk/keluar parkir.

---

## 2. Sequence Diagram Interaksi Layanan dengan Sistem Terpusat (SSO, SOAP, RabbitMQ)

Berikut adalah diagram urutan alur data internal saat request verifikasi membership masuk ke **Service C (Membership Service)**:

```mermaid
sequenceDiagram
    autonumber
    actor Pengguna as User / Gateway
    participant LocalApp as MemberController (Service C)
    participant LocalDB as Database Lokal
    participant SSO as Cloud SSO Dosen
    participant SOAP as SOAP Audit Service
    participant RabbitMQ as RabbitMQ Publisher

    Pengguna->>LocalApp: POST /api/v1/members/verification (vehicle_plate)
    LocalApp->>LocalDB: Query Member by vehicle_plate & Cek status aktif
    LocalDB-->>LocalApp: Member Data (ID, Name, Type, Discount)

    alt Member Aktif & Valid
        Note over LocalApp, SSO: [Lapis 1] Autentikasi Federated SSO
        LocalApp->>SSO: POST /api/v1/auth/token (api_key M2M)
        SSO-->>LocalApp: JWT Bearer Token

        Note over LocalApp, SOAP: [Lapis 2] Sinkronisasi SOAP Audit Legacy
        LocalApp->>SOAP: POST /soap/v1/audit (XML Envelope + JWT)
        SOAP-->>LocalApp: XML Response (Status: SUCCESS, ReceiptNumber)
        
        Note over LocalApp, RabbitMQ: [Lapis 3] Broadcast Event Asinkron
        LocalApp->>RabbitMQ: POST /api/v1/messages/publish (Event JSON + JWT)
        RabbitMQ-->>LocalApp: Publish Response (Success JSON)

        LocalApp-->>Pengguna: Return Response 200 (Member Valid + ReceiptNumber + Integrations Status)
    else Member Tidak Valid / Expired
        LocalApp-->>Pengguna: Return Response 200 (Success: false, Member invalid/tidak ditemukan)
    end
```

---

## 3. Desain SSO Mapping ke Database Lokal

Sistem keamanan menggunakan **Federated SSO** berbasis JWT (RS256). Ketika pengguna (misal: Warga/Dosen) melakukan login:
1. JWT didekode menggunakan Public Key (JWKS) dari endpoint SSO Dosen.
2. Field `sub` (SSO User ID), `email`, dan `roles` diekstrak dari payload JWT.
3. User dipetakan ke tabel lokal `local_roles` dengan aturan pemetaan:
   - Jika role SSO berisi `'admin'` atau `'dosen'` $\rightarrow$ Dipetakan sebagai `'admin'` di DPark.
   - Jika role SSO berisi `'operator'` $\rightarrow$ Dipetakan sebagai `'operator'` di DPark.
   - Selain itu $\rightarrow$ Dipetakan sebagai `'member'` (default).

Tabel skema lokal `local_roles`:
- `sso_sub`: string (unique)
- `email`: string
- `local_role`: enum ('admin', 'operator', 'member')
- `jwt_payload`: text
- `last_seen`: timestamp
