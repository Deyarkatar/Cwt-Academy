# Backup and Incident Response Plan — Cwt Academy (2026)

## 1. Backup Strategy

### Database (MySQL)

- **Frequency**: Daily full backup + hourly incremental (binlog)
- **Method**: `mysqldump --single-transaction --routines --triggers` for full backup
- **Retention**: 30 days daily, 12 months weekly, 7 years monthly (adjust per compliance)
- **Encryption**: Backups encrypted at rest using GPG or age
- **Storage**: Off-site (S3/R2 with versioning), separate from application infrastructure
- **Verification**: Monthly restore test to verify backup integrity

### Redis

- **AOF persistence**: `appendonly yes`, `appendfsync everysec` (already configured)
- **RDB snapshots**: `save 60 1` (already configured)
- **Note**: Redis is cache/queue/session only — not a source of truth. Full data lives in MySQL.

### File Uploads (Payment Proofs)

- **Storage**: `storage/app/payment_proofs/` on local disk
- **Backup**: Daily rsync to off-site storage
- **Retention**: Same as database retention (payment proofs are business records)

### Application Code

- **Source control**: Git (GitHub)
- **Backups**: GitHub repository + local clones
- **Tags**: Every release tagged with semantic version

### Backup Script

```bash
#!/bin/bash
# scripts/backup.sh (existing)
# Daily MySQL backup with encryption
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="cwt_academy_${DATE}.sql.gz"
mysqldump -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" | gzip | gpg --encrypt --recipient "$GPG_RECIPIENT" > "/backups/${BACKUP_FILE}.gpg"
# Upload to S3/R2
aws s3 cp "/backups/${BACKUP_FILE}.gpg" "s3://cwt-backups/db/"
```

## 2. Incident Response Plan

### Severity Levels

| Level | Description | Response Time |
|---|---|---|
| P0 | Data breach, active exploitation | Immediate (15 min) |
| P1 | Security vulnerability with active risk | 1 hour |
| P2 | Security issue, no active exploitation | 4 hours |
| P3 | Hardening improvement, low risk | Next sprint |

### P0: Data Breach / Active Exploitation

1. **Contain** (immediate)
   - Take affected services offline (docker-compose stop)
   - Preserve evidence (do not destroy logs)
   - Snapshot database for forensic analysis

2. **Assess** (within 1 hour)
   - Identify what was accessed (audit_logs table)
   - Check honey token alerts in logs
   - Determine scope of exposure (user data, payment proofs, admin credentials)

3. **Notify** (within 24 hours)
   - Notify affected users
   - Notify relevant authorities per local regulations
   - Document incident in post-mortem

4. **Remediate**
   - Rotate all secrets (database, Redis, API keys, admin passwords)
   - Patch vulnerability
   - Restore from clean backup if data was corrupted
   - Deploy fix and verify with tests

5. **Post-mortem** (within 1 week)
   - Root cause analysis
   - Timeline of events
   - Lessons learned
   - Preventive measures implemented

### P1: Security Vulnerability

1. **Triage** — Verify vulnerability, assess impact
2. **Fix** — Develop patch, write regression test
3. **Deploy** — Emergency deployment after tests pass
4. **Document** — Update security documentation

### Incident Detection Signals

| Signal | Source | Action |
|---|---|---|
| Honey token triggered | `HoneyTokenGuard` → Log::critical | Investigate immediately |
| Brute force block | `BruteForceDetectionMiddleware` → Log::critical | Check if targeted attack |
| Multiple failed logins | `AccountLockoutService` → Log::warning | Monitor for patterns |
| CSP violation report | CSP report-uri endpoint | Check for XSS attempts |
| Sentry error spike | Sentry dashboard | Check for application errors |

### Contact List

- **Lead Developer**: [Fill in]
- **System Administrator**: [Fill in]
- **Security Advisor**: [Fill in]
- **Hosting Provider**: [Fill in]

## 3. Recovery Procedures

### Database Recovery

```bash
# Stop application
docker-compose stop php queue-worker scheduler horizon

# Restore from backup
gpg --decrypt backup.sql.gz.gpg | gunzip | mysql -u root -p cwt_academy

# Verify integrity
php artisan migrate:status
php artisan tinker --execute="echo User::count();"

# Restart
docker-compose up -d
```

### Full System Recovery

1. Provision new server
2. Clone repository
3. Configure `.env` with fresh secrets
4. Run `docker-compose up -d`
5. Restore database from backup
6. Restore payment proof files from backup
7. Run `php artisan migrate --force`
8. Run `php artisan optimize:clear`
9. Verify with `php artisan test`
10. Switch DNS/load balancer to new server

## 4. Security Monitoring Checklist

- [ ] Review audit logs weekly for suspicious activity
- [ ] Check brute force blocks daily
- [ ] Monitor CSP violation reports
- [ ] Review Sentry errors daily
- [ ] Verify backup integrity monthly
- [ ] Patch dependencies monthly
- [ ] Rotate API keys quarterly
- [ ] Review admin access list monthly
