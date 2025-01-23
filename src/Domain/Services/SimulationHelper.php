<?php

namespace App\Domain\Services;

use App\Entity\ServerManager\GameList;
use App\VersionsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class SimulationHelper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VersionsProvider $provider
    ) {
    }

    /**
     * @throws Exception
     */
    public function getConfiguredSimulationTypes(int $sessionId, ?array $dataModel = null): array
    {
        $result = array();
        if ($dataModel === null) {
            if (null === $gameList = $this->em->getRepository(GameList::class)->find($sessionId)) {
                throw new Exception('Game list not found');
            }
            if (null === $config = $gameList->getGameConfigVersion()->getGameConfigComplete()) {
                throw new Exception('Game config not found');
            }
            if (null === $dataModel = ($config['data_model'] ?? null)) {
                throw new Exception('Data model not found');
            }
        }
        $possibleSims = $this->provider->getComponentsVersions();
        foreach ($possibleSims as $possibleSim => $possibleSimVersion) {
            if (array_key_exists($possibleSim, $dataModel) && is_array($dataModel[$possibleSim])) {
                $versionString = $possibleSimVersion;
                if (array_key_exists("force_version", $dataModel[$possibleSim])) {
                    $versionString = $dataModel[$possibleSim]["force_version"];
                }
                $result[$possibleSim] = $versionString;
            }
        }
        return $result;
    }
}
