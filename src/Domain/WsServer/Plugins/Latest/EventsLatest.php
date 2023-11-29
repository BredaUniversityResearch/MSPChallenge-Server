<?php

namespace App\Domain\WsServer\Plugins\Latest;

use App\Domain\Common\CommonBase;
use Exception;
use React\Promise\PromiseInterface;

class EventsLatest extends CommonBase
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
                    'event_id',
                    'event_country_id as country_id',
                    'event_title as title',
                    'event_body as body',
                    'event_game_month as game_month',
                    'event_datetime as date_time'
                )
                ->from('game_event')
                ->where('event_lastupdate > ' . $qb->createPositionalParameter($time))
        );
    }
}
