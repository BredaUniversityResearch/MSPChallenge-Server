<?php

namespace App\Domain\API\v1;

use App\Domain\Services\ConnectionManager;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Kpi extends Base
{
    public const KPI_TYPE_EXTERNAL = 'EXTERNAL';
    public const ALLOWED_KPI_TYPES = ['ECOLOGY', 'ENERGY', 'SHIPPING', self::KPI_TYPE_EXTERNAL];

    /**
     * called from MEL
     * @apiGroup KPI
     * @throws Exception
     * @api {POST} /kpi/post Post
     * @apiParam {string} name name of the KPI
     * @apiParam {int} month Month that this KPI applies to.
     * @apiParam {float} value the value of this months kpi
     * @apiParam {string} type the type of KPI (ECOLOGY, ENERGY, SHIPPING)
     * @apiParam {string} unit the measurement unit of this KPI
     * @apiParam {int} country (OPTIONAL) id of the country that this belongs to. Not filling this in will default it to
     *   all countries
     * @apiDescription Add a new kpi value to the database
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Post(string $name, int $month, float $value, string $type, string $unit, int $country = -1): int
    {
        return $this->PostKPI($name, $month, $value, $type, $unit, $country);
    }

    /**
     * called from SEL
     * @apiGroup KPI
     * @throws Exception
     * @api {POST} /kpi/BatchPost BatchPost
     * @apiParam {array} kpiValues Input format should be [{"name":(string kpiName),"month": (int month),
     *   "value":(float kpiValue),"type":(string kpiType),"unit":(string kpiUnit),"country":(int countryId or null)}]
     * @apiDescription Add a new kpi value to the database
     * @noinspection PhpUnused
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function BatchPost(array $kpiValues): void
    {
        foreach ($kpiValues as $value) {
            $this->PostKPI(
                $value["name"],
                $value["month"],
                $value["value"],
                $value["type"],
                $value["unit"],
                $value["country"],
                $value["type_external"] ?? null
            );
        }
    }

    /**
     * @throws Exception
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    private function PostKPI(
        string $kpiName,
        int $kpiMonth,
        float $kpiValue,
        string $kpiType,
        string $kpiUnit,
        int $kpiCountry = -1,
        ?string $kpiTypeExternal = null
    ): int {
        if (!in_array(strtoupper($kpiType), self::ALLOWED_KPI_TYPES)) {
            throw new BadRequestHttpException('Invalid KPI type: '.$kpiType.
                '. Allowed values are: '.implode(',', self::ALLOWED_KPI_TYPES).'.');
        }
        $kpiType = strtoupper($kpiType);
        return (int)$this->getDatabase()->query(
            "
            INSERT INTO kpi (
                 kpi_name, kpi_value, kpi_month, kpi_type, kpi_type_external, kpi_lastupdate, kpi_unit, kpi_country_id
             ) 
            VALUES (?, ?, ?, ?, ?, UNIX_TIMESTAMP(NOW(6)), ?, ?)
            ON DUPLICATE KEY UPDATE kpi_value=?, kpi_lastupdate=UNIX_TIMESTAMP(NOW(6))
            ",
            array(
                $kpiName, $kpiValue, $kpiMonth, $kpiType, $kpiTypeExternal ?? 'NULL', $kpiUnit, $kpiCountry, $kpiValue
            ),
            true
        );
    }
}
