<?php

namespace Adrec\BackpackImport;

use Adrec\BackpackImport\Models\ImportLog;
use Adrec\BackpackImport\Imports\CrudImport;
use Adrec\BackpackImport\Imports\QueuedCrudImport;
use Adrec\BackpackImport\Requests\ImportFileRequest;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;

trait ImportOperation
{
    /**
     * Register import routes
     */
    protected function setupImportRoutes($segment, $routeName, $controller)
    {
        Route::get($segment . '/import', [
            'as' => $routeName . '.import',
            'uses' => $controller . '@selectFile',
            'operation' => 'import',
        ]);

        Route::post($segment . '/import', [
            'as' => $routeName . '.import.handleFile',
            'uses' => $controller . '@handleFile',
            'operation' => 'import',
        ]);

        Route::get($segment . '/import/{id}/map', [
            'as' => $routeName . '.import.map',
            'uses' => $controller . '@mapFields',
            'operation' => 'import',
        ]);

        Route::post($segment . '/import/{id}/map', [
            'as' => $routeName . '.import.handleMapping',
            'uses' => $controller . '@handleMapping',
            'operation' => 'import',
        ]);

        Route::get($segment . '/import/{id}/confirm', [
            'as' => $routeName . '.import.confirm',
            'uses' => $controller . '@confirmImport',
            'operation' => 'import',
        ]);

        Route::post($segment . '/import/{id}/confirm', [
            'as' => $routeName . '.import.handleImport',
            'uses' => $controller . '@handleImport',
            'operation' => 'import',
        ]);
    }

    /**
     * Setup default configuration for import operation
     */
    protected function setupImportDefaults()
    {
        $this->crud->allowAccess('import');

        $this->crud->operation('import', function () {
            $this->crud->setOperationSetting('queued', false);
            $this->crud->setOperationSetting('deleteFileAfterImport', false);
            $this->crud->setOperationSetting('withoutPrimaryKey', false);
            $this->crud->setOperationSetting('userMapping', true);
            $this->crud->setOperationSetting('exampleFileUrl', null);
            $this->crud->setOperationSetting('importHandler', null);
        });

        $this->crud->operation('list', function () {
            $this->crud->addButton('top', 'import', 'view', 'adrec.backpack-import::buttons.import');
        });
    }

    /**
     * Setup import operation - override in controller for custom columns
     */
    protected function setupImportOperation()
    {
        // Override in your controller to configure import columns
    }

    // ==============================
    // Step 1: Select File
    // ==============================

    public function selectFile()
    {
        $this->crud->hasAccessOrFail('import');
        $this->crud->setOperation('import');
        $this->setupImportConfig();

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Import ' . $this->crud->entity_name_plural;
        $this->data['exampleFileUrl'] = $this->crud->getOperationSetting('exampleFileUrl') ?? null;

        return view('adrec.backpack-import::select-file', $this->data);
    }

    public function handleFile()
    {
        $this->crud->hasAccessOrFail('import');

        $request = app(ImportFileRequest::class);

        $disk = config('backpack.operations.import.disk', 'local');
        $path = config('backpack.operations.import.path', 'imports');

        // Store the file
        $filePath = $request->file('import_file')->store($path, $disk);

        // Get primary key
        $primaryKey = $this->getImportPrimaryKey();

        // Create import log
        $importLogModel = config('backpack.operations.import.import_log_model', ImportLog::class);
        $importLog = $importLogModel::create([
            'user_id' => backpack_auth()->id(),
            'file_path' => $filePath,
            'disk' => $disk,
            'model' => get_class($this->crud->getModel()),
            'model_primary_key' => $primaryKey,
            'delete_file_after_import' => $this->crud->getOperationSetting('deleteFileAfterImport') ?? false,
        ]);

        // Check if user mapping is disabled
        $userMapping = $this->crud->getOperationSetting('userMapping') ?? true;

        if (!$userMapping) {
            // Auto-map: use file headings as field names
            $headings = $this->getFileHeadings($importLog);
            $config = [];
            foreach ($headings as $heading) {
                $config[] = [
                    'file_column' => $heading,
                    'field_name' => $heading,
                    'field_type' => 'text',
                    'field_config' => [],
                ];
            }
            $importLog->update(['config' => $config]);
            return redirect(url($this->crud->route . '/import/' . $importLog->id . '/confirm'));
        }

        return redirect(url($this->crud->route . '/import/' . $importLog->id . '/map'));
    }

