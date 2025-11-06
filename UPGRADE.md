# Upgrade Guide - Laravel Job Status

## Upgrading to Laravel 12 / PHP 8.3

This package has been completely modernized to support Laravel 12 and PHP 8.3 with breaking changes.

### Requirements

**Before:**
- PHP >= 7.1
- Laravel/Lumen >= 5.5

**After:**
- PHP ^8.3
- Laravel ^12.0

### Breaking Changes

#### 1. JobStatusEnum Introduction

The `status` field now uses a backed enum instead of string constants.

**Before:**
```php
use Imtigger\LaravelJobStatus\JobStatus;

if ($jobStatus->status === JobStatus::STATUS_FINISHED) {
    // ...
}
```

**After:**
```php
use Imtigger\LaravelJobStatus\JobStatus;
use Imtigger\LaravelJobStatus\Enums\JobStatusEnum;

// Option 1: Use enum methods
if ($jobStatus->status->isFinished()) {
    // ...
}

// Option 2: Compare with enum
if ($jobStatus->status === JobStatusEnum::FINISHED) {
    // ...
}

// Option 3: Use backward-compatible constants (will be removed in future)
if ($jobStatus->status->value === JobStatus::STATUS_FINISHED) {
    // ...
}
```

#### 2. Database Schema Changes

The migration now uses:
- `json` columns instead of `longText` for input/output (automatic casting)
- `id()` instead of `increments('id')`
- `unsignedInteger()` instead of `integer()` for progress fields
- Anonymous class format

If upgrading an existing installation, no migration changes are required as Laravel handles JSON casting automatically.

#### 3. Type Declarations

All classes now use strict type declarations. If you're extending any classes, ensure your methods have proper type hints:

```php
// Your custom JobStatus model
namespace App\Models;

use Imtigger\LaravelJobStatus\JobStatus as BaseJobStatus;

class JobStatus extends BaseJobStatus
{
    // Add strict types if extending
    public function customMethod(): string
    {
        return 'custom';
    }
}
```

#### 4. Model Attribute Accessors

Accessors have been updated to use Laravel 11+ Attribute objects. If you were overriding accessors:

**Before:**
```php
public function getProgressPercentageAttribute()
{
    return custom_calculation();
}
```

**After:**
```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function progressPercentage(): Attribute
{
    return Attribute::make(
        get: fn (): float => custom_calculation(),
    );
}
```

#### 5. Service Provider Changes

- `$defer` property removed (deprecated in Laravel 11)
- `provides()` method removed (deprecated in Laravel 11)

If you registered providers manually in `config/app.php`, they should still work via auto-discovery.

#### 6. Constructor Property Promotion

When creating jobs, you can now use constructor property promotion:

**Before:**
```php
class MyJob implements ShouldQueue
{
    use Trackable;
    
    protected $params;
    
    public function __construct(array $params)
    {
        $this->params = $params;
        $this->prepareStatus();
    }
}
```

**After:**
```php
class MyJob implements ShouldQueue
{
    use Trackable;
    
    public function __construct(private readonly array $params)
    {
        $this->prepareStatus();
    }
}
```

### New Features

#### JobStatusEnum Methods

```php
$status = JobStatusEnum::FINISHED;

$status->isFinished();    // true
$status->isFailed();      // false
$status->isEnded();       // true
$status->isExecuting();   // false
$status->isQueued();      // false
$status->isRetrying();    // false
```

#### Modern Type Safety

All methods now have proper return type declarations:
- `prepareStatus(array $data = []): void`
- `setProgressMax(int $value): void`
- `setProgressNow(int $value, int $every = 1): void`
- `incrementProgress(int $offset = 1, int $every = 1): void`
- `setInput(array $value): void`
- `setOutput(array $value): void`
- `getJobStatusId(): int|string|null`

### Lumen Support

Lumen support has been removed as Lumen is no longer maintained by Laravel.

### Testing

If you have tests that use this package:

1. Update PHPUnit to ^11.0
2. Update Orchestra Testbench to ^9.0
3. Ensure test classes use strict types
4. Update PHPUnit configuration to version 11 format

### Code Style

The package now includes Laravel Pint configuration. Run formatting:

```bash
composer lint
```

### Recommended Actions

1. **Update your code:**
   - Add `declare(strict_types=1)` to files using the package
   - Update status comparisons to use enum
   - Add return type hints to job handle methods

2. **Test thoroughly:**
   - Test job dispatching
   - Test progress tracking
   - Test status retrieval
   - Test failed job handling

3. **Update dependencies:**
   ```bash
   composer require imtigger/laravel-job-status:^2.0
   ```

### Need Help?

If you encounter issues during the upgrade, please check:
- The updated [README.md](README.md) for usage examples
- The [INSTALL.md](INSTALL.md) for installation instructions
- Open an issue on GitHub for support
