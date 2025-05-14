<?php

namespace App\src\Entity\SessionAPI;

use App\src\Repository\SessionAPI\ObjectiveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;

#[ORM\Entity(repositoryClass: ObjectiveRepository::class)]
class Objective
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $objectiveId;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'objective')]
    #[JoinColumn(name: 'objective_country_id', referencedColumnName: 'country_id')]
    private ?Country $country;

    #[ORM\Column(type: Types::STRING, length: 128)]
    private ?string $objectiveTitle;

    #[ORM\Column(type: Types::TEXT, length: 1024)]
    private ?string $objectiveDescription;

    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $objectiveDeadline;

    #[ORM\Column(type: Types::FLOAT, length: 11)]
    private ?float $objectiveLastupdate;

    #[ORM\Column(type: Types::SMALLINT, length: 1)]
    private ?int $objectiveActive = 1;

    #[ORM\Column(type: Types::SMALLINT, length: 1)]
    private ?int $objectiveComplete = 0;

    public function getObjectiveId(): ?int
    {
        return $this->objectiveId;
    }

    public function setObjectiveId(?int $objectiveId): Objective
    {
        $this->objectiveId = $objectiveId;
        return $this;
    }

    public function getCountry(): ?Country
    {
        return $this->country;
    }

    public function setCountry(?Country $country): Objective
    {
        $this->country = $country;
        return $this;
    }

    public function getObjectiveTitle(): ?string
    {
        return $this->objectiveTitle;
    }

    public function setObjectiveTitle(?string $objectiveTitle): Objective
    {
        $this->objectiveTitle = $objectiveTitle;
        return $this;
    }

    public function getObjectiveDescription(): ?string
    {
        return $this->objectiveDescription;
    }

    public function setObjectiveDescription(?string $objectiveDescription): Objective
    {
        $this->objectiveDescription = $objectiveDescription;
        return $this;
    }

    public function getObjectiveDeadline(): ?int
    {
        return $this->objectiveDeadline;
    }

    public function setObjectiveDeadline(?int $objectiveDeadline): Objective
    {
        $this->objectiveDeadline = $objectiveDeadline;
        return $this;
    }

    public function getObjectiveLastupdate(): ?float
    {
        return $this->objectiveLastupdate;
    }

    public function setObjectiveLastupdate(?float $objectiveLastupdate): Objective
    {
        $this->objectiveLastupdate = $objectiveLastupdate;
        return $this;
    }

    public function getObjectiveActive(): ?int
    {
        return $this->objectiveActive;
    }

    public function setObjectiveActive(?int $objectiveActive): Objective
    {
        $this->objectiveActive = $objectiveActive;
        return $this;
    }

    public function getObjectiveComplete(): ?int
    {
        return $this->objectiveComplete;
    }

    public function setObjectiveComplete(?int $objectiveComplete): Objective
    {
        $this->objectiveComplete = $objectiveComplete;
        return $this;
    }
}
