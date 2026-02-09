<?php

namespace App\Entity;

use App\Repository\KitaYearRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: KitaYearRepository::class)]
#[ORM\Table(name: 'kita_years')]
class KitaYear
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = false;

    /**
     * @var Collection<int, Availability>
     */
    #[ORM\OneToMany(targetEntity: Availability::class, mappedBy: 'kitaYear', orphanRemoval: true)]
    private Collection $availabilities;

    /**
     * @var Collection<int, CookingAssignment>
     */
    #[ORM\OneToMany(targetEntity: CookingAssignment::class, mappedBy: 'kitaYear', orphanRemoval: true)]
    private Collection $cookingAssignments;

    /**
     * @var Collection<int, Holiday>
     */
    #[ORM\OneToMany(targetEntity: Holiday::class, mappedBy: 'kitaYear', orphanRemoval: true)]
    private Collection $holidays;

    /**
     * @var Collection<int, Vacation>
     */
    #[ORM\OneToMany(targetEntity: Vacation::class, mappedBy: 'kitaYear', orphanRemoval: true)]
    private Collection $vacations;

    /**
     * @var Collection<int, LastYearCooking>
     */
    #[ORM\OneToMany(targetEntity: LastYearCooking::class, mappedBy: 'kitaYear')]
    private Collection $lastYearCookings;

    public function __construct()
    {
        $this->availabilities = new ArrayCollection();
        $this->cookingAssignments = new ArrayCollection();
        $this->holidays = new ArrayCollection();
        $this->vacations = new ArrayCollection();
        $this->lastYearCookings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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
            $availability->setKitaYear($this);
        }
        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getKitaYear() === $this) {
                $availability->setKitaYear(null);
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
            $cookingAssignment->setKitaYear($this);
        }
        return $this;
    }

    public function removeCookingAssignment(CookingAssignment $cookingAssignment): static
    {
        if ($this->cookingAssignments->removeElement($cookingAssignment)) {
            if ($cookingAssignment->getKitaYear() === $this) {
                $cookingAssignment->setKitaYear(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Holiday>
     */
    public function getHolidays(): Collection
    {
        return $this->holidays;
    }

    public function addHoliday(Holiday $holiday): static
    {
        if (!$this->holidays->contains($holiday)) {
            $this->holidays->add($holiday);
            $holiday->setKitaYear($this);
        }
        return $this;
    }

    public function removeHoliday(Holiday $holiday): static
    {
        if ($this->holidays->removeElement($holiday)) {
            if ($holiday->getKitaYear() === $this) {
                $holiday->setKitaYear(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Vacation>
     */
    public function getVacations(): Collection
    {
        return $this->vacations;
    }

    public function addVacation(Vacation $vacation): static
    {
        if (!$this->vacations->contains($vacation)) {
            $this->vacations->add($vacation);
            $vacation->setKitaYear($this);
        }
        return $this;
    }

    public function removeVacation(Vacation $vacation): static
    {
        if ($this->vacations->removeElement($vacation)) {
            if ($vacation->getKitaYear() === $this) {
                $vacation->setKitaYear(null);
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
            $lastYearCooking->setKitaYear($this);
        }
        return $this;
    }

    public function removeLastYearCooking(LastYearCooking $lastYearCooking): static
    {
        if ($this->lastYearCookings->removeElement($lastYearCooking)) {
            if ($lastYearCooking->getKitaYear() === $this) {
                $lastYearCooking->setKitaYear(null);
            }
        }
        return $this;
    }

    public function getYearString(): string
    {
        return $this->startDate->format('Y') . '/' . $this->endDate->format('Y');
    }

    public function __toString(): string
    {
        return 'Kita-Jahr ' . $this->getYearString();
    }
}
