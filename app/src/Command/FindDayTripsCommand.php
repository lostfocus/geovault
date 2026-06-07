<?php

namespace App\Command;

use App\Entity\Database;
use App\Repository\DatabaseRepository;
use App\Service\TripService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:find-day-trips',
    description: 'Find trips for a day',
)]
class FindDayTripsCommand extends Command
{
    public function __construct(
        private readonly TripService $tripService,
        private readonly DatabaseRepository $databaseRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('database', InputArgument::REQUIRED, 'The database to query')
            ->addArgument('day', InputArgument::REQUIRED, 'The day of the trip')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $databaseId = $input->getArgument('database');
        $database = $this->databaseRepository->find($databaseId);
        if (!$database instanceof Database) {
            $io->error('Database not found');

            return Command::INVALID;
        }

        $dayString = $input->getArgument('day');
        if (!is_string($dayString)) {
            $io->error('The day argument must be a string');

            return Command::INVALID;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dayString);
        if (false === $date) {
            $io->error('The day argument must be a valid date');

            return Command::INVALID;
        }

        try {
            $result = $this->tripService->findDayTrips($database, $date);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::INVALID;
        }
        if (null === $result) {
            $io->error('Undefined error');

            return Command::INVALID;
        }
        $io->success(count($result).' trip(s) returned');

        return Command::SUCCESS;
    }
}
