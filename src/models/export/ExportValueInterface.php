<?php

namespace rias\simpleforms\models\export;

interface ExportValueInterface
{
    public static function toColumn($value): string;
}
