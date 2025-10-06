<?php

namespace App\Entity;

use App\Repository\CookingAssignmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CookingAssignmentRepository::class)]
#[ORM\Table(name: 'cooking_assignments')]
#[ORM\Index(name: 'assigned_date_idx', columns: ['assigned_date'])]
class CookingAssignment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'cookingAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Party $party = null;

    #[ORM\ManyToOne(inversedBy: 'cookingAssignments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?KitaYear $kitaYear = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $assignedDate = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isManuallyAssigned = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getAssignedDate(): ?\DateTimeImmutable
    {
        return $this->assignedDate;
    }

    public function setAssignedDate(\DateTimeImmutable $assignedDate): static
    {
        $this->assignedDate = $assignedDate;
        return $this;
    }

    public function isManuallyAssigned(): bool
    {
        return $this->isManuallyAssigned;
    }

    public function setIsManuallyAssigned(bool $isManuallyAssigned): static
    {
        $this->isManuallyAssigned = $isManuallyAssigned;
        return $this;
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
}
