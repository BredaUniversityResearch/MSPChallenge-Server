<?php

namespace App\Entity;

use App\Domain\Common\EntityEnums\EventLogSeverity;
use App\Repository\EventLogRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: EventLogRepository::class)]
class EventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'event_log_id')]
    private ?int $id = null;

    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column(name: 'event_log_time', type: Types::DATETIME_MUTABLE)]
    protected ?DateTime $time;

    #[ORM\Column(name: 'event_log_source', length: 75)]
    private ?string $source = null;

    #[ORM\Column(name: 'event_log_severity', enumType: EventLogSeverity::class)]
    private EventLogSeverity $severity = EventLogSeverity::WARNING;

    #[ORM\Column(name: 'event_log_message', type: Types::TEXT)]
    private ?string $message = null;

    #[ORM\Column(name: 'event_log_stack_trace', type: Types::TEXT, nullable: true)]
    private ?string $stackTrace = null;

    #[ORM\Column(name: 'event_log_reference_object', length: 255, nullable: true)]
    private ?string $referenceObject = null;

    #[ORM\Column(name: 'event_log_reference_id', nullable: true)]
    private ?int $referenceId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTime(): ?DateTime
    {
        return $this->time;
    }

    public function setTime(DateTime $time): void
    {
        $this->time = $time;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSeverity(): EventLogSeverity
    {
        return $this->severity;
    }

    public function setSeverity(EventLogSeverity $severity): static
    {
        $this->severity = $severity;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): static
    {
        $this->stackTrace = $stackTrace;

        return $this;
    }

    public function getReferenceObject(): ?string
    {
        return $this->referenceObject;
    }

    public function setReferenceObject(string $referenceObject): static
    {
        $this->referenceObject = $referenceObject;
        return $this;
    }

    public function getReferenceId(): ?int
    {
        return $this->referenceId;
    }

    public function setReferenceId(int $referenceId): static
    {
        $this->referenceId = $referenceId;
        return $this;
    }
}
