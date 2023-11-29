<?php

namespace App\Controller\SessionAPI;

use App\Domain\Services\ConnectionManager;
use React\EventLoop\Loop;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use function App\parallel;
use function App\tpf;
use function App\await;

class GameController extends BaseController
{

    #[Route(
        '/{sessionId}/api/Game/AddEvent/',
        name: 'session_api_game_addevent',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
    public function addEvent(
        int $sessionId,
        Request $request,
    ): Response {
        try {
            $gameMonth = $request->get('game_month') ?? -1;
            $country = $request->get('country')
                or throw new \Exception('country is obligatory, but can be -1 to indicate all countries');
            $title = $request->get('title')
                or throw new \Exception('title is obligatory');
            $body = $request->get('body')
                or throw new \Exception('body is obligatory');

            if ($country != -1) {
                $countries[] = $country;
            } else {
                $countries = [];
                $conn = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($sessionId);
                $allCountries = $conn->executeQuery(
                    $conn->createQueryBuilder()->select('country_id')->from('country')->getSQL()
                )->fetchAllAssociative();
                foreach ($allCountries as $storedCountry) {
                    $countries[] = $storedCountry['country_id'];
                }
            }

            $conn = ConnectionManager::getInstance()->getCachedAsyncGameSessionDbConnection(Loop::get(), $sessionId);
            $toPromiseFunctions = [];
            foreach ($countries as $country) {
                $toPromiseFunctions[] = tpf(function () use ($conn, $title, $body, $country, $gameMonth) {
                    $qb = $conn->createQueryBuilder();
                    return $conn->query(
                        $qb->insert('game_event')
                        ->values(
                            [
                                'event_title' => $qb->createPositionalParameter($title),
                                'event_body' => $qb->createPositionalParameter($body),
                                'event_country_id' => $qb->createPositionalParameter($country),
                                'event_game_month' => $qb->createPositionalParameter($gameMonth),
                                'event_lastupdate' => 'UNIX_TIMESTAMP(NOW(6))',
                            ]
                        )
                    );
                });
            }
            $promise = parallel($toPromiseFunctions);
            await($promise);
            return self::success();
        } catch (\Exception $e) {
            return self::error($e->getMessage().PHP_EOL.$e->getTraceAsString());
        }
    }

    #[Route(
        '/{sessionId}/api/Game/DeleteEvent/',
        name: 'session_api_game_deleteevent',
        requirements: ['sessionId' => '\d+'],
        methods: ['POST']
    )]
    public function deleteEvent(
        int $sessionId,
        Request $request,
    ): Response {
        try {
            $eventId = $request->get('id')
            or throw new \Exception('id is obligatorys');
            $conn = ConnectionManager::getInstance()->getCachedGameSessionDbConnection($sessionId);
            $conn->executeQuery(
                $conn->createQueryBuilder()
                    ->delete('game_event')
                    ->where($conn->createExpressionBuilder()->eq('event_id', $eventId))
                    ->getSQL()
            );
            return self::success();
        } catch (\Exception $e) {
            return self::error($e->getMessage().PHP_EOL.$e->getTraceAsString());
        }
    }
}
