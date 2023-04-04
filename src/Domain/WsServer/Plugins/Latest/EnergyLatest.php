<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Exception;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;

class EnergyLatest extends CommonBase
{
    /**
     * @throws Exception
     */
    public function fetchAll($allowEnergyKpiUpdate = true): PromiseInterface
    {
        $toPromiseFunctions[] = tpf(function () {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'energy_connection_start_id as start',
                        'energy_connection_end_id as end',
                        'energy_connection_cable_id as cable',
                        'energy_connection_start_coordinates as coords',
                        'energy_connection_active as active',
                    )
                    ->from('energy_connection')
                    ->where($qb->expr()->eq('energy_connection_active', 1))
            );
        });
        $toPromiseFunctions[] = tpf(function () use ($allowEnergyKpiUpdate) {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            $qb->
                select(
                    'eo.energy_output_geometry_id as id',
                    'eo.energy_output_capacity as capacity',
                    'eo.energy_output_maxcapacity as maxcapacity',
                    'eo.energy_output_active as active'
                )
                ->from('energy_output', 'eo')
                ->where($qb->expr()->eq('eo.energy_output_active', 1));
            if (!$allowEnergyKpiUpdate) { // skip the IMPLEMENTED plans' output
                $qb->
                    join(
                        'eo',
                        'geometry',
                        'g',
                        'eo.energy_output_geometry_id = g.geometry_id'
                    )
                    ->join(
                        'g',
                        'plan_layer',
                        'pl',
                        'g.geometry_layer_id = pl.plan_layer_layer_id'
                    )
                    ->join(
                        'pl',
                        'plan',
                        'p',
                        'pl.plan_layer_plan_id = p.plan_id'
                    )
                    ->andWhere($qb->expr()->neq('p.plan_state', $qb->createPositionalParameter('IMPLEMENTED')));
            }
            return $this->getAsyncDatabase()->query($qb);
        });
        return parallel($toPromiseFunctions);
    }

    /**
     * @throws Exception
     */
    public function fetchOutputConnectionsUnImplementedPlans(): PromiseInterface
    {
        return $this->fetchAll(false);
    }
}
