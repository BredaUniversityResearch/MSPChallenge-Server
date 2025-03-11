<?php
// Important!
// The reason this isn't split over multiple Doctrine Entity listeners, is because then the services.yaml config
// would have needed a specification of the entity manager in use for said listener (which is impossible):
// App\Entity\Listener\GameListener:
//  tags:
//   - { name: 'doctrine.orm.entity_listener',
//       event: 'prePersist',
//       entity: 'App\Entity\Game',
//       entity_manager: 'msp_session_1'
//     }

namespace App\Entity\Listener;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Exception;

class SessionEntityListener implements PostLoadEventListenerInterface, PrePersistEventListenerInterface
{
    /** @var (SubEntityListenerInterface|PostLoadEventListenerInterface|PrePersistEventListenerInterface)[]  */
    private array $listeners = [];

    public function __construct(
        array $listeners
    ) {
        $listeners = array_filter($listeners, function ($listener) {
            return $listener instanceof SubEntityListenerInterface;
        });
        foreach ($listeners as $listener) {
            $supportedEntityClasses = $listener->getSupportedEntityClasses();
            foreach ($supportedEntityClasses as $supportedEntityClass) {
                $this->listeners[$supportedEntityClass] = $listener;
            }
        }
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        foreach ($this->listeners as $entityClassName => $listener) {
            if ($entityClassName != get_class($event->getObject())) {
                continue;
            }
            if (!($listener instanceof PrePersistEventListenerInterface)) {
                continue;
            }
            $listener->prePersist($event);
        }
    }

    /**
     * @throws Exception
     */
    public function postLoad(PostLoadEventArgs $event): void
    {
        foreach ($this->listeners as $entityClassName => $listener) {
            if ($entityClassName != get_class($event->getObject())) {
                continue;
            }
            if (!($listener instanceof PostLoadEventListenerInterface)) {
                continue;
            }
            $listener->postLoad($event);
        }
    }
}
