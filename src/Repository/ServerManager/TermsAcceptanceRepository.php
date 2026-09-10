<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\TermsVersion;
use App\Entity\ServerManager\User;
use Doctrine\ORM\EntityRepository;

class TermsAcceptanceRepository extends EntityRepository
{
    public function hasAccepted(User $user, TermsVersion $termsVersion): bool
    {
        return null !== $this->findOneBy([
            'user' => $user,
            'termsVersion' => $termsVersion,
        ]);
    }
}
