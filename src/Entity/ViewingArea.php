<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\ViewingAreaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ViewingAreaRepository::class)]
#[ApiResource]
class ViewingArea
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $buttomLeftX = null;

    #[ORM\Column]
    private ?float $bottomLeftY = null;

    #[ORM\Column]
    private ?float $topRightX = null;

    #[ORM\Column]
    private ?float $topRightY = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getButtomLeftX(): ?float
    {
        return $this->buttomLeftX;
    }

    public function setButtomLeftX(float $buttomLeftX): static
    {
        $this->buttomLeftX = $buttomLeftX;

        return $this;
    }

    public function getBottomLeftY(): ?float
    {
        return $this->bottomLeftY;
    }

    public function setBottomLeftY(float $bottomLeftY): static
    {
        $this->bottomLeftY = $bottomLeftY;

        return $this;
    }

    public function getTopRightX(): ?float
    {
        return $this->topRightX;
    }

    public function setTopRightX(float $topRightX): static
    {
        $this->topRightX = $topRightX;

        return $this;
    }

    public function getTopRightY(): ?float
    {
        return $this->topRightY;
    }

    public function setTopRightY(float $topRightY): static
    {
        $this->topRightY = $topRightY;

        return $this;
    }
}
