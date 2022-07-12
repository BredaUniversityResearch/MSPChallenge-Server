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
    /**
     * @throws Exception
     * @return array|PromiseInterface
     */
    public function latest(int $time, int $country)/*: array|PromiseInterface // <-- php 8 */
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