    // ==============================
    // Step 2: Map Fields
    // ==============================

    public function mapFields($id)
    {
        $this->crud->hasAccessOrFail('import');
        $this->crud->setOperation('import');
        $this->setupImportConfig();

        $importLogModel = config('backpack.operations.import.import_log_model', ImportLog::class);
        $importLog = $importLogModel::findOrFail($id);

        // Get file headings
        $headings = $this->getFileHeadings($importLog);

        // Get CRUD columns for mapping
        $crudColumns = $this->getImportColumns();

        // Get required columns from form validation
        $requiredColumns = $this->getRequiredImportColumns();

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Map Import Fields';
        $this->data['importLog'] = $importLog;
        $this->data['headings'] = $headings;
        $this->data['crudColumns'] = $crudColumns;
        $this->data['requiredColumns'] = $requiredColumns;
        $this->data['primaryKey'] = $importLog->model_primary_key;

        return view('adrec.backpack-import::map-fields', $this->data);
    }

    public function handleMapping($id)
    {
        $this->crud->hasAccessOrFail('import');
        $this->crud->setOperation('import');
        $this->setupImportConfig();

        $importLogModel = config('backpack.operations.import.import_log_model', ImportLog::class);
        $importLog = $importLogModel::findOrFail($id);

        $mappings = request()->input('mappings', []);
        $primaryKey = $importLog->model_primary_key;
        $requiredColumns = $this->getRequiredImportColumns();

        // Validate mappings
        $config = [];
        $mappedFields = [];

        foreach ($mappings as $fieldName => $fileColumn) {
            if (empty($fileColumn) || $fileColumn === '__skip__') {
                continue;
            }

            $crudColumns = $this->getImportColumns();
            $fieldConfig = $crudColumns[$fieldName] ?? [];

            $config[] = [
                'file_column' => $fileColumn,
                'field_name' => $fieldName,
                'field_type' => $fieldConfig['type'] ?? 'text',
                'field_config' => $fieldConfig,
            ];

            $mappedFields[] = $fieldName;
        }

        // Check at least one mapping
        if (empty($config)) {
            return redirect()->back()->with('error', 'Please map at least one column.');
        }

        // Check primary key mapped (unless disabled)
        $withoutPrimaryKey = $this->crud->getOperationSetting('withoutPrimaryKey') ?? false;
        if (!$withoutPrimaryKey && $primaryKey && !in_array($primaryKey, $mappedFields)) {
            return redirect()->back()->with('error', "Primary key '{$primaryKey}' must be mapped.");
        }

        // Check required fields
        $missingRequired = array_diff($requiredColumns, $mappedFields);
        if (!empty($missingRequired)) {
            return redirect()->back()->with('error', 'Required fields missing: ' . implode(', ', $missingRequired));
        }

        $importLog->update(['config' => $config]);

        return redirect(url($this->crud->route . '/import/' . $importLog->id . '/confirm'));
    }

    // ==============================
    // Step 3: Confirm & Execute
    // ==============================

    public function confirmImport($id)
    {
        $this->crud->hasAccessOrFail('import');
        $this->crud->setOperation('import');
        $this->setupImportConfig();

        $importLogModel = config('backpack.operations.import.import_log_model', ImportLog::class);
        $importLog = $importLogModel::findOrFail($id);

        $this->data['crud'] = $this->crud;
        $this->data['title'] = 'Confirm Import';
        $this->data['importLog'] = $importLog;

        return view('adrec.backpack-import::confirm-import', $this->data);
    }

