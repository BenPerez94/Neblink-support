<?php

namespace App\Entity;

use App\Repository\ArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Index(columns: ['statut', 'date_publication'])]
#[Vich\Uploadable]
class Article
{
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PUBLIE = 'publie';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $extrait = null;

    #[ORM\Column(type: 'text')]
    private string $contenu = '';

    /** Contenu sans balises HTML, utilisé par la recherche. */
    #[ORM\Column(type: 'text')]
    private string $contenuTexte = '';

    /** Titres h2/h3 du contenu : [['niveau' => 2, 'id' => '...', 'texte' => '...'], ...] */
    #[ORM\Column(type: 'json')]
    private array $sommaire = [];

    #[ORM\Column(options: ['default' => 1])]
    private int $tempsLecture = 1;

    #[ORM\Column(length: 20, options: ['default' => self::STATUT_BROUILLON])]
    private string $statut = self::STATUT_BROUILLON;

    /** Si la date est dans le futur, l'article publié est considéré comme programmé. */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $datePublication = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metaTitre = null;

    #[ORM\Column(length: 320, nullable: true)]
    private ?string $metaDescription = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $couverture = null;

    #[Vich\UploadableField(mapping: 'blog_couvertures', fileNameProperty: 'couverture')]
    private ?File $couvertureFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $couvertureAlt = null;

    #[ORM\ManyToOne(inversedBy: 'articles')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\OrderBy(['nom' => 'ASC'])]
    private Collection $tags;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $this->updatedAt = $this->createdAt;
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getExtrait(): ?string
    {
        return $this->extrait;
    }

    public function setExtrait(?string $extrait): static
    {
        $this->extrait = $extrait;
        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getContenuTexte(): string
    {
        return $this->contenuTexte;
    }

    public function setContenuTexte(string $contenuTexte): static
    {
        $this->contenuTexte = $contenuTexte;
        return $this;
    }

    public function getSommaire(): array
    {
        return $this->sommaire;
    }

    public function setSommaire(array $sommaire): static
    {
        $this->sommaire = $sommaire;
        return $this;
    }

    public function getTempsLecture(): int
    {
        return $this->tempsLecture;
    }

    public function setTempsLecture(int $tempsLecture): static
    {
        $this->tempsLecture = $tempsLecture;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDatePublication(): ?\DateTimeImmutable
    {
        return $this->datePublication;
    }

    public function setDatePublication(?\DateTimeImmutable $datePublication): static
    {
        $this->datePublication = $datePublication;
        return $this;
    }

    public function isPublie(): bool
    {
        return $this->statut === self::STATUT_PUBLIE
            && $this->datePublication !== null
            && $this->datePublication <= new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }

    public function isProgramme(): bool
    {
        return $this->statut === self::STATUT_PUBLIE && !$this->isPublie();
    }

    /**
     * Statut affiché : brouillon, programme ou publie.
     */
    public function getEtat(): string
    {
        if ($this->statut === self::STATUT_BROUILLON) {
            return 'brouillon';
        }

        return $this->isPublie() ? 'publie' : 'programme';
    }

    public function getMetaTitre(): ?string
    {
        return $this->metaTitre;
    }

    public function setMetaTitre(?string $metaTitre): static
    {
        $this->metaTitre = $metaTitre;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getCouverture(): ?string
    {
        return $this->couverture;
    }

    public function setCouverture(?string $couverture): static
    {
        $this->couverture = $couverture;
        return $this;
    }

    public function setCouvertureFile(?File $couvertureFile = null): void
    {
        $this->couvertureFile = $couvertureFile;

        if ($couvertureFile) {
            $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        }
    }

    public function getCouvertureFile(): ?File
    {
        return $this->couvertureFile;
    }

    public function getCouvertureAlt(): ?string
    {
        return $this->couvertureAlt;
    }

    public function setCouvertureAlt(?string $couvertureAlt): static
    {
        $this->couvertureAlt = $couvertureAlt;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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
