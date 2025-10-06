<?php

namespace App\Entity;

use App\Repository\PartyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PartyRepository::class)]
#[ORM\Table(name: 'parties')]
class Party
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @var array<array{name: string, birthYear: int}> Array von Kindern (1-3 Kinder pro Familie)
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, max: 3)]
    private array $children = [];

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var array<string> 1 oder 2 Elternteile
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\Count(min: 1, max: 2)]
    private array $parentNames = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Availability>
     */
    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'party', cascade: ['remove'], orphanRemoval: true)]
    private Collection $availabilities;

    /**
     * @var Collection<int, CookingAssignment>
     */
    #[ORM\OneToMany(targetEntity: CookingAssignment::class, mappedBy: 'party', cascade: ['remove'], orphanRemoval: true)]
    private Collection $cookingAssignments;

    /**
     * @var Collection<int, LastYearCooking>
     */
    #[ORM\OneToMany(targetEntity: LastYearCooking::class, mappedBy: 'party', cascade: ['remove'], orphanRemoval: true)]
    private Collection $lastYearCookings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->availabilities = new ArrayCollection();
        $this->cookingAssignments = new ArrayCollection();
        $this->lastYearCookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function setChildren(array $children): static
    {
        $this->children = $children;
        return $this;
    }

    public function addChild(string $name, int $birthYear): static
    {
        $this->children[] = ['name' => $name, 'birthYear' => $birthYear];
        return $this;
    }

    public function removeChild(int $index): static
    {
        if (isset($this->children[$index])) {
            unset($this->children[$index]);
            $this->children = array_values($this->children); // Re-index
        }
        return $this;
    }

    /**
     * Gibt die Namen aller Kinder als String zurück (z.B. "Max, Sophie")
     */
    public function getChildrenNames(): string
    {
        return implode(', ', array_column($this->children, 'name'));
    }

    /**
     * Gibt das älteste Kind zurück (für Passwort-Generierung)
     */
    public function getOldestChild(): ?array
    {
        if (empty($this->children)) {
            return null;
        }
        
        $oldest = $this->children[0];
        foreach ($this->children as $child) {
            if ($child['birthYear'] < $oldest['birthYear']) {
                $oldest = $child;
            }
        }
        return $oldest;
    }

    /**
     * Prüft ob ein Kind mit bestimmtem Geburtsjahr existiert (für älteste Kinder die ausscheiden)
     */
    public function hasChildBornIn(int $year): bool
    {
        foreach ($this->children as $child) {
            if ($child['birthYear'] === $year) {
                return true;
            }
        }
        return false;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getParentNames(): array
    {
        return $this->parentNames;
    }

    public function setParentNames(array $parentNames): static
    {
        $this->parentNames = $parentNames;
        return $this;
    }

    public function isSingleParent(): bool
    {
        return count($this->parentNames) === 1;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Availability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(Availability $availability): static
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setParty($this);
        }
        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getParty() === $this) {
                $availability->setParty(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CookingAssignment>
     */
    public function getCookingAssignments(): Collection
    {
        return $this->cookingAssignments;
    }

    public function addCookingAssignment(CookingAssignment $cookingAssignment): static
    {
        if (!$this->cookingAssignments->contains($cookingAssignment)) {
            $this->cookingAssignments->add($cookingAssignment);
            $cookingAssignment->setParty($this);
        }
        return $this;
    }

    public function removeCookingAssignment(CookingAssignment $cookingAssignment): static
    {
        if ($this->cookingAssignments->removeElement($cookingAssignment)) {
            if ($cookingAssignment->getParty() === $this) {
                $cookingAssignment->setParty(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, LastYearCooking>
     */
    public function getLastYearCookings(): Collection
    {
        return $this->lastYearCookings;
    }

    public function addLastYearCooking(LastYearCooking $lastYearCooking): static
    {
        if (!$this->lastYearCookings->contains($lastYearCooking)) {
            $this->lastYearCookings->add($lastYearCooking);
            $lastYearCooking->setParty($this);
        }
        return $this;
    }

    public function removeLastYearCooking(LastYearCooking $lastYearCooking): static
    {
        if ($this->lastYearCookings->removeElement($lastYearCooking)) {
            if ($lastYearCooking->getParty() === $this) {
                $lastYearCooking->setParty(null);
            }
        }
        return $this;
    }

    /**
     * Generiert das Login-Passwort: Erster Buchstabe des ÄLTESTEN Kindnamens + Geburtsjahr
     */
    public function getGeneratedPassword(): string
    {
        $oldest = $this->getOldestChild();
        if (!$oldest) {
            return '';
        }
        return strtoupper(substr($oldest['name'], 0, 1)) . $oldest['birthYear'];
    }

    public function __toString(): string
    {
        return $this->getChildrenNames() . ' (' . implode(', ', $this->parentNames) . ')';
    }
}