    public function handleImport($id)
    {
        $this->crud->hasAccessOrFail('import');
        $this->crud->setOperation('import');
        $this->setupImportConfig();

        $importLogModel = config('backpack.operations.import.import_log_model', ImportLog::class);
        $importLog = $importLogModel::findOrFail($id);

        $queued = $this->crud->getOperationSetting('queued') ?? false;
        $customHandler = $this->crud->getOperationSetting('importHandler');

        // Get validation rules from create operation
        $validationRules = $this->getImportValidationRules();

        if ($customHandler) {
            $importInstance = new $customHandler($importLog, $validationRules);
        } elseif ($queued) {
            $importInstance = new QueuedCrudImport($importLog, $validationRules);
        } else {
            $importInstance = new CrudImport($importLog, $validationRules);
        }

        $filePath = $importLog->file_path;
        $disk = $importLog->disk;

        if ($queued) {
            Excel::queuedImport($importInstance, $filePath, $disk);
            return redirect(url($this->crud->route))
                ->with('success', 'Import has been queued and will be processed in the background.');
        }

        Excel::import($importInstance, $filePath, $disk);

        // Refresh to get updated counts
        $importLog->refresh();
        $message = "Import completed! Processed: {$importLog->processed_rows} rows, Skipped: {$importLog->skipped_rows} rows.";

        return redirect(url($this->crud->route))
            ->with('success', $message);
    }

    // ==============================
    // Helper Methods
    // ==============================

    /**
     * Enable queued (background) import processing
     */
    protected function queueImport(): void
    {
        $this->crud->setOperationSetting('queued', true);
    }

    /**
     * Delete file after import completes
     */
    protected function deleteFileAfterImport(): void
    {
        $this->crud->setOperationSetting('deleteFileAfterImport', true);
    }

    /**
     * Disable primary key requirement
     */
    protected function withoutPrimaryKey(): void
    {
        $this->crud->setOperationSetting('withoutPrimaryKey', true);
    }

    /**
     * Disable user column mapping (auto-map by header name)
     */
    protected function disableUserMapping(): void
    {
        $this->crud->setOperationSetting('userMapping', false);
    }

    /**
     * Set URL for example/template file download
     */
    protected function setExampleFileUrl(string $url): void
    {
        $this->crud->setOperationSetting('exampleFileUrl', $url);
    }

    /**
     * Set custom import handler class
     */
    protected function setImportHandler(string $class): void
    {
        $this->crud->setOperationSetting('importHandler', $class);
    }

    /**
     * Get primary key for the model
     */
    protected function getImportPrimaryKey(): ?string
    {
        $withoutPrimaryKey = $this->crud->getOperationSetting('withoutPrimaryKey') ?? false;
        if ($withoutPrimaryKey) {
            return null;
        }

        // Check CRUD columns for primary_key flag
        $columns = $this->getImportColumns();
        foreach ($columns as $name => $config) {
            if (!empty($config['primary_key'])) {
                return $name;
            }
        }

        // Default to model primary key
        return $this->crud->getModel()->getKeyName();
    }

    /**
     * Get import columns from CRUD configuration
     */
    protected function getImportColumns(): array
    {
        $columns = [];

        if ($this->crud->columns()) {
            foreach ($this->crud->columns() as $column) {
                $name = $column['name'] ?? '';
                if ($name) {
                    $columns[$name] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * Get required column names from CRUD form validation
     */
    protected function getRequiredImportColumns(): array
    {
        $rules = $this->getImportValidationRules();
        $required = [];

        foreach ($rules as $field => $rule) {
            $ruleStr = is_array($rule) ? implode('|', $rule) : $rule;
            if (str_contains($ruleStr, 'required')) {
                $required[] = $field;
            }
        }

        return $required;
    }

    /**
     * Get validation rules from the create form request
     */
    protected function getImportValidationRules(): array
    {
        try {
            $formRequest = $this->crud->getFormRequest();
            if ($formRequest) {
                $instance = new $formRequest();
                return $instance->rules();
            }
        } catch (\Exception $e) {
            // No form request configured
        }

        return [];
    }

    /**
     * Get file headings (first row) from uploaded file
     */
    protected function getFileHeadings(ImportLog $importLog): array
    {
        $headings = (new HeadingRowImport())
            ->toArray($importLog->file_path, $importLog->disk);

        // Flatten nested arrays
        while (isset($headings[0]) && is_array($headings[0])) {
            $headings = $headings[0];
        }

        // Filter out empty headings
        return array_filter($headings, function ($heading) {
            return $heading !== null && $heading !== '';
        });
    }

    /**
     * Setup import configuration for the current request
     */
    private function setupImportConfig()
    {
        if (method_exists($this, 'setupImportOperation')) {
            $this->setupImportOperation();
        }
    }
}
