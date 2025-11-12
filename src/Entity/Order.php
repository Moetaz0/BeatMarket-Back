<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Artist::class, inversedBy: 'orders')]
    private ?Artist $artist = null;

    #[ORM\Column(type: 'float')]
    private float $totalAmount;

    #[ORM\Column(length: 50)]
    private string $status; // pending, paid, cancelled

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $paidAt = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    private $items;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = 'pending';
    }
}
