<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Form\NewDatabase;
use App\Entity\Database;
use App\Entity\User;
use App\Repository\DatabaseRepository;
use App\Service\DatabaseService\DatabaseExistsException;
use Doctrine\ORM\EntityManagerInterface;
use Random\RandomException;
use Symfony\Component\String\Slugger\SluggerInterface;

readonly class DatabaseService
{
    public function __construct(
        private DatabaseRepository $databaseRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @return Database[]
     */
    public function getUserDatabases(User $user): array
    {
        return $this->databaseRepository->findBy(['user' => $user]);
    }

    /**
     * @throws DatabaseExistsException
     * @throws RandomException
     */
    public function createDatabase(NewDatabase $newDatabase, User $user, bool $flush = true): Database
    {
        $name = $newDatabase->getDatabaseName();
        // We can do that because it's been through the validator before.
        assert(null !== $name);
        $slug = $this->slugger->slug(strtolower($name))->toString();

        $database = $this->databaseRepository->findOneBy(['slug' => $slug]);
        if (null !== $database) {
            throw new DatabaseExistsException('A database with slug "'.$slug.'" already exists.');
        }
        $database = new Database()
            ->setSlug($slug)
            ->setUser($user)
            ->setReadToken($this->getRandomString(40))
            ->setWriteToken($this->getRandomString(40))
            ->setTimezone('UTC');
        $this->entityManager->persist($database);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $database;
    }

    /**
     * @throws RandomException
     *
     * @noinspection PhpSameParameterValueInspection
     */
    private function getRandomString(int $n): string
    {
        $length = (int) round($n / 2, 0, \RoundingMode::AwayFromZero);
        assert($length > 0);

        return bin2hex(random_bytes($length));
    }

    public function getDatabaseById(int $id, User $user): ?Database
    {
        return $this->databaseRepository->findOneBy(['id' => $id, 'user' => $user]);
    }
}
