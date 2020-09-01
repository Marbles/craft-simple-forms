<?php

namespace rias\simpleforms\models\export;

use craft\fields\data\MultiOptionsFieldData;

class MultiOptionsFieldDataExport implements ExportValueInterface
{
    /**
     * @param MultiOptionsFieldData $value
     *
     * @return string
     */
    public static function toColumn($value): string
    {
        return implode(',', (array) $value);
    }
}
