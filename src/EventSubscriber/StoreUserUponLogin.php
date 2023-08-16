<?php

namespace App\EventSubscriber;

use App\Entity\ServerManager\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

class StoreUserUponLogin implements EventSubscriberInterface
{
    private EntityManagerInterface $mspServerManagerEntityManager;

    public function __construct(
        EntityManagerInterface $mspServerManagerEntityManager,
    ) {
        $this->mspServerManagerEntityManager = $mspServerManagerEntityManager;
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        // @phpstan-ignore-next-line "Call to an undefined method"
        $user = $event->getPassport()->getUser();
        if ($user instanceof User) {
            $storedUser = $this->mspServerManagerEntityManager->getRepository(User::class)->find($user->getId());
            if (is_null($storedUser)) {
                $user->setToken('temp');
                $user->setRefreshToken('temp');
                $user->setRefreshTokenExpiration(new \DateTimeImmutable());
                $this->mspServerManagerEntityManager->persist($user);
                $this->mspServerManagerEntityManager->flush();
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', -10],
        ];
    }
}
