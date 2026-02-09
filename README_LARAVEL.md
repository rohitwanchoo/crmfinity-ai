# CRMfinity AI - Laravel 12 Conversion

## ✅ Completed

### 1. Laravel 12 Installation
- **Location**: `/var/www/html/crmfinity_laravel`
- **Version**: Laravel 12.42.0 (Latest Framework)
- **Database**: MySQL - `email_storage` (shared with legacy app)

### 2. Environment Configuration (`.env`)
```
DB_CONNECTION=mysql
DB_DATABASE=email_storage
OPENAI_API_KEY=configured
```

### 3. Database Migrations
All 7 training tables migrated:
- users, training_sessions, ground_truth_transactions
- learned_patterns, merchant_profiles, bank_layout_patterns
- training_metrics, prediction_log

### 4. Eloquent Models Created
- `TrainingSession` - with User relationship
- `GroundTruthTransaction` - with TrainingSession relationship
- `LearnedPattern` - with JSON casting
- `MerchantProfile` - with array casting
- `BankLayoutPattern`

### 5. Assets Migrated
- Uploads: `storage/app/uploads/`
- Scripts: `storage/app/scripts/` (includes extract_pdf_text.py)

## 🔄 Next Steps

### Install Laravel Breeze (Authentication)
```bash
cd /var/www/html/crmfinity_laravel
composer require laravel/breeze --dev
php artisan breeze:install blade
npm install && npm run build
php artisan migrate
```

### Create Controllers
```bash
php artisan make:controller TrainingController
php artisan make:controller UnderwritingController
php artisan make:controller DashboardController
```

### Create Service for TrainingEngine
```bash
mkdir -p app/Services
# Copy and adapt TrainingEngine.php to app/Services/TrainingEngineService.php
```

### Set Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Running the Application

### Development Server
```bash
php artisan serve
# Access: http://localhost:8000
```

### Production
Set Apache/Nginx document root to:
```
/var/www/html/crmfinity_laravel/public
```

## File Structure

```
crmfinity_laravel/
├── app/
│   ├── Http/Controllers/     # Create controllers here
│   ├── Models/               # ✅ Eloquent models created
│   └── Services/             # TrainingEngine service goes here
├── database/
│   ├── migrations/           # ✅ All migrations created
│   └── seeders/              # Create admin seeder
├── resources/views/          # Create Blade templates
├── routes/web.php            # Define routes
├── storage/app/
│   ├── uploads/              # ✅ Copied from legacy
│   └── scripts/              # ✅ PDF extraction scripts
└── .env                      # ✅ Configured
```

## Comparison: Legacy vs Laravel

| Feature | Legacy | Laravel |
|---------|--------|---------|
| Structure | Procedural | MVC |
| Database | mysqli | Eloquent ORM |
| Security | Manual | Built-in CSRF, XSS protection |
| Templates | PHP echo | Blade templating |
| Routing | Multiple files | Centralized routes |
| Auth | Custom auth.php | Laravel Breeze |
| Queues | None | Built-in queue system |

## Quick Commands

```bash
# Clear cache
php artisan config:clear && php artisan cache:clear

# Database check
php artisan tinker
>>> \App\Models\User::count()
>>> \App\Models\TrainingSession::count()

# Create admin user (in tinker)
>>> \App\Models\User::create(['username'=>'admin', 'email'=>'admin@example.com', 'password'=>bcrypt('admin123'), 'is_active'=>true])
```

## Notes

- Both legacy and Laravel apps can run simultaneously
- Using same database - no data migration needed
- All existing uploads/scripts preserved
- OpenAI API key configured in `.env`

**Status**: Core framework complete - ready for controllers, routes, and views implementation
