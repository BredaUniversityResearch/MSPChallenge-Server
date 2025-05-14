<?php

namespace App\src\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiResource;
use App\src\Repository\SessionAPI\ImmersiveSessionRegionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImmersiveSessionRegionRepository::class)]
#[ApiResource(
    description: 'Region of the immersive session. The coordinates are in the EPSG:3035 coordinate system.'.
        ' Tip: use <A href="http://bboxfinder.com">bboxfinder.com</A> to retrieve the coordinates.'
)]
class ImmersiveSessionRegion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?float $bottomLeftX = null;

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

    public function getBottomLeftX(): ?float
    {
        return $this->bottomLeftX;
    }

    public function setBottomLeftX(float $bottomLeftX): static
    {
        $this->bottomLeftX = $bottomLeftX;

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
