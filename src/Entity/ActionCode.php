<?php
// src/Entity/ActionCode.php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ActionCodeRepository;

#[ORM\Entity(repositoryClass: ActionCodeRepository::class)]
#[ORM\Table(name: 'action_code')]
class ActionCode
{
    public const PURPOSE_PASSWORD_RESET = 'PASSWORD_RESET';
    public const PURPOSE_EMAIL_VERIFY = 'EMAIL_VERIFY';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 64)]
    private string $code;

    #[ORM\Column(type: 'string', length: 50)]
    private string $purpose;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $expiresAt;

    #[ORM\Column(type: 'boolean')]
    private bool $used = false;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters/setters

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUser(): User
    {
        return $this->user;
    }
    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }
    public function setPurpose(string $purpose): self
    {
        $this->purpose = $purpose;
        return $this;
    }

    public function getExpiresAt(): \DateTimeInterface
    {
        return $this->expiresAt;
    }
    public function setExpiresAt(\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }
    public function setUsed(bool $used): self
    {
        $this->used = $used;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }
}
