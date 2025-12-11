<?php

namespace App\Repository\ServerManager;

use App\Domain\API\v1\Game;
use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\WsServer\WsServer;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\GameServer;
use Doctrine\ORM\EntityRepository;

class GameListRepository extends EntityRepository
{
    public function save(GameList $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(GameList $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return GameList[] Returns an array of GameList objects by session state, archived or not archived (active)
     * @throws \Exception
     */
    public function findBySessionState(string $value): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select([
                'g.id', 'g.name', 'gcv.id as game_config_version_id', 'gcv.version as config_version_version',
                'gcv.versionMessage as config_version_message', 'gcf.filename as config_file_name', 'gcv.region',
                'gcf.description as config_file_description', 'gws.name as watchdog_name',
                'gse.address as game_server_address', 'gws.address as watchdog_address', 'gsa.id as save_id',
                'gsa.gameConfigFilesFilename', 'g.gameCreationTime as game_creation_time', 'ggs.name as geoserver_name',
                'ggs.address as geoserver_address', 'g.gameStartYear as game_start_year',
                'g.gameEndMonth as game_end_month', 'g.gameCurrentMonth as game_current_month',
                'g.gameTransitionMonth as game_transition_month',
                'g.gameRunningTilTime as game_running_til_time', 'g.sessionState as session_state',
                'g.gameTransitionState as game_transition_state',
                'g.gameState as game_state', 'g.playersActive as players_active',
                'g.playersPastHour as players_past_hour'
            ])
            ->innerJoin('g.gameServer', 'gse')
            ->innerJoin('g.gameWatchdogServer', 'gws')
            ->leftJoin('g.gameConfigVersion', 'gcv')
            ->leftJoin('gcv.gameConfigFile', 'gcf')
            ->leftJoin('g.gameGeoServer', 'ggs')
            ->leftJoin('g.gameSave', 'gsa');

        if ($value == 'archived') {
            $qb->andWhere($qb->expr()->eq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        } else {
            $qb->andWhere($qb->expr()->neq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        }
        $sessionList = $qb->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
        $this->amendSessionList($sessionList);
        return $sessionList;
    }

    /**
     * @throws \Exception
     */
    private function amendSessionList(array &$sessionList): void
    {
        $scheme = str_replace('://', '', $_ENV['URL_WEB_SERVER_SCHEME'] ?? 'http').'://';
        $port = $_ENV['URL_WEB_SERVER_PORT'] ?? $_ENV['WEB_SERVER_PORT'] ?? 80;
        $host = $_ENV['URL_WEB_SERVER_HOST'] ?? null;
        if (is_null($host)) {
            $server = $this->getEntityManager()->getRepository(GameServer::class)->find(1);
            if (!is_null($server)) {
                $host = $server->getAddress();
            }
            if (!empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] != $host) {
                $host = $_SERVER['SERVER_NAME'];
            }
        }
        foreach ($sessionList as $key => $session) {
            $session['players_active'] ??= 0;
            $session['players_past_hour'] ??= 0;
            // get session's config file contents and decode the json
            $game = new Game();
            $game->setGameSessionId($session['id']);
            $configContents = $game->Config(); // todo: can't we use GameList::getGameConfig()->getGameConfigComplete()
            $session['edition_name'] = $configContents['edition_name'];
            $session['edition_colour'] = $configContents['edition_colour'];
            $session['edition_letter'] = $configContents['edition_letter'];
            // complete the server and websocket server addresses
            $session['game_server_address'] = $scheme.$host.':'.$port.'/';
            $session['game_ws_server_address'] = WsServer::getWsServerURLBySessionId($session['id'], $host);
            $gameList = new GameList();
            $session['current_month_formatted'] = $gameList
                ->setGameStartYear($session['game_start_year'])
                ->setGameCurrentMonth($session['game_current_month'])
                ->getGameCurrentMonthPretty();
            $session['transition_month_formatted'] = $gameList
                ->setGameStartYear($session['game_start_year'])
                ->setGameTransitionMonth($session['game_transition_month'])
                ->getGameTransitionMonthPretty();
            $session['end_month_formatted'] = $gameList
                ->setGameEndMonth($session['game_end_month'])
                ->getGameEndMonthPretty();
            if (empty($session['config_file_name'])) {
                $session['config_file_name'] = $session['gameConfigFilesFilename'];
            }
            unset($session['gameConfigFilesFilename']);
            $sessionList[$key] = $session;
        }
    }
}
