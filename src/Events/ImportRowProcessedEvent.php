<?php

namespace Adrec\BackpackImport\Events;

use Adrec\BackpackImport\Models\ImportLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

class ImportRowProcessedEvent
{
    use Dispatchable;

    public function __construct(
        public ImportLog $importLog,
        public Model $entry,
        public array $rowData
    ) {}
}
