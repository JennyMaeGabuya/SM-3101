# Bizmo - Learning Management System

**Bizmo** (Blended Interactive Zone for Modern Learning) is a modern Learning Management System designed to make education accessible, engaging, and effective. The system provides interactive quizzes, flashcards, progress tracking, study planning, and social learning features.

## 🎯 Features

- **Interactive Learning**: Engage with interactive quizzes, flashcards, and practice sessions
- **Deck Management**: Create, organize, and share study decks with customizable icons
- **Progress Tracking**: Monitor your learning journey with detailed analytics and progress reports
- **Study Planner**: Plan your study schedule with a built-in calendar and planner
- **Social Learning**: Connect with friends, share decks, and collaborate on learning
- **Email Integration**: Send and receive emails through the admin panel (IMAP support)
- **Admin Dashboard**: Comprehensive admin panel for user management, reports, and system administration
- **User Profiles**: Customizable user profiles with profile pictures and settings

## 📋 System Requirements

### Required Software

1. **XAMPP** (or similar LAMP/WAMP stack)
   - **Download**: [https://www.apachefriends.org/download.html](https://www.apachefriends.org/download.html)
   - Includes:
     - Apache Web Server
     - MySQL/MariaDB Database
     - PHP
     - phpMyAdmin

2. **PHP Version**: PHP 5.5.0 or higher (PHP 7.4+ recommended)
   - Check your PHP version: `php -v`

3. **MySQL/MariaDB**: Included with XAMPP
   - Database name: `bizmo`

### Optional but Recommended

4. **PHP IMAP Extension** (for email functionality)
   - See `ENABLE_IMAP_GUIDE.md` for detailed setup instructions
   - Required for receiving emails via IMAP
   - Not required for sending emails (uses PHPMailer)

## 📥 What to Download

### 1. XAMPP
- **Download Link**: [https://www.apachefriends.org/download.html](https://www.apachefriends.org/download.html)
- **Version**: Latest stable version
- **Platform**: Windows (xampp-windows-x64-*-installer.exe)
- **Size**: ~150 MB

### 2. PHP IMAP Extension (Optional)
- Only needed if you want to receive emails via IMAP
- Usually included with XAMPP, just needs to be enabled
- See `ENABLE_IMAP_GUIDE.md` for instructions

### 3. Dependencies
All other dependencies are included in this repository:
- ✅ **PHPMailer**: Already included in `/phpmailer` directory
- ✅ **Fonts**: Loaded from Google Fonts (Inter font family)
- ✅ **Icons & Assets**: Included in `/icons` and `/assets` directories

## 🚀 Installation Guide

### Step 1: Install XAMPP

1. Download XAMPP from the official website
2. Run the installer
3. Install to default location: `C:\xampp\`
4. During installation, select:
   - ✅ Apache
   - ✅ MySQL
   - ✅ PHP
   - ✅ phpMyAdmin

### Step 2: Place Project Files

1. Copy the entire `SIA-FINAL` folder to:
   ```
   C:\xampp\htdocs\SIA-FINAL
   ```

2. Or if you're already in `C:\xampp\htdocs\SIA-FINAL`, you're all set!

### Step 3: Start XAMPP Services

1. Open **XAMPP Control Panel**
2. Click **Start** for:
   - ✅ Apache
   - ✅ MySQL

3. Verify services are running (green indicators)

### Step 4: Create Database

1. Open phpMyAdmin: [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
2. Click **New** in the left sidebar
3. Create a new database named: `bizmo`
4. Select **utf8mb4_unicode_ci** as collation
5. Click **Create**

### Step 5: Run Database Migrations

1. In phpMyAdmin, select the `bizmo` database
2. Go to the **SQL** tab
3. Run each migration file in order from the `/migrations` folder:

   **Required Migrations:**
   - `20250121-create-admin-table.sql`
   - `20250122-create-emails-table.sql`
   - `20250123-create-incoming-emails-table.sql`
   - `20250124-add-reply-to-email-id.sql`
   - `20250125-add-deleted-column-to-incoming-emails.sql`
   - `202501-alter-decks-icon-blob.sql`
   - `202502-create-deck-shares-table.sql`
   - `202502-create-planners-table.sql`
   - `20250220-add-recipient-deck-id.sql`

4. **Note**: Some tables may be created automatically by the application on first use

### Step 6: Configure Database Connection

1. Open `config.php` in the root directory
2. Update database credentials if needed (default XAMPP settings):
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');  // Empty by default in XAMPP
   define('DB_NAME', 'bizmo');
   ```

### Step 7: Enable IMAP Extension (Optional)

If you want email receiving functionality:

1. Follow the detailed guide in `ENABLE_IMAP_GUIDE.md`
2. Or manually:
   - Open `C:\xampp\php\php.ini`
   - Find `;extension=imap`
   - Remove the semicolon: `extension=imap`
   - Restart Apache

### Step 8: Set File Permissions

Ensure the following directories are writable:
- `uploads/profiles/` - For user profile pictures
- `uploads/deck-icons/` - For deck icons

**Windows**: Usually no action needed, but if you encounter issues, right-click the folder → Properties → Security → Edit permissions

### Step 9: Access the Application

1. Open your web browser
2. Navigate to: [http://localhost/SIA-FINAL](http://localhost/SIA-FINAL)
3. You should see the Bizmo landing page

## 🔐 Default Admin Credentials

After running the admin table migration:

- **Username**: `admin`
- **Email**: `admin@bizmo.com`
- **Password**: `admin123`

**⚠️ Security Note**: Change the default admin password immediately after first login!

To access admin panel:
- URL: [http://localhost/SIA-FINAL/admin/admin_login.php](http://localhost/SIA-FINAL/admin/admin_login.php)

## 📁 Project Structure

```
SIA-FINAL/
├── admin/                 # Admin panel files
│   ├── admin_dashboard.php
│   ├── admin_login.php
│   ├── admin_users.php
│   └── ...
├── assets/                # Static assets
├── icons/                 # Application icons
├── migrations/            # Database migration files
├── phpmailer/            # PHPMailer library
├── practice/             # Practice/OTP functionality
├── sounds/               # Audio files for quizzes
├── uploads/              # User-uploaded files
│   ├── profiles/         # User profile pictures
│   └── deck-icons/       # Deck icons
├── videos/               # Video assets
├── config.php            # Database configuration
├── index.php             # Landing page
├── login.php             # User login
├── dashboard.php         # User dashboard
├── decks.php             # Deck management
├── quiz.php              # Quiz functionality
├── planner.php           # Study planner
├── friends.php           # Social features
├── progress.php          # Progress tracking
└── README.md             # This file
```

## 🛠️ Configuration

### Database Configuration
Edit `config.php` to change database settings:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bizmo');
```

### Email Configuration
Email settings are configured in admin panel files. Update SMTP settings in:
- `admin/admin_email.php` (for sending emails)
- `admin/get_incoming_email.php` (for receiving emails via IMAP)

## 🧪 Testing

### Test Database Connection
Create a test file `test_db.php`:
```php
<?php
require_once 'config.php';
if ($conn) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
?>
```

### Test IMAP (if enabled)
Use the included `test_imap.php` file:
- Navigate to: [http://localhost/SIA-FINAL/test_imap.php](http://localhost/SIA-FINAL/test_imap.php)

## 📚 Key Features Explained

### User Features
- **Dashboard**: Overview of decks, progress, and recent activity
- **Decks**: Create and manage study decks with flashcards
- **Quiz**: Interactive quiz mode with audio feedback
- **Progress**: Track learning statistics and achievements
- **Planner**: Schedule study sessions and set goals
- **Friends**: Connect with other learners and share decks
- **Favorites**: Save favorite decks for quick access
- **Settings**: Customize profile, theme, and preferences

### Admin Features
- **User Management**: View, manage, and monitor users
- **Deck Management**: View all user decks and statistics
- **Email System**: Send emails to users and receive incoming emails
- **Reports**: View system statistics and user activity
- **Dashboard**: Overview of system health and metrics

## 🔧 Troubleshooting

### Apache won't start
- Check if port 80 is in use (Skype, IIS, etc.)
- Change Apache port in XAMPP Control Panel → Config → Apache (httpd.conf)
- Look for port conflicts: `netstat -ano | findstr :80`

### MySQL won't start
- Check if port 3306 is in use
- Check MySQL error logs: `C:\xampp\mysql\data\*.err`
- Try stopping and restarting the service

### Database connection errors
- Verify MySQL is running
- Check database name in `config.php`
- Ensure database `bizmo` exists
- Verify username/password in `config.php`

### IMAP not working
- See `ENABLE_IMAP_GUIDE.md` for detailed troubleshooting
- Verify `php_imap.dll` exists in `C:\xampp\php\ext\`
- Check that `extension=imap` is uncommented in `php.ini`
- Restart Apache after changes

### File upload issues
- Check `uploads/` directory permissions
- Verify `upload_max_filesize` in `php.ini`
- Check `post_max_size` in `php.ini`

## 📝 Development Notes

- **PHP Version**: Compatible with PHP 5.5.0+, tested on PHP 7.4+
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Character Encoding**: UTF-8 (utf8mb4)
- **Session Management**: PHP sessions used for authentication
- **Password Hashing**: Uses PHP `password_hash()` with bcrypt

## 🤝 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review migration files in `/migrations` directory
3. Check Apache error logs: `C:\xampp\apache\logs\error.log`
4. Check PHP error logs: `C:\xampp\php\logs\php_error_log`

## 📄 License

This project is part of the SIA (System Integration and Application) Final project.

## 🎓 Credits

**Bizmo** - Blended Interactive Zone for Modern Learning
A modern Learning Management System for effective education.

---

**Last Updated**: 2025
**Version**: 1.0
