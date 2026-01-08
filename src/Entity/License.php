<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use App\Repository\LicenseRepository;

#[ORM\Entity(repositoryClass: LicenseRepository::class)]
#[ORM\Table(name: 'license')]
class License
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(type: 'float')]
    private float $priceMultiplier;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isExclusive = false;

    #[ORM\OneToMany(mappedBy: 'license', targetEntity: Beat::class)]
    private Collection $beats;

    public function __construct()
    {
        $this->beats = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): self
    {
        $this->terms = $terms;
        return $this;
    }

    public function getPriceMultiplier(): float
    {
        return $this->priceMultiplier;
    }

    public function setPriceMultiplier(float $priceMultiplier): self
    {
        $this->priceMultiplier = $priceMultiplier;
        return $this;
    }

    public function isExclusive(): bool
    {
        return $this->isExclusive;
    }

    public function setIsExclusive(bool $isExclusive): self
    {
        $this->isExclusive = $isExclusive;
        return $this;
    }

    /**
     * @return Collection<int, Beat>
     */
    public function getBeats(): Collection
    {
        return $this->beats;
    }

    public function addBeat(Beat $beat): self
    {
        if (!$this->beats->contains($beat)) {
            $this->beats->add($beat);
            $beat->setLicense($this);
        }
        return $this;
    }

    public function removeBeat(Beat $beat): self
    {
        if ($this->beats->removeElement($beat)) {
            if ($beat->getLicense() === $this) {
                $beat->setLicense(null);
            }
        }
        return $this;
    }
}
