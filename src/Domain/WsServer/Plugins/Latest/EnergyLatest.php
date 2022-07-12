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
    public function latest(float $time): PromiseInterface
    {
        $toPromiseFunctions[] = tpf(function () use ($time) {
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
                    ->where('energy_connection_lastupdate > ' . $qb->createPositionalParameter($time))
            );
        });
        $toPromiseFunctions[] = tpf(function () use ($time) {
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
                    ->where('energy_output_lastupdate > ' . $qb->createPositionalParameter($time))
            );
        });
        return parallel($toPromiseFunctions);
    }
}
