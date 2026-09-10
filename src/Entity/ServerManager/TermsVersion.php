<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Repository\ServerManager\TermsVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'terms_versions')]
#[ORM\Entity(repositoryClass: TermsVersionRepository::class)]
class TermsVersion extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $version = null;

    // relative to the project root, e.g. 'docs/TOS/terms.md'
    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $filePath = null;

    #[ORM\Column(length: 150, options: ['default' => '/^\d+\.\s*Acknowledgment$/i'])]
    private string $acknowledgmentHeadingPattern = '/^\d+\.\s*Acknowledgment$/i';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    // @phpstan-ignore-next-line DateTimeInterface|null but database expects DateTimeInterface
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private bool $current = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getAcknowledgmentHeadingPattern(): string
    {
        return $this->acknowledgmentHeadingPattern;
    }

    public function setAcknowledgmentHeadingPattern(string $pattern): self
    {
        $this->acknowledgmentHeadingPattern = $pattern;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    public function setCurrent(bool $current): self
    {
        $this->current = $current;

        return $this;
    }
}
