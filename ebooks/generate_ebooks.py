from __future__ import annotations

import base64
import html
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "ebooks"
LOGO = ROOT / "public" / "assets" / "logo.png"


def esc(text: str) -> str:
    return html.escape(text)


def page(number: int, eyebrow: str, title: str, content: str, *, cover: bool = False) -> str:
    cls = "page cover" if cover else "page"
    footer = "" if cover else (
        f'<footer><span>SIPAR · SMK NEGERI 5 SURAKARTA</span>'
        f'<span>{number:02d}</span></footer>'
    )
    return (
        f'<section class="{cls}">{content}'
        f'{footer}</section>'
    )


def bullet(title: str, body: str, tone: str = "blue") -> str:
    return (
        f'<article class="card {tone}"><span class="card-title">{title}</span>'
        f'<p>{body}</p></article>'
    )


def step(number: str, title: str, body: str) -> str:
    return (
        f'<div class="step"><span class="step-no">{number}</span>'
        f'<div><strong>{title}</strong><p>{body}</p></div></div>'
    )


def section_heading(eyebrow: str, title: str, lead: str = "") -> str:
    lead_html = f'<p class="lead">{lead}</p>' if lead else ""
    return f'<div class="section-head"><span class="eyebrow">{eyebrow}</span><h2>{title}</h2>{lead_html}</div>'


