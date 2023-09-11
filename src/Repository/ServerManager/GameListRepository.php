<?php

namespace App\Repository\ServerManager;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Entity\ServerManager\GameList;
use App\Entity\ServerManager\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;

class GameListRepository extends EntityRepository
{
    public function __construct(EntityManagerInterface $em, ClassMetadata $class)
    {
        parent::__construct($em, $class);
    }

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
     */
    public function findBySessionState(string $value): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select([
                'g.id', 'g.name', 'gcv.id as game_config_version_id', 'gcv.version as config_version_version',
                'gcv.versionMessage as config_version_message', 'gcf.filename as config_file_name', 'gcv.region',
                
            ])
            ->leftJoin('g.gameConfigVersion', 'gcv')
            ->leftJoin('gcv.gameConfigFile', 'gcf')
            ->leftJoin('g.gameServer', 'gse')
            ->leftJoin('g.gameGeoServer', 'ggs')
            ->leftJoin('g.gameWatchdogServer', 'gws')
            ->leftJoin('g.gameSave', 'gsa');

        /*$qb->select('g.id, g.gameConfigVersion, g.gameServer, g.gameGeoServer, g.gameWatchdogServer, g.gameSave,
            g.name, g.gameCreationTime, g.gameStartYear, g.gameEndMonth, g.g.gameCurrentMonth, g.gameRunningTilTime,
            g.sessionState, g.gameState, g.gameVisibility, g.playersActive, g.playersPastHour, g.demoSession')
            ->from('App:ServerManager\GameList', 'g')
            ->leftJoin('g.gameConfigVersion', 'gv')*/
        if ($value == 'archived') {
            $qb->andWhere($qb->expr()->eq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        } else {
            $qb->andWhere($qb->expr()->neq('g.sessionState', ':val'))
                ->setParameter('val', new GameSessionStateValue('archived'));
        }
        return $qb->orderBy('g.id', 'ASC')
            ->getQuery()
            ->getResult()
            ;
    }

//    /**
//     * @return GameList[] Returns an array of GameList objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('g.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?GameList
//    {
//        return $this->createQueryBuilder('g')
//            ->andWhere('g.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
