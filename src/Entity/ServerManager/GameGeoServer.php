<?php

namespace App\Entity\ServerManager;

use App\Domain\Common\EntityEnums\GeoServerAccessType;
use App\Entity\EntityBase;
use App\Entity\Mapping as AppMappings;
use App\Repository\ServerManager\GameGeoServerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Form\Extension\Core\Type as SymfonyFormType;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[AppMappings\Plurals('GeoServer', 'GeoServers')]
#[ORM\Table(name: 'game_geoservers')]
#[ORM\Entity(repositoryClass: GameGeoServerRepository::class)]
#[Assert\Callback([self::class, 'validateCredentials'])]
class GameGeoServer extends EntityBase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['write'])]
    #[AppMappings\Property\TableColumn(label: "Name")]
    #[Assert\NotBlank]
    #[ORM\Column(length: 128)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $name = null;

    #[Groups(['write'])]
    #[AppMappings\Property\TableColumn(label: "Fully-qualified URL")]
    #[Assert\NotBlank]
    #[Assert\Url]
    #[ORM\Column(length: 255)]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $address = null;

    #[Groups(['write'])]
    #[AppMappings\Property\TableColumn(label: "Access type")]
    #[AppMappings\Property\FormFieldType(type: SymfonyFormType\ChoiceType::class, options: [
        'attr' => [
            'data-conditional-controller' => 'true',
        ],
    ])]
    #[ORM\Column(
        type: Types::STRING,
        length: 20,
        enumType: GeoServerAccessType::class,
        options: ['default' => 'credentials']
    )]
    private GeoServerAccessType $accessType = GeoServerAccessType::CREDENTIALS;

    // ---------------------------------------------------------------------------
    // Encrypted backing columns (stored in DB as ciphertext, excluded from forms).
    // Access via getEncryptedUsername() / setEncryptedUsername() — listener use only.
    // ---------------------------------------------------------------------------

    /** @internal — managed by GameGeoServerListener */
    #[Groups(['db'])]
    #[ORM\Column(name: 'username', length: 512, nullable: true)]
    private ?string $usernameEncrypted = null;

    /** @internal — managed by GameGeoServerListener */
    #[Groups(['db'])]
    #[ORM\Column(name: 'password', length: 512, nullable: true)]
    private ?string $passwordEncrypted = null;

    // ---------------------------------------------------------------------------
    // Plain-text runtime properties (NOT ORM-mapped, populated by GameGeoServerListener
    // on postLoad and read/written by the form / application code).
    // ---------------------------------------------------------------------------

    #[Groups(['write'])]
    #[AppMappings\Property\FormFieldType(type: SymfonyFormType\TextType::class, options: [
        'label' => 'Username',
        'required' => false,
        'help' => '',
        'attr' => [
            'data-conditional-show-when' => 'accessType=credentials',
            'autocomplete' => 'username',
        ],
    ])]
    private ?string $username = null;

    #[Groups(['write'])]
    #[AppMappings\Property\FormFieldType(type: SymfonyFormType\PasswordType::class, options: [
        'label' => 'Password',
        'required' => false,
        'always_empty' => false,
        'attr' => [
            'data-conditional-show-when' => 'accessType=credentials',
            'autocomplete' => 'new-password'
        ],
    ])]
    private ?string $password = null;

    #[Groups(['write'])]
    #[AppMappings\Property\TableColumn(action: true, toggleable: true, availability: true)]
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => 1])]
    // @phpstan-ignore-next-line bool|null but database expects bool
    private ?bool $available = true;

    // ---------------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getAccessType(): GeoServerAccessType
    {
        return $this->accessType;
    }

    public function setAccessType(GeoServerAccessType $accessType): self
    {
        $this->accessType = $accessType;
        return $this;
    }

    /** Plain-text username, populated by the listener after loading from DB. */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /** Set the plain-text username; the listener encrypts it before writing to DB. */
    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /** Plain-text password, populated by the listener after loading from DB. */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /** Set the plain-text password; the listener encrypts it before writing to DB. */
    public function setPassword(?string $password): self
    {
        $this->password = $password;
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

    public static function validateCredentials(self $entity, ExecutionContextInterface $context): void
    {
        if ($entity->getAccessType() !== GeoServerAccessType::CREDENTIALS) {
            return;
        }
        // Accept the field if either the plain-text runtime value (typed in the form)
        // OR the encrypted backing column (already saved) is non-empty.
        // This prevents validation errors when editing an entity without changing credentials.
        if (empty($entity->getUsername()) && empty($entity->getEncryptedUsername())) {
            $context->buildViolation('Username is required when using credentials.')
                ->atPath('username')
                ->addViolation();
        }
        if (empty($entity->getPassword()) && empty($entity->getEncryptedPassword())) {
            $context->buildViolation('Password is required when using credentials.')
                ->atPath('password')
                ->addViolation();
        }
    }

    public function hasStoredUsername(): bool
    {
        return !empty($this->usernameEncrypted);
    }

    public function hasStoredPassword(): bool
    {
        return !empty($this->passwordEncrypted);
    }

    // ---------------------------------------------------------------------------
    // Internal accessors for GameGeoServerListener — do not call from application code.
    // ---------------------------------------------------------------------------

    /** @internal */
    public function getEncryptedUsername(): ?string
    {
        return $this->usernameEncrypted;
    }

    /** @internal */
    public function setEncryptedUsername(?string $encrypted): void
    {
        $this->usernameEncrypted = $encrypted;
    }

    /** @internal */
    public function getEncryptedPassword(): ?string
    {
        return $this->passwordEncrypted;
    }

    /** @internal */
    public function setEncryptedPassword(?string $encrypted): void
    {
        $this->passwordEncrypted = $encrypted;
    }
}
