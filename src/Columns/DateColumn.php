<?php

namespace Adrec\BackpackImport\Columns;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DateColumn extends ImportColumn
{
    public function output(): mixed
    {
        if ($this->data === null || $this->data === '') {
            return $this->getConfig('default', null);
        }

        $format = $this->getConfig('format');

        // If data is numeric, it might be an Excel serial date
        if (is_numeric($this->data)) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject($this->data));
            } catch (\Exception $e) {
                // Fall through to string parsing
            }
        }

        $dateStr = (string) $this->data;

        // If a specific format is provided
        if ($format) {
            try {
                return Carbon::createFromFormat($format, $dateStr);
            } catch (\Exception $e) {
                // Fall through to auto-detect
            }
        }

        // Auto-detect common formats
        $formats = [
            'd/m/Y',
            'd-m-Y',
            'd.m.Y',
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $fmt) {
            try {
                $date = Carbon::createFromFormat($fmt, $dateStr);
                if ($date && $date->isValid()) {
                    return $date;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Last resort: Carbon::parse
        try {
            return Carbon::parse($dateStr);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getName(): string
    {
        return 'Date';
    }
}
