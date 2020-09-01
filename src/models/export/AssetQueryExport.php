<?php

namespace rias\simpleforms\models\export;

use craft\elements\Asset;
use craft\elements\db\AssetQuery;

class AssetQueryExport implements ExportValueInterface
{
    /**
     * @param AssetQuery $value
     *
     * @return string
     */
    public static function toColumn($value): string
    {
        return implode("\n", array_map(function (Asset $asset) {
            return $asset->getUrl();
        }, $value->all()));
    }
}
