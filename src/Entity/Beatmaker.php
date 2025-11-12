<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Beatmaker extends User
{
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $studioName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $biography = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeChannel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $soundcloudProfile = null;

    #[ORM\OneToMany(mappedBy: 'beatmaker', targetEntity: Beat::class)]
    private $beats;
}
