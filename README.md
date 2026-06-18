# AKHLAK360

Sistem Penilaian 360° Core Values AKHLAK PT Energi Nusantara

AKHLAK360 adalah aplikasi web berbasis Laravel untuk mengelola proses penilaian karyawan secara 360 derajat berdasarkan enam Core Values AKHLAK:

* Amanah
* Kompeten
* Harmonis
* Loyal
* Adaptif
* Kolaboratif

Sistem mendukung pengelolaan periode penilaian, penentuan assessor, pengisian assessment, perhitungan skor berbobot, gap analysis, Individual Development Plan, talent mapping, laporan, audit log, dan dashboard berbasis role.

---

## Spesifikasi & Teknologi

- PHP 8.2+
- Laravel 12
- Company SSO simulation authentication
- Laravel Blade
- AdminLTE
- Chart.js
- MySQL/MariaDB atau SQLite
- PHPUnit feature tests

---

## Tujuan Sistem

Aplikasi ini dibuat sebagai MVP akademik untuk membantu perusahaan menjalankan proses penilaian karyawan secara terstruktur melalui beberapa perspektif:

* Atasan
* Rekan sejawat
* Bawahan
* Diri sendiri

Bobot penilaian default:

| Jenis Penilai | Bobot |
| ------------- | ----: |
| Supervisor    |   40% |
| Peer          |   20% |
| Subordinate   |   30% |
| Self          |   10% |
| Total         |  100% |

---

## Fitur Utama

### Autentikasi

* Company SSO Simulation
* Login menggunakan email perusahaan atau nomor karyawan
* Auto-provisioning akun internal
* Role ditentukan otomatis
* Rate limiting
* Session regeneration
* Audit login
* Penolakan akses employee tidak aktif
* Tidak tersedia public register
* Tidak tersedia public password login
* Tidak tersedia forgot/reset password

### HRIS Simulation

* Import data employee melalui CSV
* Download template CSV
* Sinkronisasi employee
* Sinkronisasi department dan position
* Supervisor mapping
* Status aktif dan nonaktif
* Riwayat sinkronisasi
* Audit log proses import

### Assessment Cycle

* Periode penilaian setiap semester
* Durasi periode hingga 14 hari
* Threshold penilaian
* Bobot assessor per periode
* Aktivasi dan penutupan periode
* Monitoring progress assessment

### Penentuan Assessor

* Self assessment
* Supervisor assessment
* Peer assessment
* Subordinate assessment
* Peer proposal oleh Admin HR
* Persetujuan peer oleh Supervisor
* Pencegahan assignment duplikat

### Form Penilaian

* Enam Core Values AKHLAK
* Total 18 indikator
* Skala Likert 1–5
* Validasi seluruh indikator
* Pencegahan submit ulang
* Timestamp submission
* Audit trail

### Kalkulasi Hasil

* Rata-rata per jenis assessor
* Normalisasi bobot jika jenis assessor tidak tersedia
* Skor setiap Core Value
* Final score
* Self score
* Others score
* Gap analysis
* Threshold analysis
* Weakest Core Value
* IDP recommendation
* Talent mapping
* Recalculation idempotent

### Dashboard

Dashboard tersedia untuk:

* Admin HR
* Supervisor
* Employee
* Management
* IT Admin

Setiap dashboard menampilkan informasi dan fungsi sesuai kewenangan masing-masing role.

### Reports

* Filter periode
* Filter department
* Filter kategori
* Filter threshold
* Filter talent category
* Export CSV
* Export XLSX
* Export PDF
* Export history
* Audit log export

### Audit dan Compliance

* Audit login
* Audit assessment
* Audit assignment
* Audit peer approval
* Audit result calculation
* Audit HRIS sync
* Audit export
* Compliance monitoring
* Read-only technical monitoring

---

## Role dan Hak Akses

### Admin HR

Admin HR mengelola operasional utama sistem:

