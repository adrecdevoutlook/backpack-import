<?php

namespace Adrec\BackpackImport\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ImportLog extends Model
{
    protected $table = 'import_logs';

    protected $fillable = [
        'user_id',
        'file_path',
        'disk',
        'model',
        'model_primary_key',
        'config',
        'delete_file_after_import',
        'started_at',
        'completed_at',
        'processed_rows',
        'skipped_rows',
    ];

    protected $casts = [
        'config' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'delete_file_after_import' => 'boolean',
        'processed_rows' => 'integer',
        'skipped_rows' => 'integer',
    ];

    public function user(): BelongsTo
    {
        $guard = config('backpack.base.guard', 'web');
        $userModel = config("auth.guards.{$guard}.provider");
        $userModelClass = config("auth.providers.{$userModel}.model", \App\Models\User::class);

        return $this->belongsTo($userModelClass, 'user_id');
    }

    public function getDurationAttribute(): ?int
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInSeconds($this->completed_at);
        }
        return null;
    }

    public function getFileUrlAttribute(): ?string
    {
        if ($this->file_path) {
            return Storage::disk($this->disk)->url($this->file_path);
        }
        return null;
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->file_path);
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->delete($this->file_path);
        }
        return false;
    }
}
