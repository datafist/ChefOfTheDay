<?php

namespace App\Entity;

use App\Repository\LastYearCookingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LastYearCookingRepository::class)]
#[ORM\Table(name: 'last_year_cookings')]
#[ORM\UniqueConstraint(name: 'party_year_unique', columns: ['party_id', 'kita_year_id'])]
class LastYearCooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lastYearCookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Party $party = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $lastCookingDate = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $cookingCount = null;

    #[ORM\ManyToOne(inversedBy: 'lastYearCookings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?KitaYear $kitaYear = null;

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

    public function getLastCookingDate(): ?\DateTimeImmutable
    {
        return $this->lastCookingDate;
    }

    public function setLastCookingDate(\DateTimeImmutable $lastCookingDate): static
    {
        $this->lastCookingDate = $lastCookingDate;
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

    public function getCookingCount(): ?int
    {
        return $this->cookingCount;
    }

    public function setCookingCount(int $cookingCount): static
    {
        $this->cookingCount = $cookingCount;
        return $this;
    }
}
