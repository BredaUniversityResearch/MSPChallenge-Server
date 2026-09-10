<?php

namespace App\Repository\ServerManager;

use App\Entity\ServerManager\TermsVersion;
use Doctrine\ORM\EntityRepository;

class TermsVersionRepository extends EntityRepository
{
    public function getCurrent(): ?TermsVersion
    {
        /** @var TermsVersion|null */
        return $this->findOneBy(['current' => true]);
    }
}
