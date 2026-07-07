<?php

namespace App\Controller\ServerManager;

use App\Controller\BaseController;
use App\Entity\ServerManager\GameList;
use App\Repository\ServerManager\GameListRepository;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/{manager}/database',
    requirements: ['manager' => 'manager|ServerManager'],
    defaults: ['manager' => 'manager']
)]
class DatabaseController extends BaseController
{
    /**
     * @throws Exception
     */
    #[Route('', name: 'manager_database')]
    public function index(Request $request): Response
    {
        $sessionId = (int) $request->query->get('sessionId', 0);
        $entityManager = $this->connectionManager->getServerManagerEntityManager();
        /** @var GameListRepository $repo */
        $repo = $entityManager->getRepository(GameList::class);

        return $this->render('manager/database_page.html.twig', [
            'sessions' => $repo->findBySessionState('public'),
            'selectedSessionId' => $sessionId,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/table', name: 'manager_database_table')]
    public function table(Request $request): Response
    {
        $sessionId = (int) $request->query->get('sessionId', 0);
        $rows = $this->getConnectionRows();

        if ($sessionId > 0) {
            $sessionDbName = $this->connectionManager->getGameSessionDbName($sessionId);
            $rows = array_values(array_filter(
                $rows,
                static fn(array $row): bool => (string) ($row['db'] ?? '') === $sessionDbName
            ));
        }

        return $this->render('manager/Database/database_table.html.twig', [
            'rows' => $rows,
            'selectedSessionId' => $sessionId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getConnectionRows(): array
    {
        $conn = null;
        try {
            $conn = $this->connectionManager->createDbConnection($this->connectionManager->getServerManagerDbName());

            return $conn->executeQuery(<<<'SQL'
SELECT
    pl.db,
    IFNULL(ct.process_name, CONCAT('unknown_', pl.ID)) AS process,
    pl.TIME AS duration
FROM information_schema.PROCESSLIST pl
LEFT JOIN msp_tracker.connection ct ON pl.ID = ct.connection_id
WHERE pl.db IS NOT NULL
ORDER BY pl.db ASC, pl.TIME DESC
SQL)->fetchAllAssociative();
        } catch (\Throwable) {
            return [];
        } finally {
            $conn?->close();
        }
    }
}