def build_indonesian(logo_data: str) -> list[str]:
    pages = []
    pages.append(page(1, "", "", f"""
        <div class="cover-top"><img src="{logo_data}" alt="Lambang SMK Negeri 5 Surakarta">
        <span>PANDUAN DIGITAL · EDISI 2026</span></div>
        <div class="cover-hero"><span class="cover-kicker">SISTEM INFORMASI PEMINJAMAN</span>
        <h1>SIPAR</h1><div class="cover-rule"></div>
        <h2>Panduan Penggunaan<br>Aplikasi Peminjaman Alat RPL</h2>
        <p>Alur peminjaman alat praktik yang tertib, transparan, dan mudah dipantau.</p></div>
        <div class="cover-bottom"><span>SMK NEGERI 5 SURAKARTA</span><span>REKAYASA PERANGKAT LUNAK</span></div>
        <div class="cover-orb orb-one"></div><div class="cover-orb orb-two"></div>
    """, cover=True))
    pages.append(page(2, "", "", f"""
        {section_heading("PETA BUKU", "Daftar Isi", "Panduan singkat untuk mengenal fitur, perangkat, dan alur kerja SIPAR.")}
        <div class="toc">
          <div><span>01</span><b>Pendahuluan</b><i>03</i></div>
          <div><span>02</span><b>Fitur Web SIPAR</b><i>04</i></div>
          <div><span>03</span><b>Alat dan Bahan</b><i>06</i></div>
          <div><span>04</span><b>Langkah Menggunakan Web</b><i>07</i></div>
          <div><span>05</span><b>Kesimpulan</b><i>09</i></div>
        </div>
        <div class="callout"><strong>Cara membaca</strong><p>Bagian penggunaan dibagi menurut peran: siswa, admin, dan teknisi. Ikuti langkah sesuai akses akun Anda.</p></div>
        <div class="mini-mark">S<span>/</span>P</div>
    """))
    pages.append(page(3, "", "", f"""
        {section_heading("01 · PENDAHULUAN", "Mengelola peminjaman dengan lebih tertib",
            "SIPAR adalah Sistem Informasi Peminjaman Alat RPL untuk mendukung pengelolaan alat praktik di SMK Negeri 5 Surakarta.")}
        <p class="body-copy">Peminjaman alat melibatkan banyak informasi: siapa peminjamnya, alat apa yang digunakan, kapan alat diambil, dan kapan alat harus kembali. Pencatatan yang tersebar dapat menyulitkan pemantauan stok serta riwayat transaksi.</p>
        <p class="body-copy">SIPAR menyatukan informasi tersebut dalam aplikasi web. Pengajuan, pemeriksaan, penyerahan, dan pengembalian tercatat dalam alur yang dapat ditelusuri oleh pengguna sesuai perannya.</p>
        <div class="quote"><span>“</span><p>Satu alur digital untuk menjaga alat praktik tetap terdata dan siap digunakan.</p></div>
        <div class="role-grid">
          {bullet("SISWA", "Memilih alat, mengirim pengajuan, lalu memantau status dan riwayat.", "blue")}
          {bullet("ADMIN", "Mengelola data inventaris dan memutuskan pengajuan.", "violet")}
          {bullet("TEKNISI", "Mencatat serah terima fisik dan memproses pengembalian.", "amber")}
        </div>
        <p class="small-note">Akses menu ditentukan oleh akun dan peran yang diberikan.</p>
    """))
    pages.append(page(4, "", "", f"""
        {section_heading("02 · FITUR WEB", "Menu siswa saat web dijalankan",
            "Siswa menggunakan empat menu utama. Pengajuan multi-alat tersedia dari alur Katalog Alat, bukan sebagai menu terpisah.")}
        <div class="feature-list">
          {bullet("01 · Dashboard", "Ringkasan alat tersedia, jumlah pengajuan, pengajuan yang menunggu, dan transaksi selesai.", "blue")}
          {bullet("02 · Katalog Alat", "Jelajahi dan cari alat berdasarkan nama atau kode, periksa ketersediaan, pilih beberapa jenis alat, lalu kirim satu pengajuan. Maksimal satu unit per jenis.", "violet")}
          {bullet("03 · Riwayat Peminjaman", "Lihat status dan detail pengajuan untuk mengikuti proses sampai transaksi selesai.", "green")}
          {bullet("04 · Profil Saya", "Lihat informasi akun dan kelola pengaturan profil serta kata sandi yang tersedia.", "amber")}
        </div>
        <div class="fact-strip"><b>Ketentuan pengajuan di katalog</b><span>Tanggal pinjam hari ini · durasi paling lama 5 hari · setiap pengajuan menunggu verifikasi admin</span></div>
    """))
    pages.append(page(5, "", "", f"""
        {section_heading("02 · FITUR WEB", "Kontrol inventaris dan serah terima",
            "Setiap peran melengkapi bagian alur yang berbeda sehingga catatan transaksi tetap tersambung.")}
        <div class="feature-list">
          {bullet("ADMIN · Data master", "Mengelola alat, kategori, dan akun siswa; meninjau lalu menyetujui atau menolak pengajuan.", "violet")}
          {bullet("ADMIN · Laporan", "Melihat rekap transaksi dan mengunduh laporan yang tersedia dalam format PDF atau Excel.", "blue")}
          {bullet("TEKNISI · Penyerahan", "Mencari transaksi dengan kode booking atau NIS, lalu mencatat Asset ID/serial number, kondisi, dan bukti foto.", "amber")}
          {bullet("TEKNISI · Pengembalian", "Mencari transaksi, mencatat kondisi item dan bukti foto, serta memproses pengembalian per item.", "green")}
        </div>
        <div class="flow"><span>AJUKAN</span><b>→</b><span>VERIFIKASI</span><b>→</b><span>SERAH TERIMA</span><b>→</b><span>KEMBALI</span></div>
        <p class="small-note">Stok diperbarui ketika pengajuan disetujui dan ketika pengembalian dicatat sesuai kondisi alat.</p>
    """))
    pages.append(page(6, "", "", f"""
        {section_heading("03 · ALAT DAN BAHAN", "Apa yang diperlukan?",
            "Kebutuhan berbeda untuk pengguna aplikasi dan tim yang memasang atau mengembangkan SIPAR.")}
        <h3 class="subhead">Untuk menggunakan SIPAR</h3>
        <div class="check-list"><p><b>01</b> Perangkat: komputer, laptop, tablet, atau ponsel.</p>
        <p><b>02</b> Browser modern dan koneksi jaringan yang dapat menjangkau aplikasi.</p>
        <p><b>03</b> Akun SIPAR aktif dengan peran siswa, admin, atau teknisi.</p>
        <p><b>04</b> Untuk proses teknisi: informasi Asset ID/serial number dan foto kondisi bila diminta.</p></div>
        <h3 class="subhead">Teknologi proyek</h3>
        <div class="tech-grid">
          {bullet("Laravel 12 + PHP 8.2+", "Backend aplikasi, routing, autentikasi, validasi, dan alur transaksi.", "blue")}
          {bullet("Blade + Bootstrap 5", "Template halaman web dan komponen antarmuka responsif.", "violet")}
          {bullet("MySQL", "Penyimpanan data pada deployment yang menggunakan layanan MySQL.", "green")}
          {bullet("Composer · Node.js/npm · Vite", "Manajemen dependency PHP dan proses aset frontend saat pengembangan/build.", "amber")}
          {bullet("Git + Railway", "Version control dan platform deployment aplikasi.", "blue")}
        </div>
        <p class="small-note">Pengembangan lokal memerlukan konfigurasi environment dan database yang sesuai proyek.</p>
    """))
    pages.append(page(7, "", "", f"""
        {section_heading("04 · LANGKAH PENGGUNAAN", "Siswa · ajukan peminjaman",
            "Siapkan alat yang dibutuhkan, tanggal penggunaan, dan alasan peminjaman sebelum memulai.")}
        <div class="steps">
          {step("1", "Masuk ke akun", "Buka alamat SIPAR melalui browser dan masuk menggunakan akun siswa.")}
          {step("2", "Temukan alat", "Dari Dashboard, buka Katalog Alat. Cari berdasarkan nama atau kode, lalu periksa ketersediaan.")}
          {step("3", "Pilih alat", "Tekan Pilih Alat pada setiap item yang diperlukan. Periksa daftar pilihan pada panel Pinjam.")}
          {step("4", "Isi formulir", "Tentukan tanggal pinjam (hari ini), rencana kembali (maksimal lima hari), dan tulis keperluan dengan jelas.")}
          {step("5", "Periksa dan ajukan", "Pastikan daftar alat tidak kosong. Tekan Ajukan Peminjaman, tinjau konfirmasi, lalu pilih Ya, Lanjutkan.")}
          {step("6", "Pantau status", "Buka Riwayat Peminjaman untuk melihat status. Pengajuan berstatus Pending menunggu pemeriksaan admin.")}
        </div>
        <div class="callout"><strong>Jika ada kesalahan</strong><p>Gunakan Batal pada konfirmasi untuk kembali ke formulir. Periksa kembali pilihan alat dan isian sebelum mengajukan.</p></div>
    """))
    pages.append(page(8, "", "", f"""
        {section_heading("04 · LANGKAH PENGGUNAAN", "Admin dan teknisi · proses transaksi",
            "Setelah siswa mengirim pengajuan, admin dan teknisi meneruskan proses sesuai tugas masing-masing.")}
        <h3 class="subhead">Admin</h3>
        <div class="steps compact">
          {step("1", "Buka Verifikasi Peminjaman", "Periksa identitas peminjam, daftar alat, jadwal, dan keperluan.")}
          {step("2", "Tentukan keputusan", "Setujui pengajuan jika stok tersedia dan informasi sesuai; atau tolak dengan alasan.")}
          {step("3", "Kelola data dan laporan", "Perbarui inventaris melalui menu terkait dan gunakan laporan untuk meninjau transaksi.")}
        </div>
        <h3 class="subhead">Teknisi</h3>
        <div class="steps compact">
          {step("1", "Cari transaksi", "Gunakan kode booking atau NIS pada menu Penyerahan atau Pengembalian.")}
          {step("2", "Catat penyerahan", "Masukkan Asset ID/serial number untuk unit yang diserahkan, kondisi, dan bukti foto sesuai form.")}
          {step("3", "Catat pengembalian", "Pilih item dan kondisi aktualnya, lampirkan foto yang diminta, lalu simpan catatan.")}
        </div>
        <div class="callout amber-callout"><strong>Catat kondisi apa adanya</strong><p>Periksa unit secara fisik. Status kondisi memengaruhi pencatatan stok; jangan menandai unit sebelum pemeriksaan selesai.</p></div>
    """))
    pages.append(page(9, "", "", f"""
        {section_heading("05 · KESIMPULAN", "Peminjaman yang tercatat, inventaris yang terjaga",
            "SIPAR menghubungkan pengajuan siswa dengan verifikasi admin serta serah terima dan pengembalian oleh teknisi.")}
        <p class="body-copy">Dengan katalog, formulir pengajuan, status transaksi, dan catatan operasional dalam satu aplikasi, pengguna dapat mengikuti perjalanan alat dari permintaan hingga kembali.</p>
        <p class="body-copy">Ketertiban data tetap bergantung pada penggunaan akun sesuai peran, pemeriksaan stok, pengisian informasi yang benar, serta pencatatan kondisi alat secara konsisten.</p>
        <div class="closing-card"><span>INGAT ALUR UTAMANYA</span><div>SISWA<br><b>Mengajukan</b></div><i>→</i><div>ADMIN<br><b>Memverifikasi</b></div><i>→</i><div>TEKNISI<br><b>Mencatat</b></div></div>
        <div class="end-mark"><span>SIPAR</span><small>Sistem Informasi Peminjaman Alat RPL<br>SMK Negeri 5 Surakarta</small></div>
        <p class="final-note">Gunakan SIPAR dengan teliti. Jaga alat, jaga catatan, dukung kegiatan praktik.</p>
    """))
    return pages


