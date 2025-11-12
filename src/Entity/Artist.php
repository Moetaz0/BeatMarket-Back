<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Artist extends User
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $genre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $biography = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $spotifyProfile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $soundcloudProfile = null;

    #[ORM\OneToMany(mappedBy: 'artist', targetEntity: Order::class)]
    private $orders;
}
