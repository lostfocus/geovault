<?php

/** @noinspection PhpGetterAndSetterCanBeReplacedWithPropertyHooksInspection */

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /**
     * @phpstan-ignore property.unusedType
     */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private Database $locationDatabase;

    #[ORM\Column(length: 63)]
    private ?string $startString = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startUTC = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endUTC = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mode = null;

    #[ORM\Column(nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(nullable: true)]
    private ?float $duration = null;

    #[ORM\Column(nullable: true)]
    private ?int $steps = null;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $content = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @noinspection PhpUnused */
    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getLocationDatabase(): Database
    {
        return $this->locationDatabase;
    }

    public function setLocationDatabase(Database $locationDatabase): static
    {
        $this->locationDatabase = $locationDatabase;

        return $this;
    }

    public function getStartString(): ?string
    {
        return $this->startString;
    }

    public function setStartString(string $startString): static
    {
        $this->startString = $startString;

        return $this;
    }

    public function getStartUTC(): ?\DateTimeImmutable
    {
        return $this->startUTC;
    }

    public function setStartUTC(?\DateTimeImmutable $startUTC): static
    {
        $this->startUTC = $startUTC;

        return $this;
    }

    public function getEndUTC(): ?\DateTimeImmutable
    {
        return $this->endUTC;
    }

    public function setEndUTC(?\DateTimeImmutable $endUTC): static
    {
        $this->endUTC = $endUTC;

        return $this;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getSteps(): ?int
    {
        return $this->steps;
    }

    public function setSteps(?int $steps): static
    {
        $this->steps = $steps;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @param array<string, mixed> $content
     *
     * @return $this
     */
    public function setContent(array $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