* Employee
* Department
* Position
* HRIS import
* Assessment period
* Assessment weights
* Peer proposal
* Assignment
* Result calculation
* Notification
* Reports
* Audit
* Compliance

### Supervisor

Supervisor dapat:

* Melihat direct reports
* Menyetujui atau menolak peer
* Mengisi assessment bawahan
* Melihat progress tim
* Melihat hasil agregat tim
* Melihat IDP bawahan langsung

### Employee

Employee dapat:

* Melihat task assessment
* Mengisi self assessment
* Mengisi peer assessment
* Mengisi subordinate assessment
* Melihat hasil pribadi
* Melihat gap analysis pribadi
* Melihat IDP pribadi
* Melihat profile HRIS

### Management

Management dapat:

* Melihat company analytics
* Melihat distribusi skor department
* Melihat semester trend
* Melihat gap analysis agregat
* Melihat talent mapping
* Melihat IDP summary
* Mengakses reports

Management tidak memiliki akses terhadap fungsi operasional HR.

### IT Admin

IT Admin dapat:

* Monitoring HRIS sync
* Monitoring audit log
* Monitoring export history
* Melihat system configuration
* Melihat compliance status
* Melihat environment information

IT Admin tidak memiliki akses terhadap hasil penilaian individual yang bersifat rahasia.

---

## Teknologi

* PHP 8.2+
* Laravel 12
* Blade
* AdminLTE
* SQLite
* Vite
* Chart.js
* Laravel Session Authentication
* PHPUnit

---

## Kebutuhan Sistem

Pastikan sudah tersedia:

* PHP 8.2 atau lebih baru
* Composer
* Node.js
* npm
* Git

Extension PHP yang dibutuhkan:

* OpenSSL
* PDO
* Mbstring
* Tokenizer
* XML
* Ctype
* JSON
* BCMath
* Fileinfo
* SQLite
* ZIP
---

## Identitas Demo SSO

Setiap karyawan menggunakan kode SSO personal. Role tidak dipilih pengguna dan selalu dihitung ulang dari data HRIS serta konfigurasi.

| Email / Nomor Pegawai | Kode SSO Personal | Role |
| --- | --- | --- |
| `admin_hr@example.com` / `EMP001` | `AKH-HR01-2026` | Admin HR |
| `management@example.com` / `EMP002` | `AKH-MGT2-2026` | Management |
| `it@example.com` / `EMP003` | `AKH-IT03-2026` | IT Admin |
| `supervisor@example.com` / `EN-0003` | `AKH-SPV3-2026` | Supervisor |
| `employee@example.com` / `EN-0005` | `AKH-EMP5-2026` | Employee |

Password acak atau password akun seed hanya menjadi atribut teknis database dan tidak dapat digunakan pada autentikasi publik.

## Instalasi

Clone repository:

```bash
git clone <repository-url>
cd akhlak360
```

Install dependency PHP:

```bash
composer install
```

Install dependency frontend:

```bash
npm install
```

Buat file environment:

```bash
copy .env.example .env
```

Untuk Linux atau macOS:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

Buat file database SQLite:

Windows PowerShell:

```powershell
New-Item database/database.sqlite -ItemType File
```

Linux atau macOS:

```bash
touch database/database.sqlite
```

Pastikan konfigurasi database di `.env`:

```env
DB_CONNECTION=sqlite
```

Jalankan migration dan seeder:

```bash
php artisan migrate:fresh --seed
```

Build asset:

```bash
npm run build
```

Jalankan aplikasi:

```bash
php artisan serve
```

Aplikasi dapat diakses melalui:

```text
http://127.0.0.1:8000/sso/login
```

Route `/` dan `/login` mengarahkan guest ke `/sso/login`. Form login password dan pemulihan password tidak tersedia untuk publik.

Tambahkan konfigurasi berikut ke `.env`:

