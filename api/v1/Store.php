<?php

namespace App\Domain\API\v1;

use App\Domain\Common\CommonBase;
use App\Domain\Services\SymfonyToLegacyHelper;
use Exception;

class Store
{
    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterStoreFolder(int $gameSessionId): string
    {
        $storeFolder = SymfonyToLegacyHelper::getInstance()->getProjectDir() . "/raster/";
        $storeFolder .= ($gameSessionId != CommonBase::INVALID_SESSION_ID) ? $gameSessionId . "/" : "default/";
        return $storeFolder;
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function GetRasterArchiveFolder(int $gameSessionId): string
    {
        $folder = self::GetRasterStoreFolder($gameSessionId);
        return $folder . "archive/";
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public static function EnsureFolderExists(string $directory): void
    {
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
