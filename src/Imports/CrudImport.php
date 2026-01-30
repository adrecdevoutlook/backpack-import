<?php

namespace Adrec\BackpackImport\Imports;

use Adrec\BackpackImport\Columns\ImportColumn;
use Adrec\BackpackImport\Events\ImportCompleteEvent;
use Adrec\BackpackImport\Events\ImportRowProcessedEvent;
use Adrec\BackpackImport\Events\ImportRowSkippedEvent;
use Adrec\BackpackImport\Events\ImportStartedEvent;
use Adrec\BackpackImport\Models\ImportLog;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Row;

class CrudImport implements OnEachRow, WithHeadingRow, WithEvents
{
    protected ImportLog $importLog;
    protected array $validationRules;
    protected int $processedCount = 0;
    protected int $skippedCount = 0;

    public function __construct(ImportLog $importLog, array $validationRules = [])
    {
        $this->importLog = $importLog;
        $this->validationRules = $validationRules;
    }

    public function onRow(Row $row): void
    {
        $rowData = $row->toArray();
        $config = $this->importLog->config ?? [];

        if (empty($config)) {
            return;
        }

        // Build mapped data from file row using config
        $mappedData = $this->mapRowData($rowData, $config);

        if (empty($mappedData)) {
            $this->skippedCount++;
            ImportRowSkippedEvent::dispatch($this->importLog, $rowData, 'No data mapped');
            return;
        }

        // Validate mapped data
        if (!empty($this->validationRules)) {
            $rulesToValidate = array_intersect_key($this->validationRules, $mappedData);
            if (!empty($rulesToValidate)) {
                $validator = Validator::make($mappedData, $rulesToValidate);
                if ($validator->fails()) {
                    $this->skippedCount++;
                    ImportRowSkippedEvent::dispatch(
                        $this->importLog,
                        $rowData,
                        $validator->errors()->toJson()
                    );
                    return;
                }
            }
        }

        // Get or create model entry
        $entry = $this->getEntry($mappedData);

        // Assign mapped data to model
        foreach ($mappedData as $field => $value) {
            $entry->{$field} = $value;
        }

        $entry->save();
        $this->processedCount++;

        ImportRowProcessedEvent::dispatch($this->importLog, $entry, $rowData);
    }

    /**
     * Map raw row data using import config
     */
    protected function mapRowData(array $rowData, array $config): array
    {
        $mapped = [];
        $aliases = config('backpack.operations.import.column_aliases', []);

        foreach ($config as $mapping) {
            $fileColumn = $mapping['file_column'] ?? null;
            $fieldName = $mapping['field_name'] ?? null;
            $fieldType = $mapping['field_type'] ?? 'text';
            $fieldConfig = $mapping['field_config'] ?? [];

            if (!$fileColumn || !$fieldName) {
                continue;
            }

            // Get raw value from file row
            $rawValue = $rowData[$fileColumn] ?? null;

            // Process through column handler
            $handlerClass = $this->resolveColumnHandler($fieldType, $aliases);

            if ($handlerClass && class_exists($handlerClass)) {
                $handler = new $handlerClass(
                    $rawValue,
                    $fieldConfig,
                    $this->importLog->model
                );
                $mapped[$fieldName] = $handler->output();
            } else {
                $mapped[$fieldName] = $rawValue;
            }
        }

        return $mapped;
    }

    /**
     * Find existing entry or create new one
     */
    protected function getEntry(array $mappedData)
    {
        $modelClass = $this->importLog->model;
        $primaryKey = $this->importLog->model_primary_key;

        // Try to find existing entry by primary key
        if ($primaryKey && isset($mappedData[$primaryKey])) {
            $entry = $modelClass::where($primaryKey, $mappedData[$primaryKey])->first();
            if ($entry) {
                return $entry;
            }
        }

        // Create new instance
        $entry = new $modelClass();

        if ($primaryKey && isset($mappedData[$primaryKey])) {
            $entry->{$primaryKey} = $mappedData[$primaryKey];
        }

        return $entry;
    }

    /**
     * Resolve column handler class from type string or alias
     */
    protected function resolveColumnHandler(string $type, array $aliases): ?string
    {
        // Check if it's a full class name
        if (class_exists($type) && is_subclass_of($type, ImportColumn::class)) {
            return $type;
        }

        // Check aliases
        return $aliases[$type] ?? $aliases['text'] ?? null;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->importLog->update(['started_at' => now()]);
                ImportStartedEvent::dispatch($this->importLog);
            },
            AfterImport::class => function (AfterImport $event) {
                $this->importLog->update([
                    'completed_at' => now(),
                    'processed_rows' => $this->processedCount,
                    'skipped_rows' => $this->skippedCount,
                ]);

                // Delete file if configured
                if ($this->importLog->delete_file_after_import) {
                    $this->importLog->deleteFile();
                }

                ImportCompleteEvent::dispatch($this->importLog);
            },
        ];
    }
}
