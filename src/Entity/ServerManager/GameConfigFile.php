<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\GameConfigFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OneToMany;

#[ORM\Table(name: 'game_config_files')]
#[ORM\Entity(repositoryClass: GameConfigFileRepository::class)]
class GameConfigFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * no whitespaces and no special characters please and without file extension (.json)
     */
    #[ORM\Column(length: 45)]
    private ?string $filename = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[OneToMany(targetEntity: GameConfigVersion::class, mappedBy: 'gameConfigFile', cascade: ['persist'])]
    private Collection $gameConfigVersion;

    public function __construct()
    {
        $this->gameConfigVersion = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getGameConfigVersion(): Collection
    {
        return $this->gameConfigVersion;
    }

    public function addGameConfigVersion(GameConfigVersion $gameConfigVersion): self
    {
        if (!$this->gameConfigVersion->contains($gameConfigVersion)) {
            $this->gameConfigVersion->add($gameConfigVersion);
            $gameConfigVersion->setGameConfigFile($this);
        }

        return $this;
    }

    public function removeGameConfigVersion(GameConfigVersion $gameConfigVersion): self
    {
        if ($this->gameConfigVersion->removeElement($gameConfigVersion)) {
            // set the owning side to null (unless already changed)
            if ($gameConfigVersion->getGameConfigFile() === $this) {
                $gameConfigVersion->setGameConfigFile(null);
            }
        }

        return $this;
    }
}
