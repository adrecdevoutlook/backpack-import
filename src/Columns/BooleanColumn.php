<?php

namespace Adrec\BackpackImport\Columns;

class BooleanColumn extends ImportColumn
{
    public function output(): mixed
    {
        if ($this->data === null || $this->data === '') {
            return $this->getConfig('default', false);
        }

        $options = $this->getConfig('options');

        // If custom options mapping provided
        if ($options && is_array($options)) {
            foreach ($options as $value => $label) {
                if (strtolower((string) $this->data) === strtolower((string) $label)) {
                    return (bool) $value;
                }
            }
        }

        // Default boolean parsing
        $trueValues = ['true', '1', 'yes', 'y', 'on'];
        return in_array(strtolower(trim((string) $this->data)), $trueValues, true);
    }

    public function getName(): string
    {
        return 'Boolean';
    }
}
