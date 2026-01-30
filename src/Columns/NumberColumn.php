<?php

namespace Adrec\BackpackImport\Columns;

class NumberColumn extends ImportColumn
{
    public function output(): mixed
    {
        if ($this->data === null || $this->data === '') {
            return $this->getConfig('default', null);
        }

        if (is_numeric($this->data)) {
            return (float) $this->data;
        }

        return null;
    }

    public function getName(): string
    {
        return 'Number';
    }
}
