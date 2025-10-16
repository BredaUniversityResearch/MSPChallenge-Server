<?php

namespace App\Entity\Trait;

use Doctrine\ORM\EntityManagerInterface;

trait EntityOriginTrait
{
    private ?EntityManagerInterface $originManager = null;

    public function setOriginManager(EntityManagerInterface $manager): static
    {
        $this->originManager = $manager;
        return $this;
    }

    public function getOriginManager(): ?EntityManagerInterface
    {
        return $this->originManager;
    }

    public function getOriginDatabase(): ?string
    {
        if (null === $this->originManager) {
            return null;
        }
        try {
            return $this->originManager->getConnection()->getDatabase();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getOriginGameListId(): ?int
    {
        if (null === $this->originManager) {
            return null;
        }
        if (null === $database = $this->getOriginDatabase()) {
            return null;
        }
        if (1 !== preg_match('/'.($_ENV['DBNAME_SESSION_PREFIX'] ?? 'msp_session_').'(\d+)/', $database, $matches)) {
            return null;
        }
        return (int)$matches[1];
    }
}
