# Deploying Glisa to a VPS

One Ubuntu 24.04 server runs everything: Nginx serves the site over HTTPS, PHP-FPM runs Laravel, and Supervisor keeps the Reverb WebSocket server alive behind Nginx. The SQLite database and uploaded cards/songs live on the server's disk.

## Before you start

- An Ubuntu 24.04 VPS (1 GB RAM is enough to start) with SSH access as root or a sudo user.
- A domain or subdomain, for example `draft.yourdomain.com`, whose DNS **A record points to the server's IP**. The HTTPS certificate cannot be issued until it does.
- The code pushed to `main` on GitHub. `provision.sh` clones `https://github.com/khalid413/Draft.git`; if the repository is private, give the server a deploy key first or set `REPO` to an SSH URL it can read.

## First-time setup

SSH into the server, then:

```bash
git clone https://github.com/khalid413/Draft.git /tmp/draft-setup
DOMAIN=draft.yourdomain.com EMAIL=you@yourdomain.com bash /tmp/draft-setup/deploy/provision.sh
```

The script installs Nginx, PHP 8.3, Node, Composer, Supervisor and Certbot; puts the app in `/var/www/draft-duel`; writes `.env` from `deploy/env.production.example` with a fresh app key and Reverb credentials; builds the frontend; migrates and seeds the football players; starts Reverb; gets a Let's Encrypt certificate; and opens only SSH, HTTP and HTTPS in the firewall.

Then create the admin account, either way:

- **Interactive:** asks for an email and a 12+ character password.

  ```bash
  cd /var/www/draft-duel && sudo -u www-data php artisan draft:admin
  ```

- **From `.env` (AdminSeeder):** set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `/var/www/draft-duel/.env` (quote the password if it has special characters), then rebuild the config cache and seed:

  ```bash
  cd /var/www/draft-duel && sudo -u www-data php artisan optimize && sudo -u www-data php artisan db:seed --class=AdminSeeder --force
  ```

Never put the admin password in the repository: it is public.

## Shipping updates

Push to `main`, then on the server:

```bash
bash /var/www/draft-duel/deploy/deploy.sh
```

It puts the site in maintenance mode, fast-forwards to the latest `main`, installs dependencies, rebuilds assets, migrates, refreshes caches and restarts Reverb.

## Files

| File | Installed as |
| --- | --- |
| `deploy/env.production.example` | `/var/www/draft-duel/.env` |
| `deploy/nginx.conf` | `/etc/nginx/sites-available/draft-duel` |
| `deploy/supervisor-reverb.conf` | `/etc/supervisor/conf.d/draft-duel-reverb.conf` |

## Checking on it

- Site health: `https://your-domain/up` returns 200.
- Reverb: `supervisorctl status draft-duel-reverb`, log in `storage/logs/reverb.log`.
- App errors: `storage/logs/laravel-*.log`.
- Rooms keep working if Reverb is down; browsers fall back to re-checking every 2 seconds.

## Backups

Everything that matters is on disk: `database/database.sqlite` and `storage/app/private` (uploaded card images and songs). Back up both, for example with a nightly copy using `sqlite3 database/database.sqlite ".backup /root/backups/draft-$(date +%F).sqlite"`.
