<?php
namespace App\Twig;

use App\Entity\ServerManager\GameList;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GameButtonDecider extends AbstractExtension
{
    public function __construct(
        private readonly EntityManagerInterface $mspServerManagerEntityManager
    ) {
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('gameButton', [$this, 'gameButtonDecide']),
        ];
    }

    public function gameButtonDecide(string $buttonType, array|GameList $gameSession)
    {
        if (is_array($gameSession)) {
            $gameSession = $this->mspServerManagerEntityManager->getRepository(GameList::class)
                ->createGameListFromData($gameSession);
        }
        if ($buttonType == 'play') {
            if ($gameSession->getSessionState() == 'healthy'
                && $gameSession->getGameState() == 'pause'
            ) {
                return true;
            }
        }
    }
}
