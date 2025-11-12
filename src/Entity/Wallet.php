<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'wallet', targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    private float $balance = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: WalletTransaction::class, cascade: ['persist', 'remove'])]
    private $transactions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }
}
