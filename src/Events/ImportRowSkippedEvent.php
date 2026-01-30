<?php

namespace Adrec\BackpackImport\Events;

use Adrec\BackpackImport\Models\ImportLog;
use Illuminate\Foundation\Events\Dispatchable;

class ImportRowSkippedEvent
{
    use Dispatchable;

    public function __construct(
        public ImportLog $importLog,
        public array $rowData,
        public string $reason = ''
    ) {}
}