def build_english(logo_data: str) -> list[str]:
    pages = []
    pages.append(page(1, "", "", f"""
        <div class="cover-top"><img src="{logo_data}" alt="SMK Negeri 5 Surakarta emblem">
        <span>DIGITAL GUIDE · 2026 EDITION</span></div>
        <div class="cover-hero"><span class="cover-kicker">EQUIPMENT LOAN INFORMATION SYSTEM</span>
        <h1>SIPAR</h1><div class="cover-rule"></div>
        <h2>RPL Equipment Loan<br>User Guide</h2>
        <p>A clear, accountable, and easy-to-follow workflow for practical equipment loans.</p></div>
        <div class="cover-bottom"><span>SMK NEGERI 5 SURAKARTA</span><span>SOFTWARE ENGINEERING</span></div>
        <div class="cover-orb orb-one"></div><div class="cover-orb orb-two"></div>
    """, cover=True))
    pages.append(page(2, "", "", f"""
        {section_heading("BOOK MAP", "Contents", "A concise guide to SIPAR features, requirements, and workflows.")}
        <div class="toc">
          <div><span>01</span><b>Introduction</b><i>03</i></div>
          <div><span>02</span><b>Web Features</b><i>04</i></div>
          <div><span>03</span><b>Tools and Materials</b><i>06</i></div>
          <div><span>04</span><b>How to Use the Web App</b><i>07</i></div>
          <div><span>05</span><b>Conclusion</b><i>09</i></div>
        </div>
        <div class="callout"><strong>How to use this guide</strong><p>The usage section is organized by role: student, administrator, and technician. Follow the steps for your account.</p></div>
        <div class="mini-mark">S<span>/</span>P</div>
    """))
    pages.append(page(3, "", "", f"""
        {section_heading("01 · INTRODUCTION", "A more organized way to manage loans",
            "SIPAR is the RPL Equipment Loan Information System, designed to support practical equipment management at SMK Negeri 5 Surakarta.")}
        <p class="body-copy">An equipment loan involves several details: who borrows an item, which equipment is needed, when it is collected, and when it is due back. Scattered records can make stock and transaction history difficult to monitor.</p>
        <p class="body-copy">SIPAR brings these details together in a web application. Requests, reviews, handovers, and returns are recorded in a traceable workflow, with access based on each user's role.</p>
        <div class="quote"><span>“</span><p>One digital workflow helps keep practical equipment accounted for and ready to use.</p></div>
        <div class="role-grid">
          {bullet("STUDENT", "Select equipment, submit a request, and follow its status and history.", "blue")}
          {bullet("ADMIN", "Manage inventory data and decide on loan requests.", "violet")}
          {bullet("TECHNICIAN", "Record physical handovers and process returns.", "amber")}
        </div>
        <p class="small-note">Available menus depend on the account and assigned role.</p>
    """))
    pages.append(page(4, "", "", f"""
        {section_heading("02 · WEB FEATURES", "Student menus available in the web app",
            "Students use four main menus. Multi-item requests are part of the Equipment Catalog workflow, not a separate menu.")}
        <div class="feature-list">
          {bullet("01 · Dashboard", "A summary of available equipment, submitted requests, pending requests, and completed transactions.", "blue")}
          {bullet("02 · Equipment Catalog", "Browse and search by name or code, check availability, select several equipment types, and submit one request. The catalog form allows at most one unit per type.", "violet")}
          {bullet("03 · Loan History", "Review request status and details as a transaction progresses to completion.", "green")}
          {bullet("04 · My Profile", "View account information and manage the available profile and password settings.", "amber")}
        </div>
        <div class="fact-strip"><b>Catalog request rules</b><span>Loan date is today · maximum duration is 5 days · every request awaits administrator review</span></div>
    """))
    pages.append(page(5, "", "", f"""
        {section_heading("02 · WEB FEATURES", "Inventory controls and physical handover",
            "Each role completes a different part of the workflow while keeping records connected.")}
        <div class="feature-list">
          {bullet("ADMIN · Master data", "Manage equipment, categories, and student accounts; review, approve, or reject requests.", "violet")}
          {bullet("ADMIN · Reports", "Review transaction summaries and download available PDF or Excel reports.", "blue")}
          {bullet("TECHNICIAN · Handover", "Find a transaction by booking code or student ID, then record Asset IDs/serial numbers, condition, and photo evidence.", "amber")}
          {bullet("TECHNICIAN · Return", "Find a transaction, record item condition and photo evidence, and process returns item by item.", "green")}
        </div>
        <div class="flow"><span>REQUEST</span><b>→</b><span>REVIEW</span><b>→</b><span>HANDOVER</span><b>→</b><span>RETURN</span></div>
        <p class="small-note">Stock is updated when a request is approved and when a return is recorded according to the equipment's condition.</p>
    """))
    pages.append(page(6, "", "", f"""
        {section_heading("03 · TOOLS AND MATERIALS", "What do you need?",
            "Requirements differ for application users and the team that builds or deploys SIPAR.")}
        <h3 class="subhead">To use SIPAR</h3>
        <div class="check-list"><p><b>01</b> A computer, laptop, tablet, or smartphone.</p>
        <p><b>02</b> A modern browser and network access to the application.</p>
        <p><b>03</b> An active SIPAR account with a student, administrator, or technician role.</p>
        <p><b>04</b> For technician tasks: Asset ID/serial number details and condition photos when requested.</p></div>
        <h3 class="subhead">Project technology</h3>
        <div class="tech-grid">
          {bullet("Laravel 12 + PHP 8.2+", "Application backend, routes, authentication, validation, and transaction workflows.", "blue")}
          {bullet("Blade + Bootstrap 5", "Page templates and responsive interface components.", "violet")}
          {bullet("MySQL", "Data storage for deployments configured with a MySQL service.", "green")}
          {bullet("Composer · Node.js/npm · Vite", "PHP dependency management and frontend asset development/build tools.", "amber")}
          {bullet("Git + Railway", "Version control and application deployment platform.", "blue")}
        </div>
        <p class="small-note">Local development requires an environment configuration and a compatible database.</p>
    """))
    pages.append(page(7, "", "", f"""
        {section_heading("04 · HOW TO USE THE WEB APP", "Student · submit a loan request",
            "Prepare the equipment you need, the usage dates, and the reason for borrowing before you start.")}
        <div class="steps">
          {step("1", "Sign in", "Open the SIPAR address in a browser and sign in with your student account.")}
          {step("2", "Find equipment", "From the Dashboard, open Equipment Catalog. Search by name or code and check availability.")}
          {step("3", "Select equipment", "Choose Select Equipment for each item you need. Review your selection in the Loan panel.")}
          {step("4", "Complete the form", "Set the loan date (today), planned return date (within five days), and a clear purpose.")}
          {step("5", "Review and submit", "Make sure the item list is not empty. Select Submit Loan Request, review the confirmation, then choose Continue.")}
          {step("6", "Track status", "Open Loan History to check the status. A Pending request is awaiting administrator review.")}
        </div>
        <div class="callout"><strong>If you need to make changes</strong><p>Choose Cancel in the confirmation to return to the form. Review your item selections and details before submitting.</p></div>
    """))
    pages.append(page(8, "", "", f"""
        {section_heading("04 · HOW TO USE THE WEB APP", "Administrator and technician · process a transaction",
            "After a student submits a request, the administrator and technician continue the process according to their responsibilities.")}
        <h3 class="subhead">Administrator</h3>
        <div class="steps compact">
          {step("1", "Open Loan Verification", "Check the borrower's identity, item list, schedule, and purpose.")}
          {step("2", "Make a decision", "Approve if stock is available and the information is valid, or reject with a reason.")}
          {step("3", "Manage data and reports", "Update inventory in the relevant menus and use reports to review transactions.")}
        </div>
        <h3 class="subhead">Technician</h3>
        <div class="steps compact">
          {step("1", "Find the transaction", "Use a booking code or student ID in Handover or Return.")}
          {step("2", "Record the handover", "Enter an Asset ID/serial number for each unit handed over, its condition, and requested photo evidence.")}
          {step("3", "Record the return", "Select the item and its observed condition, attach requested photos, and save the record.")}
        </div>
        <div class="callout amber-callout"><strong>Record the actual condition</strong><p>Inspect each item physically. Its condition affects stock records; do not mark a unit before the inspection is complete.</p></div>
    """))
    pages.append(page(9, "", "", f"""
        {section_heading("05 · CONCLUSION", "Accountable loans, well-managed inventory",
            "SIPAR connects student requests with administrator review and technician handover and return records.")}
        <p class="body-copy">With a catalog, request form, transaction status, and operational notes in one application, users can follow equipment from request through return.</p>
        <p class="body-copy">Reliable records still depend on using the correct role, checking stock, entering accurate information, and consistently recording equipment condition.</p>
        <div class="closing-card"><span>THE CORE WORKFLOW</span><div>STUDENT<br><b>Requests</b></div><i>→</i><div>ADMIN<br><b>Reviews</b></div><i>→</i><div>TECHNICIAN<br><b>Records</b></div></div>
        <div class="end-mark"><span>SIPAR</span><small>RPL Equipment Loan Information System<br>SMK Negeri 5 Surakarta</small></div>
        <p class="final-note">Use SIPAR carefully. Protect the equipment, maintain accurate records, support practical learning.</p>
    """))
    return pages


