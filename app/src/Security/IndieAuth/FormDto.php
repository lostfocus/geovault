<?php

declare(strict_types=1);

namespace App\Security\IndieAuth;

use Symfony\Component\Validator\Constraints as Assert;

class FormDto
{
    #[Assert\NotBlank]
    #[Assert\Url]
    private ?string $url = null;

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): FormDto
    {
        $this->url = $url;

        return $this;
    }
}
