# Installation - Laravel

## Requirements

- PHP ^8.3
- Laravel ^12.0

## Installation Steps

### 1. Install via Composer

```bash
composer require imtigger/laravel-job-status
```

The package will be automatically discovered by Laravel.

### 2. Run Migrations

```bash
php artisan migrate
```

This will create the `job_statuses` table in your database.

### 3. Publish Configuration (Optional)

If you need to customize the configuration:

```bash
php artisan vendor:publish --provider="Imtigger\LaravelJobStatus\LaravelJobStatusServiceProvider" --tag="config"
```

This will create `config/job-status.php`.

### 4. Publish Migrations (Optional)

If you need to customize the migration:

```bash
php artisan vendor:publish --provider="Imtigger\LaravelJobStatus\LaravelJobStatusServiceProvider" --tag="migrations"
```

## Advanced Configuration

### Custom JobStatus Model

To use your own JobStatus model, update `config/job-status.php`:

```php
return [
    'model' => App\Models\JobStatus::class,
    // ...
];
```

### Immediate Job ID Capture

By default, the job_id is captured when the job starts processing. To capture it immediately upon dispatch, add the Bus Service Provider to your `config/app.php`:

```php
'providers' => [
    // ...
    Imtigger\LaravelJobStatus\LaravelJobStatusBusServiceProvider::class,
],
```

### Dedicated Database Connection

Laravel supports only one transaction per database connection. JobStatus updates within a transaction are invisible to other connections (e.g., progress monitoring pages) until the transaction commits.

To make JobStatus updates visible immediately:

1. Add a new connection in `config/database.php`:

```php
'connections' => [
    // ... existing connections
    
    'mysql-job-status' => [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'forge'),
        'username' => env('DB_USERNAME', 'forge'),
        'password' => env('DB_PASSWORD', ''),
        // ... other mysql config
    ],
],
```

2. Update `config/job-status.php`:

```php
return [
    'database_connection' => 'mysql-job-status',
    // ...
];
```

### Custom Event Manager

To use a different event manager (e.g., LegacyEventManager), update `config/job-status.php`:

```php
return [
    'event_manager' => Imtigger\LaravelJobStatus\EventManagers\LegacyEventManager::class,
    // ...
];
```
