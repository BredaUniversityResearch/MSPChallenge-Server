<?php

namespace App\Entity\ServerManager;

use App\Repository\ServerManager\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Table(name: 'users')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
class User extends UserBase
{
    public function getUserIdentifier(): ?int
    {
        return $this->getId();
    }
}
