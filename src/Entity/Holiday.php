<?php

namespace App\Entity;

use App\Repository\HolidayRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HolidayRepository::class)]
#[ORM\Table(name: 'holidays')]
class Holiday
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'holidays')]
    #[ORM\JoinColumn(nullable: false)]
    private ?KitaYear $kitaYear = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function __toString(): string
    {
        return $this->name . ' (' . $this->date->format('d.m.Y') . ')';
    }
}
