<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Beat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private string $fileUrl;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverImage = null;

    #[ORM\Column(type: 'float')]
    private float $price;

    #[ORM\Column(length: 50)]
    private string $genre;

    #[ORM\Column(type: 'integer')]
    private int $bpm; // beats per minute

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $key = null; // e.g., A#m, F#

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $uploadedAt;

    #[ORM\ManyToOne(targetEntity: Beatmaker::class, inversedBy: 'beats')]
    private ?Beatmaker $beatmaker = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTime();
    }
}
