<?php

namespace App\Domain\WsServer\Plugins\Tick;

use Exception;
use React\Promise\PromiseInterface;

class PlanTick extends TickBase
{
    /**
     * Internal function to clean up some state for the plans.
     *
     * @throws Exception
     */
    public function tick(bool $showDebug = false): PromiseInterface
    {
        //Now finally clean up all plans that are still locked by a user which hasn't been seen for an amount of time
        $qb = $this->getAsyncDatabase()->createQueryBuilder();
        /** @noinspection SqlResolve */
        return $this->getAsyncDatabase()->query(
            $qb
                ->update('plan', 'p')
                ->set('p.plan_lock_user_id', 'NULL')
                ->set('p.plan_lastupdate', $qb->createPositionalParameter(microtime(true)))

                // since it is not possible to use this innerJoin with an update with DBAL, use a sub query instead:
                // ->innerJoin('p', 'user', 'u', 'p.plan_lock_user_id = u.user_id AND u.user_lastupdate + 60 < ?')
                ->andWhere(
                    $qb->expr()->in(
                        'p.plan_lock_user_id',
                        'SELECT u.user_id FROM user u WHERE u.user_id = p.plan_lock_user_id AND ' .
                            'u.user_lastupdate + 60 < ' . $qb->createPositionalParameter(microtime(true))
                    )
                )
        );
    }
}