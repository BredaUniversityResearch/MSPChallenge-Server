<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Repository\ServerManager\TermsAcceptanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'terms_acceptances')]
#[ORM\UniqueConstraint(name: 'user_terms_unique', columns: ['user_id', 'terms_version_id'])]
#[ORM\Entity(repositoryClass: TermsAcceptanceRepository::class)]
class TermsAcceptance extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: TermsVersion::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?TermsVersion $termsVersion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    // @phpstan-ignore-next-line DateTimeInterface|null but database expects DateTimeInterface
    private ?\DateTimeInterface $acceptedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getTermsVersion(): ?TermsVersion
    {
        return $this->termsVersion;
    }

    public function setTermsVersion(TermsVersion $termsVersion): self
    {
        $this->termsVersion = $termsVersion;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeInterface
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(\DateTimeInterface $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }
}
