<?php declare(strict_types=1);

namespace Easir\ContactsDoubletCheck;

use OutOfBoundsException;

trait GetFieldNames
{
    protected function getFieldName(string $fieldName): string
    {
        if (!array_key_exists($fieldName, $this->fields)) {
            throw new OutOfBoundsException(sprintf('%s is unknown', $fieldName));
        }

        return $this->fields[$fieldName];
    }
}
