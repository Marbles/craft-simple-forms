<?php

namespace rias\simpleforms\models\export;

use craft\fields\data\SingleOptionFieldData;

class SingleOptionFieldDataExport implements ExportValueInterface
{
    /**
     * @param SingleOptionFieldData $value
     *
     * @return string
     */
    public static function toColumn($value): string
    {
        return (string) $value;
    }
}
