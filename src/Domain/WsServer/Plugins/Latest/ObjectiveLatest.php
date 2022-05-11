<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Exception;
use React\Promise\PromiseInterface;

class ObjectiveLatest extends CommonBase
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
                    'objective_id',
                    'objective_country_id as country_id',
                    'objective_title as title',
                    'objective_description as description',
                    'objective_deadline as deadline',
                    'objective_active as active',
                    'objective_complete as complete',
                )
                ->from('objective')
                ->where('objective_lastupdate > ' . $qb->createPositionalParameter($time))
        );
    }
}
