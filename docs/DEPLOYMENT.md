# Deployment Guide

## Environment Variables

Required `.env` variables:

```env
APP_NAME="Cwt Academy"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cwt_academy
DB_USERNAME=
DB_PASSWORD=

# Manual Telegram Access Flow - No bot or webhook required
# Telegram channels are configured in admin panel with static invite/join links

ADMIN_DEFAULT_EMAIL=admin@cwtacademy.local
ADMIN_DEFAULT_PASSWORD=change-this-in-production

PAYMENT_PROOF_MAX_MB=5

QUEUE_CONNECTION=database
```

## Setup Commands

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Queue Worker

Start a queue worker for background jobs:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

For production, use Supervisor:

```ini
[program:cwtacademy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cwtacademy-worker.log
stopwaitsecs=3600
```

## Storage

Ensure the storage directory is writable:

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

Payment proofs are stored in `storage/app/payment_proofs`.

## Telegram Channel Setup (Manual Workflow)

**This application uses a manual Telegram join-request workflow. No bot is required.**

### Steps:

1. **Create Private Telegram Channels**
   - Create a Telegram channel for each course
   - Make it private
   - Configure to require join approval (Settings > Channel Type > Private Channel > Approve New Members)

2. **Get Channel Invite Links**
   - In channel settings, create an invite link
   - Can be permanent: `https://t.me/+abc123xyz`
   - Copy this link

3. **Configure in Admin Panel**
   - Log into admin panel
   - Navigate to Telegram Channels
   - Create channel mapping with:
     - Course ID
     - Telegram URL (the invite link from step 2)
     - Channel title
     - Mark as active

4. **Manual Approval Workflow**
   - When a student's payment is approved, they see the Telegram link on their dashboard
   - Student clicks the link and sends a join request in Telegram
   - Your team manually approves the request inside Telegram
   - Admin marks the access as granted in the admin panel (for tracking)

5. **Revocation**
   - To revoke: manually remove the student from the Telegram channel
   - Mark as revoked in the admin panel

**No bot setup, webhook configuration, or BotFather interaction required.**

---## Production Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Generate a strong `APP_KEY`
- [ ] Configure MySQL/MariaDB credentials
- [ ] Set strong admin password (not the default)
- [ ] Configure HTTPS and redirect HTTP to HTTPS
- [ ] Set up queue workers (Supervisor)
- [ ] Configure log rotation
- [ ] Set up database backups
- [ ] Configure firewall (only allow 80/443)
- [ ] Create Telegram channels with join request approval enabled
- [ ] Add Telegram channel links to course configurations in admin panel
- [ ] Run `php artisan optimize`
- [ ] Test all API endpoints
- [ ] Verify file upload limits in PHP and web server config
- [ ] Set up monitoring and alerting
