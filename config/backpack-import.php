<?php

return [
    // Model class for import log tracking
    'import_log_model' => \Adrec\BackpackImport\Models\ImportLog::class,

    // Storage disk for uploaded import files
    'disk' => env('FILESYSTEM_DISK', 'local'),

    // Path within disk to store import files
    'path' => env('BACKPACK_IMPORT_FILE_PATH', 'imports'),

    // Queue connection for queued imports
    'queue' => env('QUEUE_CONNECTION', 'sync'),

    // Number of rows per chunk for queued imports
    'chunk_size' => env('BACKPACK_IMPORT_CHUNK_SIZE', 100),

    // Column type aliases (shorthand names)
    'column_aliases' => [
        'text' => \Adrec\BackpackImport\Columns\TextColumn::class,
        'number' => \Adrec\BackpackImport\Columns\NumberColumn::class,
        'boolean' => \Adrec\BackpackImport\Columns\BooleanColumn::class,
        'date' => \Adrec\BackpackImport\Columns\DateColumn::class,
        'array' => \Adrec\BackpackImport\Columns\ArrayColumn::class,
    ],
];
