<?php

namespace App\Entity;

use App\Repository\EcheanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EcheanceRepository::class)]
class Echeance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'echeances')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Devis $devis = null;

    #[ORM\Column(length: 120)]
    private ?string $libelle = null;

    #[ORM\Column]
    private float $montant = 0;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $payeeLe = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevis(): ?Devis
    {
        return $this->devis;
    }

    public function setDevis(?Devis $devis): static
    {
        $this->devis = $devis;
        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function getMontant(): float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): static
    {
        $this->montant = $montant;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getPayeeLe(): ?\DateTimeImmutable
    {
        return $this->payeeLe;
    }

    public function setPayeeLe(?\DateTimeImmutable $payeeLe): static
    {
        $this->payeeLe = $payeeLe;
        return $this;
    }

    public function isPayee(): bool
    {
        return $this->payeeLe !== null;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }
}
