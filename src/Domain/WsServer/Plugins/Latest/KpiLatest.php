<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Drift\DBAL\Result;
use Exception;
use React\Promise\PromiseInterface;
use function App\await;
use function App\parallel;
use function App\tpf;

class KpiLatest extends CommonBase
{
    private function getQueryBuilderKpiBase(int $country, string $kpiType)
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
                    $qb->expr()->in(
                        'kpi_month',
                        // if there is a recent simulation change, retrieve all changes of that month
                        //  note that game_currentmonth is always equal to game_Xel_month column in this case
                        //  and that all simulation runs have been finished here
                        'SELECT game_currentmonth FROM game',
                    ),
                    $qb->expr()->or(
                        $qb->expr()->eq('kpi_country_id', $qb->createPositionalParameter($country)),
                        'kpi_country_id = -1'
                    ),
                    $qb->expr()->eq(
                        'kpi_type',
                        $qb->createPositionalParameter($kpiType, \Doctrine\DBAL\Types\Types::STRING)
                    )
                )
            );
        return $qb;
    }

    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    public function fetchLastMonth(int $country)/*: array|PromiseInterface // <-- php 8 */
    {
        // ecology
        $toPromiseFunctions[] = tpf(function () use ($country) {
            $qb = $this->getQueryBuilderKpiBase($country, 'ECOLOGY');
            return $this->getAsyncDatabase()->query($qb);
        });
        // shipping
        $toPromiseFunctions[] = tpf(function () use ($country) {
            $qb = $this->getQueryBuilderKpiBase($country, 'SHIPPING');
            return $this->getAsyncDatabase()->query($qb);
        });

        // energy
        $toPromiseFunctions[] = tpf(function () {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'energy_kpi_grid_id as grid',
                        'energy_kpi_month as month',
                        'energy_kpi_country_id as country',
                        'energy_kpi_actual as actual',
                    )
                    ->from('energy_kpi')
                    ->where($qb->expr()->in(
                        'energy_kpi_month',
                        // if there is a recent simulation change, retrieve all changes of that month
                        //  note that game_currentmonth is always equal to game_Xel_month column in this case
                        //  and that all simulation runs have been finished here
                        'SELECT game_currentmonth FROM game'
                    ))
            );
        });

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
