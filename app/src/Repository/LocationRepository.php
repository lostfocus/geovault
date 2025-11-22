<?php

namespace App\Repository;

use App\Entity\Database;
use App\Entity\Location;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    public function findLatestBefore(Database $database, \DateTime $dateTime): ?Location
    {
        $latestLocation = $this->createQueryBuilder('l')
            ->andWhere('l.locationDatabase = :database')
            ->setParameter('database', $database)
            ->andWhere('l.timestampUTC <= :dateTime')
            ->setParameter('dateTime', $dateTime)
            ->orderBy('l.timestampUTC', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($latestLocation instanceof Location) {
            return $latestLocation;
        }

        return null;
    }
}