```env
ADMIN_HR_EMPLOYEE_NUMBERS=EMP001
MANAGEMENT_EMPLOYEE_NUMBERS=EMP002
IT_ADMIN_EMPLOYEE_NUMBERS=EMP003
```

Role dihitung ulang pada setiap login berdasarkan daftar Admin HR, Management, IT Admin, keberadaan bawahan langsung, lalu fallback Employee. Identitas harus cocok dengan pegawai HRIS aktif dan kode personal yang tersimpan dalam bentuk hash. Pegawai tanpa user dibuatkan user otomatis; pegawai tanpa email memakai alamat unik `nomorpegawai@internal.akhlak360.invalid`.

Admin HR dapat membuat atau mereset kode melalui **Master Data → Employees → tombol kunci**. Kode baru ditampilkan satu kali dan kode lama langsung tidak berlaku. Karyawan yang dibuat melalui form manual langsung memperoleh kode awal. Karyawan hasil import HRIS harus dibuatkan kode oleh Admin HR setelah import.

## Konfigurasi SSO Simulation

Tambahkan konfigurasi berikut pada `.env`:

```env
ADMIN_HR_EMPLOYEE_NUMBERS=EMP001
MANAGEMENT_EMPLOYEE_NUMBERS=EMP002
IT_ADMIN_EMPLOYEE_NUMBERS=EMP003
```

SSO Simulation menggunakan kode individual yang disimpan dalam bentuk hash pada employee record.

Public password login tidak dapat digunakan.

---

## Akun Demo

| Role       | Employee Number | Email                                                   | SSO Code      |
| ---------- | --------------- | ------------------------------------------------------- | ------------- |
| Admin HR   | EMP001          | [admin_hr@example.com](mailto:admin_hr@example.com)     | AKH-HR01-2026 |
| Supervisor | EN-0003         | [supervisor@example.com](mailto:supervisor@example.com) | AKH-SPV3-2026 |
| Employee   | EN-0005         | [employee@example.com](mailto:employee@example.com)     | AKH-EMP5-2026 |
| Management | EMP002          | [management@example.com](mailto:management@example.com) | AKH-MGT2-2026 |
| IT Admin   | EMP003          | [it@example.com](mailto:it@example.com)                 | AKH-IT03-2026 |

Kode di atas hanya digunakan untuk simulasi akademik dan tidak ditujukan untuk production.

---

## Alur Utama Sistem

```text
Admin HR mengelola data HRIS
        ↓
Admin HR membuat periode assessment
        ↓
Admin HR mengatur bobot assessor
        ↓
Admin HR menentukan peer
        ↓
Supervisor menyetujui peer
        ↓
Sistem membuat assessment assignment
        ↓
Employee, Supervisor, Peer, dan Subordinate mengisi assessment
        ↓
Sistem menghitung hasil penilaian
        ↓
Gap Analysis, IDP, dan Talent Mapping dihasilkan
        ↓
Management melihat analytics
        ↓
Admin HR atau Management mengekspor laporan
        ↓
IT Admin memonitor audit dan integrasi
```

---

## Struktur Penilaian

Setiap assessment terdiri dari 18 indikator.

| Core Value  | Jumlah Indikator |
| ----------- | ---------------: |
| Amanah      |                3 |
| Kompeten    |                3 |
| Harmonis    |                3 |
| Loyal       |                3 |
| Adaptif     |                3 |
| Kolaboratif |                3 |
| Total       |               18 |

Skala penilaian:

| Nilai | Interpretasi        |
| ----: | ------------------- |
|     1 | Sangat Tidak Sesuai |
|     2 | Tidak Sesuai        |
|     3 | Cukup Sesuai        |
|     4 | Sesuai              |
|     5 | Sangat Sesuai       |

---

## Menjalankan Pengujian

Bersihkan cache:

```bash
php artisan optimize:clear
```

Reset database:

```bash
php artisan migrate:fresh --seed
```

