<?php

declare(strict_types=1);

namespace App\Dto\Form;

use Symfony\Component\Validator\Constraints as Assert;

class NewDatabase
{
    #[Assert\NotBlank]
    private ?string $databaseName = null;

    public function getDatabaseName(): ?string
    {
        return $this->databaseName;
    }

    public function setDatabaseName(?string $databaseName): self
    {
        $this->databaseName = $databaseName;

        return $this;
    }
}
