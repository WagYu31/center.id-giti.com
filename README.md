# 🏢 Center Loewix (Grav Center)

> **Central Dashboard & SSO Gateway** untuk seluruh aplikasi internal Grav Technology / Loewix Group.

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap_5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)

---

## ✨ Features

| Feature | Description |
|---|---|
| 🔐 **SSO Authentication** | Single Sign-On gateway untuk semua sub-aplikasi |
| 🎨 **Bright Gold Theme** | UI premium dengan tema cerah dan aksen emas |
| 👥 **User Management** | Kelola akses karyawan per aplikasi |
| 📝 **Quick Notes** | Catatan harian langsung dari dashboard |
| 🕐 **Live Clock** | Jam real-time dengan animasi smooth |
| 🔑 **Google OAuth** | Login via Google Account |
| 📱 **Responsive** | Tampilan optimal di desktop & mobile |

## 🚀 Connected Applications

| App | Description |
|---|---|
| 💰 **Salary** | Sistem penggajian karyawan |
| 📋 **Bukti** | Manajemen bukti & dokumentasi |
| 📈 **Sales** | Target & omset tracking |
| 📄 **Quotation** | Pembuatan penawaran |
| 🔧 **Service** | Manajemen perbaikan unit |
| 👷 **Teknisi** | Manajemen jadwal teknisi |
| 🛡️ **Garansi** | Tracking garansi produk |

## 🛠️ Tech Stack

- **Backend:** PHP 8.x (Native MVC)
- **Database:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **UI Framework:** Bootstrap 5.3
- **Font:** Plus Jakarta Sans (Google Fonts)
- **Auth:** Session-based + SSO Token + Google OAuth

## 📁 Project Structure

```
center.id-giti.com/
├── config/             # Database & Google OAuth config
├── src/                # Core auth & helper functions
├── center/             # Admin panel (MVC pattern)
│   ├── app/
│   │   ├── config/
│   │   ├── controllers/
│   │   ├── core/
│   │   ├── models/
│   │   └── views/
│   └── public/
├── public/             # Main public-facing files
│   ├── assets/         # CSS, JS, images
│   ├── bukti/          # Bukti sub-app
│   ├── index.php       # Dashboard
│   ├── login.php       # Login page
│   └── data-karyawan.php  # Admin: user management
└── .gitignore
```

> **Note:** Sub-aplikasi besar (Quotation, Sales, Jadwal, Salary, Service) di-deploy terpisah via aaPanel dan tidak termasuk di repository ini.

## ⚡ Quick Start

```bash
# Clone repository
git clone https://github.com/WagYu31/center.id-giti.com.git

# Import database
mysql -u root -p < center_id_giti.sql

# Configure database
# Edit config/database.php with your credentials

# Run with PHP built-in server
php -S localhost:8080 -t public
```

## 🎨 UI Preview

- **Theme:** Bright Gold Premium
- **Background:** Warm Cream (`#f5f3ef`)
- **Accent:** Gold (`#eab308` / `#facc15`)
- **Cards:** White with subtle shadows
- **Animations:** Stagger entrance, hover glow, floating clock

---

<p align="center">
  <b>Grav Technology</b> · Built with ☕ & 💛
</p>