CSS = """
@page { size: A5 portrait; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; color: #172b3b; font-family: "Segoe UI", Arial, sans-serif; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
.page { position: relative; width: 148mm; height: 210mm; padding: 15mm 14mm 16mm; overflow: hidden; page-break-after: always; background: #fff; }
.page:last-child { page-break-after: auto; }
.section-head { border-bottom: 1px solid #dfe8f0; padding-bottom: 6mm; margin-bottom: 7mm; }
.eyebrow { color: #1768b2; font-size: 8pt; font-weight: 800; letter-spacing: 1.5px; }
h2 { font-size: 23pt; line-height: 1.08; letter-spacing: -.5px; margin: 3mm 0 2mm; color: #142a3a; }
.lead { font-size: 10pt; line-height: 1.5; color: #657789; margin: 0; max-width: 115mm; }
.body-copy { font-size: 10pt; line-height: 1.65; color: #405469; margin: 0 0 4mm; }
.quote { display: flex; gap: 3mm; align-items: flex-start; background: #eef6ff; border-left: 3px solid #2c7ac0; border-radius: 0 8px 8px 0; padding: 4mm; margin: 5mm 0; }
.quote span { font-size: 28pt; line-height: .8; color: #2374bb; font-weight: 800; }
.quote p { margin: 0; color: #244b6b; font-size: 12pt; font-weight: 700; line-height: 1.4; }
.role-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2.5mm; margin-top: 5mm; }
.card { border-radius: 8px; padding: 3.2mm; border: 1px solid #e5ebf1; background: #fff; break-inside: avoid; }
.card-title { display: block; font-size: 8pt; font-weight: 800; color: #19374e; margin-bottom: 1.5mm; }
.card p { font-size: 7.5pt; line-height: 1.4; margin: 0; color: #536779; }
.blue { background: #f0f7ff; border-color: #d7eaff; }
.violet { background: #f5f2ff; border-color: #e6deff; }
.green { background: #effaf3; border-color: #d7efdf; }
.amber { background: #fff8e9; border-color: #f4e5be; }
.small-note { font-size: 8pt; color: #758596; line-height: 1.45; margin: 4mm 0 0; }
.feature-list { display: grid; gap: 3mm; }
.feature-list .card { padding: 4mm 4.5mm; }
.feature-list .card-title { font-size: 9.5pt; }
.feature-list .card p { font-size: 8.5pt; line-height: 1.45; }
.fact-strip { margin-top: 5mm; border-radius: 8px; padding: 4mm; background: #172f46; color: #fff; }
.fact-strip b { display: block; font-size: 8pt; text-transform: uppercase; letter-spacing: .8px; color: #a9d3ff; margin-bottom: 1.5mm; }
.fact-strip span { display: block; font-size: 8.5pt; line-height: 1.4; }
.flow { display: flex; justify-content: space-between; align-items: center; margin: 5mm 0 3mm; padding: 3mm; background: #f2f6fa; border-radius: 7px; color: #31536f; font-size: 7pt; font-weight: 800; }
.flow b { color: #2a7bc0; font-size: 12pt; }
.subhead { font-size: 11pt; margin: 4mm 0 2.5mm; color: #1c3e58; }
.check-list { border: 1px solid #e3eaf0; border-radius: 8px; padding: 2mm 4mm; background: #fbfcfe; }
.check-list p { margin: 2mm 0; font-size: 8.4pt; line-height: 1.4; color: #475d70; }
.check-list b { color: #2474b5; margin-right: 2mm; }
.tech-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm; }
.tech-grid .card { padding: 2.5mm 3mm; }
.tech-grid .card-title { font-size: 7.6pt; margin-bottom: 1mm; }
.tech-grid .card p { font-size: 7pt; }
.steps { display: grid; gap: 3mm; }
.step { display: grid; grid-template-columns: 9mm 1fr; gap: 3mm; align-items: start; }
.step-no { width: 8mm; height: 8mm; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #eaf3ff; color: #1468ac; font-weight: 800; font-size: 9pt; }
.step strong { display: block; font-size: 9pt; color: #213e55; margin: .4mm 0 1mm; }
.step p { margin: 0; font-size: 8.2pt; color: #596c7c; line-height: 1.4; }
.callout { margin-top: 5mm; padding: 4mm; border-radius: 8px; border: 1px solid #cfe2f5; background: #f0f7ff; }
.callout strong { font-size: 8.5pt; color: #1c5e95; }
.callout p { margin: 1.5mm 0 0; color: #4a6378; font-size: 8pt; line-height: 1.4; }
.amber-callout { background: #fff8e9; border-color: #f0dfb6; }
.amber-callout strong { color: #966112; }
.compact { gap: 2mm; }
.compact .step { grid-template-columns: 7mm 1fr; gap: 2mm; }
.compact .step-no { width: 6mm; height: 6mm; font-size: 7pt; }
.compact .step strong { font-size: 8pt; margin: 0 0 .5mm; }
.compact .step p { font-size: 7.4pt; }
.closing-card { margin-top: 7mm; padding: 5mm 4mm; display: flex; align-items: center; justify-content: space-between; border-radius: 9px; color: #fff; background: #183951; }
.closing-card > span { position: absolute; transform: translateY(-12mm); font-size: 7pt; font-weight: 800; letter-spacing: 1px; color: #48677f; }
.closing-card div { font-size: 6.5pt; color: #b6d2e7; line-height: 1.5; }
.closing-card b { color: #fff; font-size: 8pt; }
.closing-card i { font-style: normal; color: #80c4ff; font-size: 15pt; }
.end-mark { margin: 13mm 0 5mm; display: flex; align-items: center; gap: 4mm; }
.end-mark span { font-size: 26pt; font-weight: 900; color: #176db3; letter-spacing: -1px; }
.end-mark small { border-left: 1px solid #cad6e0; padding-left: 4mm; color: #617589; font-size: 8pt; line-height: 1.45; }
.final-note { border-top: 1px solid #e0e8ef; padding-top: 4mm; color: #38536a; font-size: 9pt; font-weight: 600; line-height: 1.45; }
.toc { margin-top: 10mm; display: grid; gap: 0; }
.toc div { display: grid; grid-template-columns: 12mm 1fr 10mm; align-items: center; padding: 5mm 1mm; border-bottom: 1px solid #e6edf2; }
.toc span { color: #1d72b7; font-size: 9pt; font-weight: 800; }
.toc b { color: #263f54; font-size: 10pt; }
.toc i { color: #718598; font-style: normal; font-size: 8pt; text-align: right; }
.mini-mark { position: absolute; bottom: 24mm; right: 16mm; font-size: 28pt; font-weight: 900; color: #e8f1f8; }
.mini-mark span { color: #b9d8f0; }
footer { position: absolute; left: 14mm; right: 14mm; bottom: 7mm; padding-top: 2.5mm; border-top: 1px solid #e2eaf0; display: flex; justify-content: space-between; color: #8392a0; font-size: 6.5pt; letter-spacing: .5px; }
.cover { color: #fff; padding: 15mm 14mm; background: linear-gradient(150deg,#102b42 0%,#174b70 58%,#1974ad 100%); }
.cover-top { position: relative; z-index: 2; display: flex; align-items: center; gap: 3mm; color: #c9e6fa; font-size: 7pt; font-weight: 700; letter-spacing: .9px; }
.cover-top img { width: 17mm; height: 17mm; object-fit: contain; border-radius: 50%; background: #fff; padding: 1mm; }
.cover-hero { position: relative; z-index: 2; margin-top: 23mm; }
.cover-kicker { color: #8ed3ff; font-size: 7pt; font-weight: 800; letter-spacing: 1.6px; }
.cover h1 { margin: 3mm 0 1mm; color: #fff; font-size: 52pt; line-height: 1; letter-spacing: -2px; }
.cover-rule { width: 25mm; height: 1.5mm; background: #75caff; margin: 5mm 0 7mm; border-radius: 2px; }
.cover h2 { color: #fff; font-size: 24pt; line-height: 1.16; margin: 0 0 5mm; }
.cover-hero p { color: #d5e9f8; font-size: 11pt; line-height: 1.5; max-width: 108mm; }
.cover-bottom { position: absolute; z-index: 2; left: 14mm; right: 14mm; bottom: 15mm; border-top: 1px solid #ffffff55; padding-top: 4mm; display: flex; justify-content: space-between; color: #c3deef; font-size: 6.5pt; font-weight: 700; letter-spacing: .6px; }
.cover-orb { position: absolute; border-radius: 50%; border: 1px solid #ffffff18; }
.orb-one { width: 105mm; height: 105mm; right: -54mm; top: 41mm; background: #ffffff08; }
.orb-two { width: 77mm; height: 77mm; right: -38mm; top: 56mm; background: #ffffff08; }
"""


def document(title: str, language: str, pages: list[str]) -> str:
    return f"""<!doctype html>
<html lang="{language}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{esc(title)}</title><style>{CSS}</style></head>
<body>{''.join(pages)}</body></html>"""


def main() -> None:
    OUTPUT.mkdir(parents=True, exist_ok=True)
    logo_data = "data:image/png;base64," + base64.b64encode(LOGO.read_bytes()).decode("ascii")
    editions = [
        ("SIPAR-Panduan-Penggunaan-ID", "Panduan Penggunaan SIPAR", "id", build_indonesian(logo_data)),
        ("SIPAR-User-Guide-EN", "SIPAR User Guide", "en", build_english(logo_data)),
    ]
    for filename, title, language, pages in editions:
        target = OUTPUT / f"{filename}.html"
        target.write_text(document(title, language, pages), encoding="utf-8")
        print(target)


if __name__ == "__main__":
    main()
