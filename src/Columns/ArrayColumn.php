<?php

namespace Adrec\BackpackImport\Columns;

class ArrayColumn extends ImportColumn
{
    public function output(): mixed
    {
        if ($this->data === null || $this->data === '') {
            return $this->getConfig('default', null);
        }

        $separator = $this->getConfig('separator', ',');
        $options = $this->getConfig('options');
        $multiple = $this->getConfig('multiple', false);

        $values = array_map('trim', explode($separator, (string) $this->data));

        // Map values to option keys if options provided
        if ($options && is_array($options)) {
            $mapped = [];
            foreach ($values as $value) {
                foreach ($options as $key => $label) {
                    if (strtolower((string) $value) === strtolower((string) $label)) {
                        $mapped[] = $key;
                        break;
                    }
                }
            }
            $values = $mapped;
        }

        if ($multiple) {
            return $values;
        }

        return $values[0] ?? null;
    }

    public function getName(): string
    {
        return 'Array';
    }
}
