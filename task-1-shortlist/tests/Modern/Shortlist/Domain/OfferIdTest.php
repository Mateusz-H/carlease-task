<?php

declare(strict_types=1);

namespace App\Tests\Modern\Shortlist\Domain;

use App\Modern\Shortlist\Domain\OfferId;
use PHPUnit\Framework\TestCase;

final class OfferIdTest extends TestCase
{
    public function test_keeps_trimmed_value(): void
    {
        $id = new OfferId(' off-1001 ');

        self::assertSame('off-1001', $id->value);
    }

    public function test_rejects_empty_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new OfferId('   ');
    }

    public function test_equality_compares_value(): void
    {
        self::assertTrue(new OfferId('off-1001')->equals(new OfferId('off-1001')));
        self::assertFalse(new OfferId('off-1001')->equals(new OfferId('off-1002')));
    }
}
