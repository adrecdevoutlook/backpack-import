# Adrec Backpack Import

Import Operation for [Laravel Backpack](https://backpackforlaravel.com/) CRUD — supports CSV and Excel file imports with a 3-step wizard UI.

**Compatible with:** Laravel 12+, Backpack CRUD 7+, PHP 8.2+

## Features

- 3-step import wizard: **Upload → Map Columns → Confirm & Execute**
- Supports **CSV**, **XLS**, **XLSX** file formats
- Column type handlers: Text, Number, Boolean, Date, Array
- Auto-detect column mapping by header name
- Row-level validation using CRUD form request rules
- **Update or Create** — matches existing records by primary key
- Sync or **Queue-based** background processing
- Import history tracking via `import_logs` table
- Event hooks for custom logic (Started, RowProcessed, RowSkipped, Complete)
- Template file download support

## Requirements

- PHP >= 8.2
- Laravel >= 12.0
- Backpack CRUD >= 7.0
- maatwebsite/excel

## Installation

### Option 1: Local package (development)

Add the repository and require the package in your project's `composer.json`:

```json
{
    "require": {
        "adrec/backpack-import": "@dev"
    },
    "repositories": {
        "adrec-backpack-import": {
            "type": "path",
            "url": "packages/adrec/backpack-import"
        }
    }
}
```

Then run:

```bash
composer update adrec/backpack-import
```

### Option 2: Via Composer (when published)

```bash
composer require adrec/backpack-import
```

### Run Migration

```bash
php artisan migrate
```

### Publish Config (optional)

```bash
php artisan vendor:publish --tag=backpack-import-config
```

### Publish Views (optional)

```bash
php artisan vendor:publish --tag=backpack-import-views
```

## Usage

### Basic Usage

Add the `ImportOperation` trait to your CrudController:

```php
<?php

namespace App\Http\Controllers\Admin;

use Backpack\CRUD\app\Http\Controllers\CrudController;
use Adrec\BackpackImport\ImportOperation;

class MemberCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use ImportOperation;

    public function setup()
    {
        $this->crud->setModel(\App\Models\Member::class);
        $this->crud->setRoute(config('backpack.base.route_prefix') . '/member');
        $this->crud->setEntityNameStrings('member', 'members');
    }

    // ... your other setup methods
}
```

That's it! An **"Import members"** button will appear on the list view.

### Advanced Configuration

Override `setupImportOperation()` in your controller to customize behavior:

```php
protected function setupImportOperation()
{
    // Process imports in background queue
    $this->queueImport();

    // Delete uploaded file after import completes
    $this->deleteFileAfterImport();

    // Allow import without matching primary key (always create new records)
    $this->withoutPrimaryKey();

    // Skip the column mapping step (auto-map by header name)
    $this->disableUserMapping();

    // Provide a template file for users to download
    $this->setExampleFileUrl(asset('templates/members-template.xlsx'));

    // Use a custom import handler class
    $this->setImportHandler(\App\Imports\CustomMemberImport::class);
}
```

### Column Types

During the mapping step, values are processed through column handlers based on CRUD column types:

| Type | Class | Description |
|------|-------|-------------|
| `text` | `TextColumn` | Returns string as-is |
| `number` | `NumberColumn` | Converts to float, null if non-numeric |
| `boolean` | `BooleanColumn` | Parses `true/1/yes/y/on` as true |
| `date` | `DateColumn` | Auto-detects date formats, supports Excel serial dates |
| `array` | `ArrayColumn` | Splits by separator, maps to option keys |

### Custom Column Handler

Create your own column handler by extending `ImportColumn`:

```php
<?php

namespace App\Imports\Columns;

use Adrec\BackpackImport\Columns\ImportColumn;

class PhoneColumn extends ImportColumn
{
    public function output(): mixed
    {
        // Remove all non-digit characters
        return preg_replace('/\D/', '', (string) $this->data);
    }

    public function getName(): string
    {
        return 'Phone';
    }
}
```

Register it in `config/backpack/operations/import.php`:

```php
'column_aliases' => [
    // ... default aliases
    'phone' => \App\Imports\Columns\PhoneColumn::class,
],
```

### Events

Listen to import lifecycle events for custom logic:

```php
use Adrec\BackpackImport\Events\ImportStartedEvent;
use Adrec\BackpackImport\Events\ImportCompleteEvent;
use Adrec\BackpackImport\Events\ImportRowProcessedEvent;
use Adrec\BackpackImport\Events\ImportRowSkippedEvent;

// In EventServiceProvider or listener
Event::listen(ImportCompleteEvent::class, function ($event) {
    $log = $event->importLog;
    // Send notification, log results, etc.
    logger("Import completed: {$log->processed_rows} processed, {$log->skipped_rows} skipped");
});

Event::listen(ImportRowSkippedEvent::class, function ($event) {
    logger("Row skipped: {$event->reason}", $event->rowData);
});
```

### Routes

The trait automatically registers these routes:

| Method | URI | Description |
|--------|-----|-------------|
| GET | `/admin/{entity}/import` | Step 1: File upload form |
| POST | `/admin/{entity}/import` | Handle file upload |
| GET | `/admin/{entity}/import/{id}/map` | Step 2: Column mapping |
| POST | `/admin/{entity}/import/{id}/map` | Save column mapping |
| GET | `/admin/{entity}/import/{id}/confirm` | Step 3: Review & confirm |
| POST | `/admin/{entity}/import/{id}/confirm` | Execute import |

## Configuration

Published config file `config/backpack/operations/import.php`:

```php
return [
    // Model for tracking import history
    'import_log_model' => \Adrec\BackpackImport\Models\ImportLog::class,

    // Storage disk for uploaded files
    'disk' => env('FILESYSTEM_DISK', 'local'),

    // Path within disk
    'path' => env('BACKPACK_IMPORT_FILE_PATH', 'imports'),

    // Queue connection for background imports
    'queue' => env('QUEUE_CONNECTION', 'sync'),

    // Rows per chunk for queued imports
    'chunk_size' => env('BACKPACK_IMPORT_CHUNK_SIZE', 100),

    // Column type aliases
    'column_aliases' => [
        'text'    => \Adrec\BackpackImport\Columns\TextColumn::class,
        'number'  => \Adrec\BackpackImport\Columns\NumberColumn::class,
        'boolean' => \Adrec\BackpackImport\Columns\BooleanColumn::class,
        'date'    => \Adrec\BackpackImport\Columns\DateColumn::class,
        'array'   => \Adrec\BackpackImport\Columns\ArrayColumn::class,
    ],
];
```

## Package Structure

```
packages/adrec/backpack-import/
├── composer.json
├── README.md
├── config/
│   └── backpack-import.php
├── database/
│   └── migrations/
│       └── create_import_logs_table.php
├── resources/
│   └── views/
│       ├── buttons/import.blade.php
│       ├── select-file.blade.php
│       ├── map-fields.blade.php
│       └── confirm-import.blade.php
└── src/
    ├── AdrecImportServiceProvider.php
    ├── ImportOperation.php
    ├── Columns/
    │   ├── ImportColumn.php
    │   ├── TextColumn.php
    │   ├── NumberColumn.php
    │   ├── BooleanColumn.php
    │   ├── DateColumn.php
    │   └── ArrayColumn.php
    ├── Events/
    │   ├── ImportStartedEvent.php
    │   ├── ImportCompleteEvent.php
    │   ├── ImportRowProcessedEvent.php
    │   └── ImportRowSkippedEvent.php
    ├── Imports/
    │   ├── CrudImport.php
    │   └── QueuedCrudImport.php
    ├── Models/
    │   └── ImportLog.php
    └── Requests/
        └── ImportFileRequest.php
```

## License

MIT

## Developer

**Adrec** — [dev@adrec.com.vn](mailto:dev@adrec.com.vn)
