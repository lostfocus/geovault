<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\QuartzService;
use PHPUnit\Framework\TestCase;

class QuartzServiceTest extends TestCase
{
    private ?QuartzService $quartzService = null;

    public function setUp(): void
    {
        $this->quartzService = new QuartzService(
            dirname(__DIR__).'/assets/default'
        );
    }

    /**
     * @throws \DateInvalidOperationException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function testGetLast(): void
    {
        $date = new \DateTime('2024-11-04 10:00:00', new \DateTimeZone('UTC'));
        assert(null !== $this->quartzService);
        $result = $this->quartzService->getLast($date);
        // $this->assertNull($result);
        $this->assertInstanceOf(\stdClass::class, $result->data);
        $this->assertObjectHasProperty('type', $result->data);
    }
}
