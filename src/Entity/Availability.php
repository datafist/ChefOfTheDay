<?php

namespace App\Entity;

use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\Table(name: 'availabilities')]
#[ORM\UniqueConstraint(name: 'party_year_unique', columns: ['party_id', 'kita_year_id'])]
#[ORM\HasLifecycleCallbacks]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Party $party = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?KitaYear $kitaYear = null;

    /**
     * @var array<string> Array of date strings in Y-m-d format (Tage an denen die Familie verfügbar ist)
     */
    #[ORM\Column(type: Types::JSON)]
    private array $availableDates = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PostLoad]
    public function initializeArrays(): void
    {
        // Stelle sicher dass Array initialisiert ist, auch wenn NULL aus DB kommt
        if (!isset($this->availableDates)) {
            $this->availableDates = [];
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParty(): ?Party
    {
        return $this->party;
    }

    public function setParty(?Party $party): static
    {
        $this->party = $party;
        return $this;
    }

    public function getKitaYear(): ?KitaYear
    {
        return $this->kitaYear;
    }

    public function setKitaYear(?KitaYear $kitaYear): static
    {
        $this->kitaYear = $kitaYear;
        return $this;
    }

    public function getAvailableDates(): array
    {
        return $this->availableDates;
    }

    public function setAvailableDates(array $availableDates): static
    {
        $this->availableDates = $availableDates;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function addAvailableDate(string $date): static
    {
        if (!in_array($date, $this->availableDates, true)) {
            $this->availableDates[] = $date;
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function removeAvailableDate(string $date): static
    {
        $key = array_search($date, $this->availableDates, true);
        if ($key !== false) {
            unset($this->availableDates[$key]);
            $this->availableDates = array_values($this->availableDates);
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function isDateAvailable(string $date): bool
    {
        return in_array($date, $this->availableDates, true);
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
