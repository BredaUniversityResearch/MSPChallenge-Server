<?php

namespace App\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Tag;
use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

#[AsDecorator(decorates: 'api_platform.openapi.factory')]
readonly class OpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(private OpenApiFactoryInterface $decorated)
    {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = $this->decorated->__invoke($context);
        return $openApi->withTags(
            collect($openApi->getTags())
                ->map(
                    fn(Tag $tag) => $tag->withDescription(
                        'Endpoints for '.
                        // strip unicode emoji
                        preg_replace('/[^\x00-\x7F]/', '', $tag->getName()).
                        ' management in API Platform'
                    )
                )->all()
        );
    }
}
