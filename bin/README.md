# Utility Scripts

This directory contains maintenance and utility scripts for Dora Files.

## Available Scripts

### check-setup.php
**Purpose:** Verify installation and system requirements

**Usage:**
```bash
php bin/check-setup.php
```

**Checks:**
- PHP version (8.1+)
- Required PHP extensions (ftp, openssl, pdo, pdo_mysql)
- Configuration files (.env)
- Database connection
- Database tables
- Directory structure
- User count

**When to run:**
- After initial installation
- After migrations
- When troubleshooting issues
- Before deploying updates

---

### cleanup.php
**Purpose:** Automated cleanup of expired data and temporary files

**Usage:**
```bash
# Manual execution
php bin/cleanup.php

# Recommended: Set up cron job (daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/htdocs/files.regaletoimagl.fr/bin/cleanup.php >> /var/log/dorafiles-cleanup.log 2>&1
```

**Operations:**
- Deletes expired shared links
- Removes old revoked links (>30 days)
- Cleans up rate limit entries (>1 hour old)
- Deletes temporary files (>24 hours old)
- Removes empty temporary directories

**Output:** Logs to storage/logs/cleanup.log

---

### cleanup-logs.php
**Purpose:** Rotate and clean old activity logs

**Usage:**
```bash
# Manual execution
php bin/cleanup-logs.php

# Recommended: Set up cron job (daily at 2 AM)
0 2 * * * /usr/bin/php /path/to/htdocs/files.regaletoimagl.fr/bin/cleanup-logs.php >> /var/log/dorafiles-logs.log 2>&1
```

**Configuration:**
```env
# In .env file
ACTIVITY_LOG_RETENTION_DAYS=90
```

**Default:** Keeps last 90 days of activity logs

---

### create-test-user.php
**Purpose:** Interactive script to create test users

**Usage:**
```bash
php bin/create-test-user.php
```

**Interactive prompts:**
1. Email address
2. Password
3. FTP host
4. FTP port (default: 21)
5. FTP username
6. FTP password
7. FTP base path (default: /)

**Use cases:**
- Development and testing
- Creating initial admin account
- Adding demo users

**Security:** Only use in development - disable in production

---

## Cron Job Setup

### Complete Crontab Configuration

```bash
# Edit crontab
crontab -e

# Add these lines
# Daily cleanup at 2 AM
0 2 * * * /usr/bin/php /path/to/htdocs/files.regaletoimagl.fr/bin/cleanup.php >> /var/log/dorafiles-cleanup.log 2>&1

# Daily log rotation at 2:15 AM
15 2 * * * /usr/bin/php /path/to/htdocs/files.regaletoimagl.fr/bin/cleanup-logs.php >> /var/log/dorafiles-logs.log 2>&1
```

### Verify Cron Jobs

```bash
# List active cron jobs
crontab -l

# Check cron service status
sudo service cron status

# View cron logs
grep CRON /var/log/syslog
```

---

## Maintenance Schedule

### Daily
- `cleanup.php` - Remove expired data and temp files
- `cleanup-logs.php` - Rotate activity logs

### Weekly
- Review `storage/logs/security.log` for suspicious activity
- Check disk space usage

### Monthly
- Run `check-setup.php` to verify system health
- Review database size and optimize if needed

### After Updates
- Run `check-setup.php` to verify migrations

---

## Troubleshooting

### Script Fails with "Permission Denied"

**Solution:**
```bash
chmod +x bin/*.php
```

### Cron Jobs Not Running

**Solution:**
```bash
# Check cron service
sudo service cron status
sudo service cron start

# Verify file paths in crontab
which php  # Use this path in crontab
```

### Database Connection Errors

**Solution:**
- Verify `.env` configuration
- Check database server status
- Ensure network connectivity

### Out of Disk Space

**Solution:**
```bash
# Check disk usage
df -h

# Find large files
du -sh storage/logs/*

# Clear old logs manually if needed
rm storage/logs/security.log
rm storage/logs/cleanup.log
```

---

## Security Considerations

- **File Permissions:** Ensure scripts are not world-writable
  ```bash
  chmod 750 bin/*.php
  ```

- **Output Logs:** Rotate cron job logs to prevent disk filling
  ```bash
  # Add to logrotate
  /var/log/dorafiles-*.log {
      daily
      rotate 7
      compress
      delaycompress
      notifempty
      create 0640 www-data www-data
  }
  ```

- **Test Users:** Disable `create-test-user.php` in production
  ```bash
  # Remove execute permission
  chmod 640 bin/create-test-user.php
  ```
