<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Exception;
use React\Promise\PromiseInterface;
use function App\parallel;
use function App\tpf;

class WarningLatest extends CommonBase
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
                        'warning_id as issue_database_id',
                        'warning_active as active',
                        'warning_layer_id as plan_layer_id',
                        'warning_issue_type as type',
                        'warning_x as x',
                        'warning_y as y',
                        'warning_source_plan_id as source_plan_id',
                        'warning_restriction_id as restriction_id'
                    )
                    ->from('warning')
                    ->where('warning_last_update > ' . $qb->createPositionalParameter($time))
            );
        });
        $toPromiseFunctions[] = tpf(function () {
            $qb = $this->getAsyncDatabase()->createQueryBuilder();
            return $this->getAsyncDatabase()->query(
                $qb
                    ->select(
                        'shipping_warning_id as warning_id',
                        'shipping_warning_source_geometry_persistent_id as source_geometry_persistent_id',
                        'shipping_warning_destination_geometry_persistent_id as destination_geometry_persistent_id',
                        'shipping_warning_message as message',
                        'shipping_warning_active as active'
                    )
                    ->from('shipping_warning')
                    // note the active ones are the ones from the last simulation run, anything older is deactivated,
                    //  see Warning::SetShippingIssues()
                    ->where('shipping_warning_active = 1')
            );
        });
        return parallel($toPromiseFunctions);
    }
}
