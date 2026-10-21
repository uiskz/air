<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirTicket;
use Uiskz\Air\AirTicketFare;

final class AirTicketFareTest extends TestCase
{
    private function ticket(): AirTicket
    {
        $ticket = new AirTicket();
        $ticket->issueDate = '2026-10-20 09:30:00';
        $ticket->baseFareAmount = 1000;
        $ticket->baseFareCurrency = 'USD';
        $ticket->equivalentFareAmount = 500000;
        $ticket->taxAmount = 20000;
        $ticket->totalAmount = 520000;
        $ticket->totalCurrency = 'KZT';
        $ticket->commissionPercent = 1;
        $ticket->commissionAmount = 5000;
        $ticket->airlineFee = 100;
        $ticket->consolidatorFee = 30;
        $ticket->serviceFee = 40;
        $ticket->agentMarkup = 10;
        $ticket->agentServiceFee = 15;
        $ticket->penaltyAmount = 7;

        return $ticket;
    }

    public function testSaleLineCopiesTicketAmounts(): void
    {
        $fare = new AirTicketFare();

        $fare->fillFromTicket($this->ticket(), AirTicket::OPERATION_SALE);

        $this->assertSame(AirTicket::OPERATION_SALE, $fare->operation);
        $this->assertSame('2026-10-20 04:30:00', $fare->date->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $fare->date->getTimezone()->getName());
        $this->assertSame('USD', $fare->baseCurrency);
        $this->assertSame(1000.0, $fare->baseFare);
        $this->assertSame('KZT', $fare->equivalentCurrency);
        $this->assertSame(500000.0, $fare->equivalentFare);
        $this->assertSame(AirTicketFare::COMMISSION_PERCENT, $fare->commissionType);
        $this->assertSame(5000.0, $fare->commissionAmount);
        $this->assertSame(20000.0, $fare->taxesAmount);
        $this->assertSame(520000.0, $fare->totalAmount);
        $this->assertSame(30.0, $fare->consolidatorFee);
        $this->assertSame(40.0, $fare->serviceFee);
        $this->assertSame(10.0, $fare->agentMarkup);
        $this->assertSame(15.0, $fare->agentServiceFee);
        $this->assertSame(500000.0 + 20000 + 100 + 30 + 40 + 15 + 10 + 7, $fare->grandTotalAmount);
    }

    public function testSaleLineWithoutIssueDateIsDatedNow(): void
    {
        $ticket = new AirTicket();
        $fare = new AirTicketFare();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $fare->fillFromTicket($ticket, AirTicket::OPERATION_SALE);

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $fare->date->getTimestamp());
        $this->assertSame('UTC', $fare->date->getTimezone()->getName());
    }

    public function testSaleLineWithMalformedIssueDateIsDatedNow(): void
    {
        $ticket = $this->ticket();
        $ticket->issueDate = '20.10.2026';
        $fare = new AirTicketFare();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $fare->fillFromTicket($ticket, AirTicket::OPERATION_SALE);

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $fare->date->getTimestamp());
    }

    public function testCommissionAmountOnlyMakesAmountCommission(): void
    {
        $ticket = $this->ticket();
        $ticket->commissionPercent = 0;
        $fare = new AirTicketFare();

        $fare->fillFromTicket($ticket, AirTicket::OPERATION_SALE);

        $this->assertSame(AirTicketFare::COMMISSION_AMOUNT, $fare->commissionType);
    }

    public function testGrandTotalFallsBackToBaseFareWithoutEquivalentFare(): void
    {
        $ticket = $this->ticket();
        $ticket->equivalentFareAmount = 0;
        $fare = new AirTicketFare();

        $fare->fillFromTicket($ticket, AirTicket::OPERATION_SALE);

        $this->assertSame(1000.0 + 20000 + 100 + 30 + 40 + 15 + 10 + 7, $fare->grandTotalAmount);
    }

    public function testVoidLineIsNegativeAndChargesVoidFees(): void
    {
        $ticket = $this->ticket();
        $ticket->voidMarkup = 50;
        $ticket->voidFee = 60;
        $ticket->voidAgentMarkup = 7;
        $ticket->voidAgentFee = 9;
        $fare = new AirTicketFare();
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $fare->fillFromTicket($ticket, AirTicket::OPERATION_VOID);

        $this->assertSame(AirTicket::OPERATION_VOID, $fare->operation);
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $fare->date->getTimestamp());
        $this->assertSame(-1000.0, $fare->baseFare);
        $this->assertSame(-500000.0, $fare->equivalentFare);
        $this->assertSame(-5000.0, $fare->commissionAmount);
        $this->assertSame(-20000.0, $fare->taxesAmount);
        $this->assertSame(-520000.0, $fare->totalAmount);
        $this->assertSame(-100.0, $fare->airlineFee);
        $this->assertSame(-7.0, $fare->penaltyAmount);
        // void markups and agency fees are charged, the sale ones returned
        $this->assertSame(50.0 - 30, $fare->consolidatorFee);
        $this->assertSame(7.0 - 10, $fare->agentMarkup);
        $this->assertSame(9.0 - 15, $fare->agentServiceFee);
        $this->assertSame(60.0, $fare->serviceFee);
        $this->assertSame(-500000.0 - 20000 - 100 + 20 + 60 - 6 - 3 - 7, $fare->grandTotalAmount);
    }
}
