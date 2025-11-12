<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Admin extends User
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $permissions = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $department = null;
}
