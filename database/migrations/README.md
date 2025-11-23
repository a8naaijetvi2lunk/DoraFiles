# Database Migrations

This directory contains all database migrations for Dora Files.

## Migration Order

Migrations must be run in the following order:

1. **001_initial_schema.php** - Creates initial database tables
   - `users` table
   - `shared_links` table
   - `rate_limits` table

2. **002_profile_feature.php** - Adds profile and multi-FTP support
   - `ftp_connections` table
   - `activity_logs` table
   - Adds profile columns to `users`
   - Migrates existing FTP credentials

3. **003_two_factor_auth.php** - Adds 2FA support
   - Adds `two_factor_secret` column
   - Adds `two_factor_enabled` column
   - Adds `two_factor_backup_codes` column
   - Creates index for performance

## Running Migrations

### From Project Root

```bash
# Run individual migrations
php database/migrations/001_initial_schema.php
php database/migrations/002_profile_feature.php
php database/migrations/003_two_factor_auth.php
```

### Using Legacy Files (Root Directory)

```bash
# Legacy migration files are kept in root for backward compatibility
php migrate.php
php migrate-profile.php
php migrate-2fa.php
```

## Migration Status

To check which migrations have been applied, run:

```bash
php bin/check-setup.php
```

This will verify:
- Database connection
- Required tables existence
- Column presence
- Indexes

## Rollback

To rollback migrations, refer to the rollback sections in:
- `docs/PROFILE_FEATURE.md` - Profile feature rollback
- Individual migration files contain comments about reversing changes

## Database Schema

### Final Schema (After All Migrations)

#### users
```sql
id, email, password_hash,
ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path,
active_ftp_connection_id, last_login_at, last_login_ip,
two_factor_secret, two_factor_enabled, two_factor_backup_codes,
created_at, updated_at
```

#### ftp_connections
```sql
id, user_id, connection_name,
ftp_host, ftp_port, ftp_username, ftp_password, ftp_base_path,
is_default, last_used_at,
created_at, updated_at
```

#### activity_logs
```sql
id, user_id, action, entity_type, entity_name, details,
ip_address, user_agent, created_at
```

#### shared_links
```sql
id, user_id, token, file_path, file_name, file_size,
expires_at, download_count, last_downloaded_at,
created_at, revoked_at
```

#### rate_limits
```sql
id, ip_address, action, attempts, window_start
```

## Troubleshooting

### Migration Failed: could not find driver

**Solution:** Ensure PHP MySQL extension is installed
```bash
# Ubuntu/Debian
sudo apt-get install php-mysql

# CentOS/RHEL
sudo yum install php-mysqlnd
```

### Migration Failed: Access denied

**Solution:** Check `.env` database credentials
```env
DB_HOST=127.0.0.1
DB_DATABASE=FILES-DATA
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

### Table already exists

**Solution:** This is safe - migration will skip existing tables

### Column already exists

**Solution:** This is safe - migration will skip existing columns

## Security Notes

- All FTP credentials are encrypted using `APP_ENCRYPTION_KEY`
- 2FA secrets are encrypted before storage
- Backup codes are hashed using bcrypt
- Activity logs capture IP addresses for security auditing

## Performance

Indexes are created for:
- Email lookups (users.email)
- Token lookups (shared_links.token)
- User activity queries (activity_logs.user_id, action, created_at)
- FTP connection queries (ftp_connections.user_id, is_default)
- Rate limiting (rate_limits.ip_address, action)
- 2FA status (users.two_factor_enabled)
