<?php
namespace App\Twig;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
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
            new TwigFunction('showGameButton', [$this, 'gameButtonDecide']),
        ];
    }

    public function gameButtonDecide(string $buttonType, array|GameList $gameSession)
    {
        if (is_array($gameSession)) {
            $gameSession = $this->mspServerManagerEntityManager->getRepository(GameList::class)
                ->createGameListFromData($gameSession);
        }
        switch ($buttonType) {
            case 'recreate':
            case 'archive':
                if ($gameSession->getSessionState() != GameSessionStateValue::ARCHIVED) {
                    return true;
                }
                break;
            case 'play':
                if ($gameSession->getSessionState() == GameSessionStateValue::HEALTHY
                    && ($gameSession->getGameState() == GameStateValue::PAUSE
                        || $gameSession->getGameState() == GameStateValue::FASTFORWARD)) {
                    return true;
                }
                break;
            case 'pause':
                if ($gameSession->getSessionState() == GameSessionStateValue::HEALTHY
                    && ($gameSession->getGameState() == GameStateValue::PLAY
                        || $gameSession->getGameState() == GameStateValue::FASTFORWARD
                        || $gameSession->getGameState() == GameStateValue::SETUP)) {
                    return true;
                }
                break;
            case 'fastforward':
                if ($gameSession->getSessionState() == GameSessionStateValue::HEALTHY
                    && ($gameSession->getGameState() == GameStateValue::PLAY
                        || $gameSession->getGameState() == GameStateValue::PAUSE)) {
                    return true;
                }
                break;
            case 'access':
            case 'demo':
                if ($gameSession->getSessionState() == GameSessionStateValue::HEALTHY) {
                    return true;
                }
                break;
            case 'save':
            case 'export':
                if ($gameSession->getSessionState() == GameSessionStateValue::HEALTHY
                    && ($gameSession->getGameState() == GameStateValue::PAUSE
                        || $gameSession->getGameState() == GameStateValue::SETUP
                        || $gameSession->getGameState() == GameStateValue::END)) {
                    return true;
                }
                break;
        }
        return false;
    }
}
