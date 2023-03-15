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
    public function fetchAll(): PromiseInterface
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
        $toPromiseFunctions[] = tpf(function () {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'energy_output_geometry_id as id',
                        'energy_output_capacity as capacity',
                        'energy_output_maxcapacity as maxcapacity',
                        'energy_output_active as active'
                    )
                    ->from('energy_output')
                    ->where($qb->expr()->eq('energy_output_active', 1))
            );
        });
        return parallel($toPromiseFunctions);
    }
}
