<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Gesdinet\JWTRefreshTokenBundle\Doctrine\RefreshTokenRepositoryInterface;

class RefreshTokenRepository extends ServiceEntityRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }
    public function findInvalid($datetime = null)
    {
        return $this->createQueryBuilder('r')
            ->where('r.valid < :now')
            ->setParameter('now', $datetime ?? new \DateTime())
            ->getQuery()
            ->getResult();
    }
}
