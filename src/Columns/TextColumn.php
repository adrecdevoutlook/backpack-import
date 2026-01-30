<?php

namespace Adrec\BackpackImport\Columns;

class TextColumn extends ImportColumn
{
    public function output(): mixed
    {
        if ($this->data === null || $this->data === '') {
            return $this->getConfig('default', null);
        }

        return (string) $this->data;
    }

    public function getName(): string
    {
        return 'Text';
    }
}
