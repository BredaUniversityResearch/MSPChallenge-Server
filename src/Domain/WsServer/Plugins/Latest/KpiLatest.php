<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use App\Domain\Common\ToPromiseFunction;
use Doctrine\DBAL\Query\QueryBuilder;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\await;
use function App\parallel;
use function App\tpf;

class KpiLatest extends CommonBase
{
    private function createQueryBuilderKpiBase(
        int $country,
        string $kpiType,
        bool $currentMonthOnly = true
    ): QueryBuilder {
        assert(in_array($kpiType, ['ECOLOGY', 'SHIPPING'])); // so, not meant for energy

        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        // template query builder for both ecology and shipping
        $andExpr = $qb->expr()->and(
            $qb->expr()->or(
                $qb->expr()->eq('kpi_country_id', $qb->createPositionalParameter($country)),
                'kpi_country_id = -1'
            ),
            $qb->expr()->eq(
                'kpi_type',
                $qb->createPositionalParameter($kpiType, \Doctrine\DBAL\Types\Types::STRING)
            )
        );
        if ($currentMonthOnly) {
            $andExpr = $andExpr->with($qb->expr()->in(
                'kpi_month',
                // if there is a recent simulation change, retrieve all changes of that month
                //  note that game_currentmonth is always equal to game_Xel_month column in this case
                //  and that all simulation runs have been finished here
                'SELECT game_currentmonth FROM game',
            ));
        }
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
            ->where($andExpr);
        return $qb;
    }

    private function createQueryBuilderEnergyKpiBase(bool $currentMonthOnly = true): QueryBuilder
    {
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        $qb
            ->select(
                'energy_kpi_grid_id as grid',
                'energy_kpi_month as month',
                'energy_kpi_country_id as country',
                'energy_kpi_actual as actual',
                'energy_kpi_lastupdate'
            )
            ->from('energy_kpi');
        if ($currentMonthOnly) {
            $qb->where($qb->expr()->in(
                'energy_kpi_month',
                // if there is a recent simulation change, retrieve all changes of that month
                //  note that game_currentmonth is always equal to game_Xel_month column in this case
                //  and that all simulation runs have been finished here
                'SELECT game_currentmonth FROM game'
            ));
        }
        return $qb;
    }

    private function createPromiseFunctionKpi(int $time, int $country, string $kpiType): ToPromiseFunction
    {
        return tpf(function () use ($time, $country, $kpiType) {
            $qb = $this->createQueryBuilderKpiBase($country, $kpiType);
            return $this->getAsyncDatabase()->query($qb)
                ->then(function (Result $result) use ($time, $country, $kpiType) {
                    $kpiMinLastUpdate = collect($result->fetchAllRows() ?: [])
                        ->reduce(fn($carry, $item) => min($carry, $item['lastupdate']), $time);
                    if ($time <= $kpiMinLastUpdate) { // oh, we need to retrieve more that just the current month
                        $qb = $this->createQueryBuilderKpiBase($country, $kpiType, false); // fetch all
                        return $this->getAsyncDatabase()->query($qb);
                    }
                    return $result;
                });
        });
    }

    private function createPromiseFunctionEnergyKpi(int $time): ToPromiseFunction
    {
        return tpf(function () use ($time) {
            $qb = $this->createQueryBuilderEnergyKpiBase();
            return $this->getAsyncDatabase()->query($qb)
                ->then(function (Result $result) use ($time) {
                    $kpiMinLastUpdate = collect($result->fetchAllRows() ?: [])
                        ->reduce(fn($carry, $item) => min($carry, $item['energy_kpi_lastupdate']), $time);
                    if ($time <= $kpiMinLastUpdate) { // oh, we need to retrieve more that just the current month
                        $qb = $this->createQueryBuilderEnergyKpiBase(false); // fetch all
                        return $this->getAsyncDatabase()->query($qb);
                    }
                    return $result;
                });
        });
    }

    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    public function latest(int $time, int $country)/*: array|PromiseInterface // <-- php 8 */
    {
        $toPromiseFunctions[] = $this->createPromiseFunctionKpi($time, $country, 'ECOLOGY');
        $toPromiseFunctions[] = $this->createPromiseFunctionKpi($time, $country, 'SHIPPING');
        $toPromiseFunctions[] = $this->createPromiseFunctionEnergyKpi($time);

        $promise = parallel($toPromiseFunctions)
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
