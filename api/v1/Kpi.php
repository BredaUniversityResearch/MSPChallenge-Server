<?php

namespace App\Domain\API\v1;

use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;
use function Clue\React\Block\await;

class Kpi extends Base
{
    private const ALLOWED = array(
        "Post",
        "BatchPost",
        "Latest"
    );

    public function __construct(string $method = '')
    {
        parent::__construct($method, self::ALLOWED);
    }

    /**
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
                $value["country"]
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
        int $kpiCountry = -1
    ): int {
        $value = floatval(str_replace(",", ".", $kpiValue));
        return Database::GetInstance()->query(
            "
            INSERT INTO kpi (kpi_name, kpi_value, kpi_month, kpi_type, kpi_lastupdate, kpi_unit, kpi_country_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE kpi_value = ?, kpi_lastupdate = ?
            ",
            array(
                $kpiName, $value, $kpiMonth, $kpiType, microtime(true), $kpiUnit, $kpiCountry, $value, microtime(true)
            ),
            true
        );
    }

    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function Latest(int $time, int $country)/*: array|PromiseInterface // <-- php 8 */
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();

        // template query builder for both ecology and shipping
        $qb
            ->select(
                'kpi_name as name',
                'kpi_value as value',
                'kpi_month as month',
                'kpi_type as type',
                'kpi_lastupdate as lastupdate',
                'kpi_country_id as country',
            )
            ->from('kpi')
            ->where(
                $qb->expr()->and(
                    'kpi_lastupdate > ?',
                    $qb->expr()->or(
                        'kpi_country_id = ?',
                        'kpi_country_id = -1'
                    ),
                    'kpi_type = ?'
                )
            );

        // ecology
        $toPromiseFunctions[] = tpf(function () use ($qb, $time, $country) {
            return $this->getAsyncDatabase()->query(
                $qb
                    ->setParameters([$time, $country, 'ECOLOGY'])
            );
        });
        // shipping
        $toPromiseFunctions[] = tpf(function () use ($qb, $time, $country) {
            return $this->getAsyncDatabase()->query(
                $qb
                    ->setParameters([$time, $country, 'SHIPPING'])
            );
        });

        // energy
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $toPromiseFunctions[] = tpf(function () use ($qb, $time) {
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'energy_kpi_grid_id as grid',
                        'energy_kpi_month as month',
                        'energy_kpi_country_id as country',
                        'energy_kpi_actual as actual',
                    )
                    ->from('energy_kpi')
                    ->where('energy_kpi_lastupdate > ' . $qb->createPositionalParameter($time))
            );
        });

        $promise = parallel($toPromiseFunctions, 1) // todo: if performance allows, increase threads
            /** @var Result[] $results */
            ->then(function (array $results) {
                //should probably be renamed to be something other than ecology
                $data['ecology'] = $results[0]->fetchAllRows();
                $data['shipping'] = $results[1]->fetchAllRows();
                $data['energy'] = $results[2]->fetchAllRows();
                return $data;
            });

        return $this->isAsync() ? $promise : await($promise);
    }
}