Jalankan seluruh test:

```bash
php artisan test
```



Lihat daftar route:

```bash
php artisan route:list --except-vendor
```

Build production asset:

```bash
npm run build
```

Hasil audit terakhir:

* 124 test passed
* 1.103 assertions passed
* 106 routes verified
* Vite production build passed
* Lima role berhasil diuji melalui SSO
* Unauthorized direct routes menghasilkan 403
* Tidak ditemukan browser console error
* Desktop dan laptop walkthrough berhasil

---

## Demo

Dokumentasi demo tersedia pada:

```text
DEMO_SCRIPT.md
docs/demo-rehearsal-report.md
docs/end-to-end-audit-2026-06-18.md
```

Urutan demo yang disarankan:

1. Login sebagai Admin HR
2. Tampilkan HRIS employee data
3. Tampilkan active assessment period
4. Tampilkan bobot 40/20/30/10
5. Tampilkan peer proposal
6. Login Supervisor dan approve peer
7. Isi satu assessment
8. Login Admin HR dan recalculate result
9. Tampilkan Gap Analysis
10. Tampilkan IDP
11. Tampilkan Talent Mapping
12. Login Management dan tampilkan analytics
13. Export report
14. Login IT Admin dan tampilkan audit trail

---

## Scheduler dan Reminder

Untuk menjalankan scheduler secara lokal:

```bash
php artisan schedule:work
```

Untuk menjalankan satu kali pengecekan scheduler:

```bash
php artisan schedule:run
```

Pada MVP lokal, pengiriman email menggunakan log driver.

Pastikan konfigurasi:

```env
MAIL_MAILER=log
```

Notification in-app tetap tersedia.

---

## Keamanan

Implementasi keamanan pada MVP:

* CSRF protection
* SSO-only public authentication
* Generic authentication error
* Rate limiting
* Session regeneration
* Role-based access control
* Direct-route authorization
* Active employee enforcement
* Read-only employee identity profile
* Audit logging
* Confidential assessor data protection
* Input validation
* Transaction rollback
* Duplicate assignment prevention
* Duplicate submission prevention

---

## Batasan MVP

Aplikasi ini merupakan MVP akademik.

Hal yang masih menggunakan simulasi:

* Company SSO
* HRIS integration
* Email delivery
* Scheduler infrastructure
* Production monitoring

Hal yang belum termasuk:

* OIDC atau SAML production
* Live HRIS API
* Production SMTP
* Deployment monitoring
* Load testing
* Uptime validation
* Payroll integration
* Remuneration integration
* Mobile application

---

## Catatan Production

Sebelum digunakan pada lingkungan production, diperlukan:

* Identity Provider perusahaan
* OIDC atau SAML
* API HRIS perusahaan
* PostgreSQL atau database production lainnya
* SMTP production
* Scheduler atau queue worker
* HTTPS
* Secure secret management
* Log monitoring
* Backup database
* Performance testing
* Security testing
* Disaster recovery plan

---

## Dokumentasi Audit

Audit end-to-end terakhir mencakup:

* SSO
* Role-based access
* Dashboard
* HRIS
* Assessment workflow
* Peer approval
* Assignment
* Submission
* Calculation
* Gap analysis
* IDP
* Talent mapping
* Reports
* Export
* Notification
* Audit log
* Compliance
* Desktop walkthrough
* Mobile review

## Kontributor

Project ini dikembangkan untuk kebutuhan tugas akademik Sistem Informasi.

Tambahkan nama anggota kelompok pada bagian berikut:

```text
1. Arwan Nugraha Rahmatullah NIM 102012330470
2. Annisa Dwi Nurul Humairah NIM 102012300085
3. Jonathan Edgar Barasa NIM 102012300075
```

---

## License

Project ini dibuat untuk kebutuhan akademik.

Penggunaan lebih lanjut harus mengikuti persetujuan pemilik project dan pihak terkait.
