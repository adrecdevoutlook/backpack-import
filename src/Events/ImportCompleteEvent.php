<?php

namespace Adrec\BackpackImport\Events;

use Adrec\BackpackImport\Models\ImportLog;
use Illuminate\Foundation\Events\Dispatchable;

class ImportCompleteEvent
{
    use Dispatchable;

    public function __construct(
        public ImportLog $importLog
    ) {}
}
