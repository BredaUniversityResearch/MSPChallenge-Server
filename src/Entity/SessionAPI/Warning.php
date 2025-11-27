<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Controller\SessionAPI\WarningController;
use App\Domain\Common\EntityEnums\WarningIssueType;
use App\Repository\SessionAPI\WarningRepository;
use ArrayObject;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WarningRepository::class)]
#[ApiResource(
    description: 'Endpoints for management related to warnings',
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Patch(),
        new Delete(),
        new Post(
            uriTemplate: '/{warning}/Post',
            uriVariables: [
                'warning' => new Link(
                    schema: ['type' => 'string', 'default' => 'warning', 'enum' => ['warning', 'Warning']],
                    property: 'warning',
                    required: true
                ),
            ],
            requirements: ['warning' => '[wW]arning'],
            controller: WarningController::class . '::post',
            openapi: new Operation(
                responses: [
                    200 => new Response(
                        description: 'Successful operation',
                        content: new ArrayObject([
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/WarningIssueStructure']
                            ]
                        ])
                    )
                ],
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/x-www-form-urlencoded' => [
                            'schema' => [
                                'required' => ['plan', 'planlayer_id'],
                                'properties' => [
                                    'plan' => [
                                        'type' => 'integer',
                                        'description' => 'Plan ID'
                                    ],
                                    'planlayer_id' => [
                                        'type' => 'integer',
                                        'description' => 'The ID of the plan player'
                                    ],
                                    'added' => [
                                        'type' => 'string',
                                        // phpcs:ignore
                                        'description' => 'JSON array of issues to are added. Each item being a PlanIssueObject<br>Example:<br><pre>[<br>    {<br>        "issue_database_id": 1,<br>        "type": "Error",<br>        "active": true,<br>        "x": 0,<br>        "y": 0,<br>        "restriction_id": 1<br>    },<br>    {<br>        "issue_database_id": 2,<br>        "type": "Error",<br>        "active": true,<br>        "x": 0,<br>        "y": 0,<br>        "custom_restriction_id": 10001<br>    }<br>]</pre>',
                                        'format' => 'json',
                                        'default' => null
                                    ],
                                    'removed' => [
                                        'type' => 'string',
                                        // phpcs:ignore
                                        'description' => 'JSON array of issues to are removed. Each item being a PlanIssueObject',
                                        'format' => 'json',
                                        'default' => null
                                    ]
                                ]
                            ]
                        ]
                    ]),
                    required: true
                )
            ),
            name: 'session_api_warning_post'
        )
    ],
    openapi: new Operation(
        tags: ['✨ Warning']
    )
)]
class Warning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'warning_id')]
    private ?int $id = null;

    #[ORM\Column(name: 'warning_last_update', nullable: true)]
    private ?float $lastUpdate = null;

    #[ORM\Column(name: 'warning_active')]
    private ?bool $active = null;

    #[ORM\JoinColumn(name: 'warning_layer_id', referencedColumnName: 'layer_id', nullable: false)]
    private ?Layer $layer = null;

    #[ORM\Column(name: 'warning_issue_type', enumType: WarningIssueType::class)]
    private ?WarningIssueType $issueType = null;

    #[ORM\Column(name: 'warning_x')]
    private ?float $x = null;

    #[ORM\Column(name: 'warning_y')]
    private ?float $y = null;

    #[ORM\JoinColumn(name: 'warning_source_plan_id', referencedColumnName: 'plan_id', nullable: false)]
    private ?Plan $sourcePlan = null;

    #[ORM\JoinColumn(name: 'warning_restriction_id', referencedColumnName: 'restriction_id', nullable: true)]
    private ?Restriction $restriction = null;

    #[ORM\Column(nullable: true)]
    private ?int $customRestrictionId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastUpdate(): ?float
    {
        return $this->lastUpdate;
    }

    public function setLastUpdate(float $lastUpdate): static
    {
        $this->lastUpdate = $lastUpdate;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLayer(): ?Layer
    {
        return $this->layer;
    }

    public function setLayer(Layer $layer): static
    {
        $this->layer = $layer;

        return $this;
    }

    public function getIssueType(): ?WarningIssueType
    {
        return $this->issueType;
    }

    public function setIssueType(WarningIssueType $issueType): static
    {
        $this->issueType = $issueType;

        return $this;
    }

    public function getX(): ?float
    {
        return $this->x;
    }

    public function setX(float $x): static
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): ?float
    {
        return $this->y;
    }

    public function setY(float $y): static
    {
        $this->y = $y;

        return $this;
    }

    public function getSourcePlan(): ?Plan
    {
        return $this->sourcePlan;
    }

    public function setSourcePlan(Plan $sourcePlan): static
    {
        $this->sourcePlan = $sourcePlan;

        return $this;
    }

    public function getRestriction(): ?Restriction
    {
        return $this->restriction;
    }

    public function setRestriction(?Restriction $restriction): static
    {
        $this->restriction = $restriction;

        return $this;
    }

    public function getCustomRestrictionId(): ?int
    {
        return $this->customRestrictionId;
    }

    public function setCustomRestrictionId(?int $customRestrictionId): static
    {
        $this->customRestrictionId = $customRestrictionId;

        return $this;
    }
}
