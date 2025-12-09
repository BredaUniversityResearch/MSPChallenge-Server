<?php

namespace App\Entity\Trait;

use Doctrine\ORM\EntityManagerInterface;

trait EntityOriginTrait
{
    private ?int $originGameListId = null;

    public function getOriginGameListId(): ?int
    {
        return $this->originGameListId;
    }

    public function setOriginGameListId(int $originGameListId): static
    {
        $this->originGameListId = $originGameListId;
        return $this;
    }
}
