<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Exception;
use React\Promise\PromiseInterface;

class WarningLatest extends CommonBase
{
    /**
     * @throws Exception
     */
    public function latest(float $time): PromiseInterface
    {
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
    }
}
