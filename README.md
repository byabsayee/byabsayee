<div align="center">

<img src="https://raw.githubusercontent.com/byabsayee/byabsayee/main/public/assets/images/ByabsayeeLogo.png" alt="Byabsayee Logo" width="220" />

# Byabsayee

**Open source ERP for small businesses — invoices, inventory, employees, accounting and more.**

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-10.11-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](https://docker.com)
[![GitHub Actions](https://img.shields.io/github/actions/workflow/status/byabsayee/byabsayee/docker-publish.yml?label=build)](https://github.com/byabsayee/byabsayee/actions)

[Features](#features) · [Quick Deploy](#quick-deploy) · [Self-Hosting Guide](#self-hosting-guide) · [Screenshots](#screenshots) · [Contributing](#contributing)

</div>

---

## What is Byabsayee?

**Byabsayee** (ব্যবসায়ী) means *businessman* in Bengali. It is a free, self-hosted ERP (Enterprise Resource Planning) system built for small businesses — particularly in Bangladesh and South Asia — that need a simple, affordable alternative to expensive SaaS tools.

Run it entirely on your own server. Your data never leaves your hands.

---

## Features

- 🧾 **Invoicing** — Create, send, and track invoices with PDF export
- 📦 **Inventory** — Manage products, categories, and stock levels
- 👥 **Customers & Suppliers** — Full contact management
- 👷 **Employees** — Profiles, roles, and privilege management
- 💰 **Accounting** — Expenses, funds, debts, dues, and payments
- 📊 **Reports** — Sales, expenses, and business summaries
- 🏪 **POS** — Point of sale interface
- 📚 **Books** — Multi-book support for different business units
- 🔔 **Notifications** — In-app notification system
- 🔐 **Two-Factor Authentication** — TOTP-based 2FA for security
- 📱 **WhatsApp Integration** — Send messages via WhatsApp API
- 🌐 **Public Invoice Pages** — Share invoices with customers via link
- 🐳 **Docker ready** — Deploy in minutes with one compose file

---

## Screenshots

> _Coming soon_

---

## Quick Deploy

The fastest way to run Byabsayee on any server with Docker installed.

**1. Download the compose file**
```bash
curl -O https://raw.githubusercontent.com/byabsayee/byabsayee/main/docker-compose.yml
```

**2. Create your environment file**
```bash
curl -O https://raw.githubusercontent.com/byabsayee/byabsayee/main/.env.example
cp .env.example .env
nano .env   # fill in your passwords
```

**3. Start everything**
```bash
docker compose up -d
```

Byabsayee will be available at `http://YOUR_SERVER_IP:1021`
phpMyAdmin will be available at `http://YOUR_SERVER_IP:8093`

> The database schema is imported automatically on first run. No manual setup needed.

---

## Self-Hosting Guide

### Requirements

| Requirement | Minimum |
|-------------|---------|
| OS | Any Linux distro (Ubuntu 22.04+ recommended) |
| RAM | 512 MB |
| Disk | 2 GB free |
| Docker | 24.0+ |
| Docker Compose | 2.0+ |

### Step 1 — Install Docker

If you don't have Docker installed:
```bash
curl -fsSL https://get.docker.com | sh
```

### Step 2 — Create a folder and download the files

```bash
mkdir byabsayee && cd byabsayee

curl -O https://raw.githubusercontent.com/byabsayee/byabsayee/main/docker-compose.yml
curl -O https://raw.githubusercontent.com/byabsayee/byabsayee/main/.env.example
cp .env.example .env
```

### Step 3 — Configure your .env

Open `.env` with any text editor and fill in your values:

```bash
nano .env
```

Key values to change:

| Variable | What to put |
|----------|-------------|
| `APP_KEY` | Run: `php -r "echo bin2hex(random_bytes(32));"` |
| `APP_URL` | Your server's IP or domain, e.g. `http://192.168.1.100:1021` |
| `DB_USER` | Any username, e.g. `byabsayee_user` |
| `DB_PASS` | A strong password |
| `MYSQL_ROOT_PASSWORD` | A different strong password |
| `SMTP_*` | Your email provider credentials |

### Step 4 — Start Byabsayee

```bash
docker compose up -d
```

Check the logs to confirm it started:
```bash
docker logs byabsayee --tail 20
```

You should see:
```
[Byabsayee] Database already set up (55 tables found). Skipping schema import.
```

### Step 5 — Open in browser

Go to `http://YOUR_SERVER_IP:1021` and register your first account.

---

### Deploying with Portainer

If you use [Portainer](https://portainer.io) to manage your Docker server:

1. Open Portainer → **Stacks** → **+ Add stack**
2. Name it `byabsayee`
3. Paste the contents of `docker-compose.yml` into the web editor
4. Scroll down → **Environment variables** → **Advanced mode**
5. Paste the contents of your `.env` file
6. Click **Deploy the stack**

---

### Updating to the latest version

```bash
docker compose pull
docker compose up -d
```

Or in Portainer: **Update the stack** → tick **Re-pull image** → **Update**.

---

### Configuring a domain with HTTPS

To use a domain name (e.g. `erp.yourdomain.com`) with HTTPS, use a reverse proxy like [Nginx Proxy Manager](https://nginxproxymanager.com) or [Caddy](https://caddyserver.com) in front of Byabsayee.

Example Nginx Proxy Manager setup:
- **Domain:** `erp.yourdomain.com`
- **Forward Hostname:** your server IP
- **Forward Port:** `1021`
- Enable **SSL** with Let's Encrypt

Then update `APP_URL` in your `.env` to `https://erp.yourdomain.com` and restart.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.2 |
| Architecture | Custom MVC (no framework) |
| Database | MariaDB 10.11 |
| Web server | Nginx + PHP-FPM |
| Container | Docker (Alpine Linux) |
| Process manager | Supervisord |
| PDF generation | Custom PHP service |
| Email | PHPMailer via SMTP |

---

## Development

### Running locally

Clone the repo and start with Docker:

```bash
git clone https://github.com/byabsayee/byabsayee.git
cd byabsayee
cp .env.example .env
# fill in .env values
docker compose up -d
```

### Live editing without rebuilding

Create a `docker-compose.override.yml` (not committed to git):

```yaml
services:
  byabsayee:
    volumes:
      - .:/Sites/byabsayee
```

Now any change to PHP, CSS, or JS files takes effect immediately on browser refresh.

### Project structure

```
byabsayee/
├── app/
│   ├── Controllers/     # One controller per feature
│   ├── Helpers/         # Database, Router, Mailer
│   └── Services/        # PDF, Activity logging
├── config/
│   └── app.php          # All config reads from .env
├── public/
│   ├── index.php        # Entry point (all requests go here)
│   ├── css/app.css      # Main stylesheet
│   └── js/app.js        # Main JavaScript
├── views/               # PHP view templates
├── nginx/               # Nginx config
├── schema.sql           # Full database schema
├── Dockerfile
└── docker-compose.yml
```

---

## Contributing

Contributions are welcome! Here's how:

1. Fork the repository
2. Create a branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Commit: `git commit -m "add: your feature description"`
5. Push: `git push origin feature/your-feature`
6. Open a Pull Request

Please keep code consistent with the existing style (vanilla PHP, no frameworks).

---

## License

Byabsayee is open source under the [MIT License](LICENSE).
You are free to use, modify, and distribute it for personal or commercial purposes.

---

<div align="center">

Made with ❤️ in Bangladesh

</div>