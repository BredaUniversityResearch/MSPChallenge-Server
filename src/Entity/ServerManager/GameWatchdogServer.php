<?php

namespace App\Entity\ServerManager;

use App\Entity\EntityBase;
use App\Entity\Interface\WatchdogInterface;
use App\Entity\Mapping as AppMappings;
use App\Entity\Trait\WatchdogTrait;
use App\Repository\ServerManager\GameWatchdogServerRepository;
use App\Validator as AcmeAssert;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[AppMappings\ReadonlyIDs([1])]
#[AppMappings\Plurals('Watchdog server', 'Watchdog servers')]
#[ORM\Table(name: 'game_watchdog_servers', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uq_server_id', columns: ['server_id']),
    new ORM\UniqueConstraint(name: 'uq_scheme_address_port', columns: ['scheme', 'address', 'port']),
])]
#[ORM\Entity(repositoryClass: GameWatchdogServerRepository::class)]
class GameWatchdogServer extends EntityBase implements WatchdogInterface
{
    use WatchdogTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[AppMappings\Property\FormFieldType(type: SymfonyFormType\UuidType::class)]
    #[ORM\Column(type: UuidType::NAME)]
    private ?Uuid $serverId = null;

    #[AppMappings\Property\TableColumn(label: "Server name")]
    #[Assert\NotBlank]
    #[ORM\Column(length: 128, unique: true)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[AppMappings\Property\TableColumn(label: "Fully-qualified URL")]
    #[Assert\NotBlank]
    #[AcmeAssert\Address]
    #[ORM\Column(length: 255, unique: true)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $address = null;

    #[ORM\Column(options: ['default' => 80])]
    private int $port = 80;

     #[ORM\Column(length: 255, options: ['default' => 'http'])]
    private string $scheme = 'http';

    /**
     * see https://github.com/dunglas/doctrine-json-odm
     * see https://www.doctrine-project.org/projects/doctrine-dbal/en/4.0/reference/types.html#json
     */
    #[Assert\NotBlank]
    #[AcmeAssert\SimulationSettings]
    #[ORM\Column(type: 'json_document', nullable: true, options: ['default' => 'NULL'])]
    #[AppMappings\Property\FormFieldType(
        type: SymfonyFormType\TextareaType::class,
        options: [
            'attr' => [
                'class' => 'form-control',
                'rows' => 10,
                'placeholder' => <<<'JSON'
                {
                  "simulation_type": "External",
                  "kpis": [
                    {
                      "categoryName": "...kpi category here...",
                      "unit": "...kpi unit here...",
                      "valueDefinitions": [
                        {
                          "valueName": "...kpi name here..."
                        }
                      ]
                    }
                  ]
                }
                JSON
            ]
        ]
    )]
    private mixed $simulationSettings = null;

    #[AppMappings\Property\TableColumn(action: true, toggleable: true, availability: true)]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    // @phpstan-ignore-next-line bool|null but database expects bool
    private ?bool $available = true;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;

        return $this;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setScheme(string $scheme): static
    {
        $this->scheme = $scheme;

        return $this;
    }

    public function getSimulationSettings(): mixed
    {
        return $this->simulationSettings;
    }

    public function setSimulationSettings(mixed $simulationSettings): static
    {
        $this->simulationSettings = $simulationSettings;

        return $this;
    }

    public function getAvailable(): ?bool
    {
        return $this->available;
    }

    public function setAvailable(?bool $available): self
    {
        $this->available = $available;

        return $this;
    }

    public function createUrl(): string
    {
        $scheme = str_replace('://', '', (
            $this->getServerId()->toRfc4122() == WatchdogInterface::INTERNAL_SERVER_ID_RFC4122 ?
                $_ENV['WATCHDOG_SCHEME'] : null
            ) ??
            $this->getScheme());
        $port = (
            $this->getServerId()->toRfc4122() == WatchdogInterface::INTERNAL_SERVER_ID_RFC4122 ?
                $_ENV['WATCHDOG_PORT'] : null
            ) ??
            $this->getPort();
        return "{$scheme}://{$this->getAddress()}:{$port}";
    }
}
