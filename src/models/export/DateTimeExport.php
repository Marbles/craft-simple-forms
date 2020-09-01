<?php

namespace rias\simpleforms\models\export;

use DateTime;

class DateTimeExport implements ExportValueInterface
{
    /**
     * @param DateTime $value
     *
     * @return string
     */
    public static function toColumn($value): string
    {
        return $value->format('Y-m-d H:i:s');
    }
}
