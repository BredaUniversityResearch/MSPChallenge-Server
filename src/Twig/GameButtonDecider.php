<?php
namespace App\Twig;

use App\Domain\Common\EntityEnums\GameSessionStateValue;
use App\Domain\Common\EntityEnums\GameStateValue;
use App\Domain\Common\GameListAndSaveSerializer;
use App\Domain\Services\ConnectionManager;
use App\Entity\ServerManager\GameList;
use Exception;
use ReflectionException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GameButtonDecider extends AbstractExtension
{
    public function __construct(
        private readonly ConnectionManager $connectionManager
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('showGameButton', [$this, 'gameButtonDecide']),
        ];
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function gameButtonDecide(string $buttonType, array|GameList $gameSession): bool
    {
        if (is_array($gameSession)) {
            $serializer = new GameListAndSaveSerializer($this->connectionManager);
            $gameSession = $serializer->createGameListFromData($gameSession);
        }
        switch ($buttonType) {
            case 'recreate':
            case 'archive':
                if ($gameSession->getSessionState() != GameSessionStateValue::ARCHIVED
                    && $gameSession->getSessionState() != GameSessionStateValue::REQUEST) {
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
