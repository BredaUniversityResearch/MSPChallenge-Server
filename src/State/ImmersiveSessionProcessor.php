<?php

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Domain\Common\EntityEnums\ImmersiveSessionStatus;
use App\Domain\Helper\RequestDataExtractor;
use App\Entity\SessionAPI\ImmersiveSession;
use App\Message\Docker\CreateImmersiveSessionContainerMessage;
use App\Message\Docker\RemoveImmersiveSessionContainerMessage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;

class ImmersiveSessionProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private readonly ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private readonly ProcessorInterface $removeProcessor,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    /**
     * @param ImmersiveSession $data
     * @throws \Exception
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var ?Request $request */
        $request = $context['request'] ?? null;
        if (!$request) {
            throw new \RuntimeException('Request not found in context');
        }
        $gameSessionId = RequestDataExtractor::getSessionIdFromRequest($request);

        $hasConnection = $data->getConnection() !== null;
        if ($operation instanceof DeleteOperationInterface) {
            if ($hasConnection) {
                $message = new RemoveImmersiveSessionContainerMessage();
                $message
                    ->setImmersiveSessionId($data->getId())
                    ->setDockerContainerId($data->getConnection()->getDockerContainerID())
                    ->setGameSessionId($gameSessionId);
                $this->messageBus->dispatch($message);
            }
            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        /** @var ImmersiveSession $result */
        $data->setStatus(ImmersiveSessionStatus::STARTING);
        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        if ($hasConnection) {
            return $result;
        }

        $message = new CreateImmersiveSessionContainerMessage();
        $message
            ->setImmersiveSessionId($result->getId())
            ->setGameSessionId($gameSessionId);
        $this->messageBus->dispatch($message);
        return $result;
    }
}
