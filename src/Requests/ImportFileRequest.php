<?php

namespace Adrec\BackpackImport\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    public function rules(): array
    {
        return [
            'import_file' => [
                'required',
                'file',
                'mimetypes:application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv,application/x-xls',
                'max:51200', // 50MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'import_file.required' => 'Please select a file to import.',
            'import_file.file' => 'The upload must be a valid file.',
            'import_file.mimetypes' => 'The file must be a CSV, XLS, or XLSX file.',
            'import_file.max' => 'The file must not exceed 50MB.',
        ];
    }
}
