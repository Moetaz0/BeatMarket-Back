<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class WalletTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'transactions')]
    private ?Wallet $wallet = null;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(length: 20)]
    private string $type; // "credit" or "debit"

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $reference = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }
}
