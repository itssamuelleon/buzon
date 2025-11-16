# Email Queue Setup

## Overview
The email queue system allows sending department notifications asynchronously without blocking the user interface. Emails are queued and processed in the background.

## Database Setup

First, create the email queue table by running the SQL file:

```bash
mysql -u root -p buzon_quejas < create_email_queue.sql
```

Or execute the SQL directly:

```sql
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    department_id INT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    INDEX idx_status (status),
    INDEX idx_complaint (complaint_id)
);
```

## Processing Methods

### Option 1: Cron Job (Recommended for Production)

Add a cron job to process the email queue periodically:

```bash
# Process emails every 5 minutes
*/5 * * * * php /path/to/buzon/process_email_queue.php

# Or process every minute for faster delivery
* * * * * php /path/to/buzon/process_email_queue.php
```

### Option 2: Async HTTP Request (Current Implementation)

The system automatically triggers `process_email_queue.php` asynchronously when emails are queued. This uses a non-blocking curl request with a 100ms timeout.

**Note:** This method works best with:
- Apache with mod_curl
- Nginx with proper timeout settings
- PHP-FPM with sufficient worker processes

### Option 3: Manual Trigger

You can manually process the queue by visiting:

```
http://localhost/buzon/process_email_queue.php
```

Or via CLI:

```bash
php /path/to/buzon/process_email_queue.php
```

## How It Works

1. **User assigns departments** → Emails are queued instead of sent immediately
2. **Page redirects immediately** → User sees success message without waiting
3. **Background process** → `process_email_queue.php` sends emails asynchronously
4. **Retry logic** → Failed emails are retried up to 3 times
5. **Error tracking** → Failed emails are logged with error messages

## Queue Status

Check the email queue status:

```sql
-- View pending emails
SELECT * FROM email_queue WHERE status = 'pending';

-- View sent emails
SELECT * FROM email_queue WHERE status = 'sent';

-- View failed emails
SELECT * FROM email_queue WHERE status = 'failed';

-- View retry attempts
SELECT id, complaint_id, department_id, attempts, max_attempts, error_message 
FROM email_queue 
WHERE status = 'failed' AND attempts >= max_attempts;
```

## Configuration

The queue processor processes up to 10 emails per run. To change this, edit `process_email_queue.php`:

```php
// Change LIMIT value
LIMIT 10  // Process 10 emails per run
```

## Troubleshooting

### Emails not being sent
1. Check if `email_queue` table exists
2. Verify cron job is running: `grep CRON /var/log/syslog`
3. Check PHP error logs
4. Test manually: `php process_email_queue.php`

### High failure rate
1. Check SMTP configuration in `config.php`
2. Verify department email addresses are correct
3. Check email server logs
4. Review error messages in `email_queue.error_message`

### Performance issues
1. Reduce `LIMIT` in `process_email_queue.php` if processing takes too long
2. Increase cron frequency for faster delivery
3. Monitor database indexes on `email_queue` table

## Files Created

- `create_email_queue.sql` - Database table creation script
- `process_email_queue.php` - Background email processor
- `EMAIL_QUEUE_SETUP.md` - This documentation
