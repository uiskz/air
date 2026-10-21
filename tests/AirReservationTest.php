<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirReservation;
use Uiskz\Air\AirTicket;
use Uiskz\Air\Tests\Support\FixedRateConverter;

final class AirReservationTest extends TestCase
{
    public function testConvertCurrencyBringsTicketsToSystemCurrency(): void
    {
        $ticket = new AirTicket();
        $ticket->totalCurrency = 'USD';
        $ticket->baseFareCurrency = 'USD';
        $ticket->baseFareAmount = 100;
        $ticket->equivalentFareAmount = 100;
        $ticket->totalAmount = 100;
        $reservation = new AirReservation();
        $reservation->tickets = [$ticket];

        $reservation->convertCurrency(new FixedRateConverter('KZT', ['USD' => 500.0]), 'USD');

        $this->assertSame('KZT', $reservation->currency);
        $this->assertSame('USD', $ticket->providerCurrency);
        $this->assertSame(50000.0, $ticket->totalAmount);
        $this->assertSame('KZT', $ticket->totalCurrency);
    }
}
