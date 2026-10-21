<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirSegment;
use Uiskz\Air\AirTax;
use Uiskz\Air\AirTicket;
use Uiskz\Air\AirTicketFare;
use Uiskz\Air\Tests\Support\FixedRateConverter;
use Uiskz\Travel\Fees\FeeKind;
use Uiskz\Travel\Traveler;

final class AirTicketTest extends TestCase
{
    private function ticket(): AirTicket
    {
        $ticket = new AirTicket();
        $ticket->number = '6552441294842';
        $ticket->baseFareAmount = 1000;
        $ticket->equivalentFareAmount = 1000;
        $ticket->taxAmount = 200;
        $ticket->totalAmount = 1200;
        $ticket->consolidatorFee = 30;
        $ticket->serviceFee = 40;
        $ticket->agentMarkup = 10;
        $ticket->agentServiceFee = 15;
        $ticket->grandTotalAmount = $ticket->calculateGrandTotal();

        return $ticket;
    }

    private function tax(string $code, float $amount, string $currency = ''): AirTax
    {
        $tax = new AirTax();
        $tax->code = $code;
        $tax->amount = $amount;
        if ($currency !== '') {
            $tax->currency = $currency;
        }

        return $tax;
    }

    private function segment(string $origin, string $destination, string $flightNumber = '701'): AirSegment
    {
        $segment = new AirSegment();
        $segment->origin = $origin;
        $segment->destination = $destination;
        $segment->carrier = 'KC';
        $segment->flightNumber = $flightNumber;

        return $segment;
    }

    public function testAddTaxRefreshesTotals(): void
    {
        $ticket = new AirTicket();
        $ticket->equivalentFareAmount = 1000;

        $ticket->addTax($this->tax('YQ', 100));

        $this->assertSame(100.0, $ticket->taxAmount);
        $this->assertSame(1100.0, $ticket->totalAmount);
        $this->assertSame(1100.0, $ticket->grandTotalAmount);
        $this->assertSame('KZT', $ticket->taxes[0]->currency);
    }

    public function testAddTaxUsesBaseFareWithoutEquivalentFare(): void
    {
        $ticket = new AirTicket();
        $ticket->baseFareAmount = 500;

        $ticket->addTax($this->tax('YQ', 50, 'USD'));

        $this->assertSame(550.0, $ticket->totalAmount);
        $this->assertSame('USD', $ticket->taxes[0]->currency);
    }

    public function testAddTaxOfSameCodeMergesAndRefreshesTotals(): void
    {
        $ticket = new AirTicket();
        $ticket->equivalentFareAmount = 1000;
        $ticket->consolidatorFee = 7;

        $ticket->addTax($this->tax('YQ', 100));
        $ticket->addTax($this->tax('YQ', 50));

        $this->assertCount(1, $ticket->taxes);
        $this->assertSame(150.0, $ticket->taxes[0]->amount);
        $this->assertSame(150.0, $ticket->taxAmount);
        $this->assertSame(1150.0, $ticket->totalAmount);
        $this->assertSame(1157.0, $ticket->grandTotalAmount);
        $this->assertSame(150.0, $ticket->getTotalTaxAmount());
    }

    public function testGrandTotalIncludesAllFeesAndIgnoresDiscount(): void
    {
        $ticket = $this->ticket();
        $ticket->airlineFee = 5;
        $ticket->discount = 100;

        $this->assertSame(1200.0 + 5 + 30 + 40 + 10 + 15, $ticket->calculateGrandTotal());
    }

    public function testUpdateGrandTotalRefreshesOnlySaleFares(): void
    {
        $ticket = $this->ticket();
        $sale = new AirTicketFare();
        $sale->operation = AirTicket::OPERATION_SALE;
        $refund = new AirTicketFare();
        $refund->operation = AirTicket::OPERATION_REFUND;
        $ticket->fares = [$sale, $refund];
        $ticket->serviceFee = 60;

        $ticket->updateGrandTotal();

        $this->assertSame(1315.0, $ticket->grandTotalAmount);
        $this->assertSame(60.0, $sale->serviceFee);
        $this->assertSame(30.0, $sale->consolidatorFee);
        $this->assertSame(1315.0, $sale->grandTotalAmount);
        $this->assertSame(0.0, $refund->serviceFee);
        $this->assertSame(0.0, $refund->grandTotalAmount);
    }

    public function testLegacySetServiceFeeOverwritesBuckets(): void
    {
        $ticket = $this->ticket();

        $ticket->setServiceFee(70);
        $ticket->setServiceFee(25, 'agent');

        $this->assertSame(70.0, $ticket->serviceFee);
        $this->assertSame(25.0, $ticket->agentServiceFee);
        $this->assertSame([1 => [FeeKind::SERVICE => 70.0]], $ticket->getOwnerFees());
        $this->assertSame(1200.0 + 30 + 70 + 10 + 25, $ticket->grandTotalAmount);
    }

    public function testSetServiceFeeOfOwnersSumsAgencies(): void
    {
        $ticket = $this->ticket();

        $ticket->setServiceFee(5, 'agent', 7);
        $ticket->setServiceFee(8, 'agent', 9);

        $this->assertSame(13.0, $ticket->agentServiceFee);
        $this->assertSame(40.0, $ticket->serviceFee);
        $this->assertSame(5.0, $ticket->getOwnerFee(7, FeeKind::SERVICE));
        $this->assertSame(8.0, $ticket->getOwnerFee(9, FeeKind::SERVICE));
    }

    public function testSetServiceFeeCopiesFeesToSaleFares(): void
    {
        $ticket = $this->ticket();
        $sale = new AirTicketFare();
        $sale->operation = AirTicket::OPERATION_SALE;
        $void = new AirTicketFare();
        $void->operation = AirTicket::OPERATION_VOID;
        $ticket->fares = [$sale, $void];

        $ticket->setServiceFee(50);

        $this->assertSame(50.0, $sale->serviceFee);
        $this->assertSame(30.0, $sale->consolidatorFee);
        $this->assertSame(15.0, $sale->agentServiceFee);
        $this->assertSame(10.0, $sale->agentMarkup);
        $this->assertSame($ticket->grandTotalAmount, $sale->grandTotalAmount);
        $this->assertSame(0.0, $void->serviceFee);
    }

    public function testSetServiceFeesForSaleCopiesPassengerFareFees(): void
    {
        $ticket = $this->ticket();
        $ticket->setOwnerFees([1 => [FeeKind::VOID_SERVICE => 3.0]]);
        $pi = new AirPassengerFareDetails();
        $pi->setMarkup(55, 'consolidator');
        $pi->setMarkup(25, 'agent', 7);
        $pi->setServiceFee(66, 'consolidator');
        $pi->setServiceFee(33, 'agent', 7);
        $pi->setDiscount(12, 'consolidator');

        $ticket->setServiceFees($pi);

        $this->assertSame(55.0, $ticket->consolidatorFee);
        $this->assertSame(25.0, $ticket->agentMarkup);
        $this->assertSame(66.0, $ticket->serviceFee);
        $this->assertSame(33.0, $ticket->agentServiceFee);
        $this->assertSame(12.0, $ticket->discount);
        $this->assertEquals(
            [
                1 => [FeeKind::VOID_SERVICE => 3.0, FeeKind::SERVICE => 66.0, FeeKind::MARKUP => 55.0],
                7 => [FeeKind::MARKUP => 25.0, FeeKind::SERVICE => 33.0],
            ],
            $ticket->getOwnerFees()
        );
        $this->assertSame(1200.0 + 55 + 66 + 25 + 33, $ticket->grandTotalAmount);
    }

    public function testSetServiceFeesForSaleKeepsTicketFeesThePassengerFareLacks(): void
    {
        $ticket = $this->ticket();

        $ticket->setServiceFees(new AirPassengerFareDetails());

        $this->assertSame(30.0, $ticket->consolidatorFee);
        $this->assertSame(40.0, $ticket->serviceFee);
        $this->assertSame(10.0, $ticket->agentMarkup);
        $this->assertSame(15.0, $ticket->agentServiceFee);
    }

    public function testSetServiceFeesForVoidDoesNotTouchSaleFields(): void
    {
        $ticket = $this->ticket();
        $ticket->setOwnerFees([1 => [FeeKind::SERVICE => 40.0, FeeKind::VOID_MARKUP => 1.0]]);
        $pi = new AirPassengerFareDetails();
        $pi->setMarkup(55, 'consolidator');
        $pi->setMarkup(25, 'agent', 7);
        $pi->setServiceFee(66, 'consolidator');
        $pi->setServiceFee(33, 'agent', 7);

        $ticket->setServiceFees($pi, AirTicket::OPERATION_VOID);

        $this->assertSame(30.0, $ticket->consolidatorFee);
        $this->assertSame(40.0, $ticket->serviceFee);
        $this->assertSame(10.0, $ticket->agentMarkup);
        $this->assertSame(15.0, $ticket->agentServiceFee);
        $this->assertSame(55.0, $ticket->voidMarkup);
        $this->assertSame(25.0, $ticket->voidAgentMarkup);
        $this->assertSame(66.0, $ticket->voidFee);
        $this->assertSame(33.0, $ticket->voidAgentFee);
        $this->assertEquals(
            [
                1 => [FeeKind::SERVICE => 40.0, FeeKind::VOID_SERVICE => 66.0, FeeKind::VOID_MARKUP => 55.0],
                7 => [FeeKind::VOID_MARKUP => 25.0, FeeKind::VOID_SERVICE => 33.0],
            ],
            $ticket->getOwnerFees()
        );
    }

    public function testVoidHelpersDoNotTouchSaleFields(): void
    {
        $ticket = $this->ticket();

        $ticket->setVoidMarkup(12, 'consolidator');
        $ticket->setVoidMarkup(8, 'agent');
        $ticket->setVoidFee(20, 'consolidator');
        $ticket->setVoidFee(5, 'agent', 7);

        $this->assertSame(12.0, $ticket->voidMarkup);
        $this->assertSame(8.0, $ticket->voidAgentMarkup);
        $this->assertSame(20.0, $ticket->voidFee);
        $this->assertSame(5.0, $ticket->voidAgentFee);
        $this->assertSame(30.0, $ticket->consolidatorFee);
        $this->assertSame(40.0, $ticket->serviceFee);
        $this->assertSame(1295.0, $ticket->grandTotalAmount);
    }

    public function testPopulateFaresOfIssuedTicketBuildsSaleLineOnly(): void
    {
        $ticket = $this->ticket();
        $ticket->status = AirTicket::STATUS_ISSUED;

        $ticket->populateFares();

        $this->assertCount(1, $ticket->fares);
        $this->assertSame(AirTicket::OPERATION_SALE, $ticket->fares[0]->operation);
        $this->assertSame(1295.0, $ticket->fares[0]->grandTotalAmount);
    }

    public function testPopulateFaresOfVoidTicketReturnsIssueServiceFee(): void
    {
        $ticket = $this->ticket();
        $ticket->status = AirTicket::STATUS_VOID;
        $ticket->voidFee = 60;
        $ticket->voidMarkup = 50;

        $ticket->populateFares(true);

        $this->assertCount(2, $ticket->fares);
        $void = $ticket->fares[1];
        $this->assertSame(AirTicket::OPERATION_VOID, $void->operation);
        // returns the issue fee (40), charges the void fee (60)
        $this->assertSame(20.0, $void->serviceFee);
        // -1000 - 200 + (50 - 30) + 20 + (0 - 15) + (0 - 10)
        $this->assertSame(-1185.0, $void->grandTotalAmount);
    }

    public function testPopulateFaresOfVoidTicketOnlyChargesVoidFee(): void
    {
        $ticket = $this->ticket();
        $ticket->status = AirTicket::STATUS_VOID;
        $ticket->voidFee = 60;
        $ticket->voidMarkup = 50;

        $ticket->populateFares(false);

        $void = $ticket->fares[1];
        $this->assertSame(60.0, $void->serviceFee);
        $this->assertSame(-1145.0, $void->grandTotalAmount);
    }

    public function testPopulateFaresUsesFareFactory(): void
    {
        $ticket = new class extends AirTicket {
            protected function newFare(): AirTicketFare
            {
                return new class extends AirTicketFare {
                };
            }
        };
        $ticket->status = AirTicket::STATUS_VOID;

        $ticket->populateFares();

        $this->assertCount(2, $ticket->fares);
        foreach ($ticket->fares as $fare) {
            $this->assertNotSame(AirTicketFare::class, $fare::class);
        }
    }

    public function testFormatNumberSeparatesAirlineCode(): void
    {
        $ticket = $this->ticket();

        $this->assertSame('655-2441294842', $ticket->formatNumber());
        $this->assertSame('655-2441294842', $ticket->printedNumber());
    }

    public function testPrintedNumberOfConjunctionTicketAppendsLastTwoDigits(): void
    {
        $ticket = $this->ticket();
        $ticket->conjunctionTickets = ['6552441294843'];

        $this->assertSame('6552441294842-43', $ticket->printedNumber());

        $ticket->conjunctionTickets = ['6552441294843', 6552441294844];
        $this->assertSame('6552441294842-44', $ticket->printedNumber());

        $ticket->conjunctionTickets = ['5'];
        $this->assertSame('6552441294842-5', $ticket->printedNumber());
    }

    public function testSetPassengerTakesPassengerType(): void
    {
        $traveler = new Traveler();
        $traveler->type = Traveler::TYPE_CHILD;
        $ticket = $this->ticket();

        $ticket->setPassenger($traveler);

        $this->assertSame($traveler, $ticket->passenger);
        $this->assertSame(Traveler::TYPE_CHILD, $ticket->passengerType);
    }

    public function testAddSegmentAppendsSegment(): void
    {
        $ticket = $this->ticket();
        $segment = $this->segment('ALA', 'NQZ');

        $ticket->addSegment($segment);

        $this->assertSame([$segment], $ticket->segments);
    }

    public function testFillSegmentsFromEmdCopiesServicesOfMatchingSegments(): void
    {
        $ticket = $this->ticket();
        $ticket->segments = [$this->segment('ALA', 'NQZ'), $this->segment('NQZ', 'ALA', '702')];
        $ticket->segments[1]->seat = '1A';
        $emd = new AirTicket();
        $emdSegment = $this->segment('NQZ', 'ALA', '702');
        $emdSegment->seat = '12C';
        $emdSegment->meal = 'VGML';
        $emdSegment->bundle = 'COMFORT';
        $emd->segments = [$emdSegment, $this->segment('ALA', 'TSE')];

        $ticket->fillSegmentsFromEmd($emd);

        $this->assertSame('', $ticket->segments[0]->seat);
        $this->assertSame('', $ticket->segments[0]->meal);
        $this->assertSame('1A', $ticket->segments[1]->seat);
        $this->assertSame('VGML', $ticket->segments[1]->meal);
        $this->assertSame('COMFORT', $ticket->segments[1]->bundle);
    }

    public function testApplySystemCurrencyInSystemCurrencyCopiesProviderAmounts(): void
    {
        $ticket = $this->ticket();
        $ticket->taxes = [$this->tax('YQ', 200, 'KZT')];
        $converter = new FixedRateConverter('KZT');

        $ticket->applySystemCurrency($converter, 'KZT', new \DateTimeImmutable('2026-10-01'));

        $this->assertSame([], $converter->calls);
        $this->assertSame('KZT', $ticket->providerCurrency);
        $this->assertSame(1200.0, $ticket->providerTotalAmount);
        $this->assertSame(200.0, $ticket->taxes[0]->providerAmount);
        $this->assertSame(1200.0, $ticket->totalAmount);
        $this->assertSame(1295.0, $ticket->grandTotalAmount);
    }

    public function testApplySystemCurrencyConvertsProviderAmounts(): void
    {
        $ticket = $this->ticket();
        $ticket->totalCurrency = 'USD';
        $ticket->baseFareCurrency = 'USD';
        $ticket->taxes = [$this->tax('YQ', 200, 'USD')];
        $converter = new FixedRateConverter('KZT', ['USD' => 500.0]);

        $ticket->applySystemCurrency($converter, 'KZT', new \DateTimeImmutable('2026-10-01'));

        $this->assertSame('USD', $ticket->providerCurrency);
        $this->assertSame(1000.0, $ticket->providerBaseFareAmount);
        $this->assertSame(500000.0, $ticket->baseFareAmount);
        $this->assertSame('KZT', $ticket->baseFareCurrency);
        $this->assertSame(500000.0, $ticket->equivalentFareAmount);
        $this->assertSame(100000.0, $ticket->taxAmount);
        $this->assertSame(600000.0, $ticket->totalAmount);
        $this->assertSame('KZT', $ticket->totalCurrency);
        $this->assertSame(100000.0, $ticket->taxes[0]->amount);
        $this->assertSame('KZT', $ticket->taxes[0]->currency);
        $this->assertSame(200.0, $ticket->taxes[0]->providerAmount);
        $this->assertSame(600000.0 + 95, $ticket->grandTotalAmount);
    }

    public function testApplySystemCurrencyKeepsBaseFareInFilingCurrency(): void
    {
        $ticket = $this->ticket();
        $ticket->totalCurrency = 'USD';
        $ticket->baseFareCurrency = 'EUR';
        $converter = new FixedRateConverter('KZT', ['USD' => 500.0]);

        $ticket->applySystemCurrency($converter, 'KZT', new \DateTimeImmutable('2026-10-01'));

        $this->assertSame(1000.0, $ticket->baseFareAmount);
        $this->assertSame('EUR', $ticket->baseFareCurrency);
        $this->assertSame(500000.0, $ticket->equivalentFareAmount);
    }

    public function testApplySystemCurrencyTwiceConvertsFromProviderAmounts(): void
    {
        $ticket = $this->ticket();
        $ticket->totalCurrency = 'USD';
        $ticket->baseFareCurrency = 'USD';
        $converter = new FixedRateConverter('KZT', ['USD' => 500.0]);
        $date = new \DateTimeImmutable('2026-10-01');

        $ticket->applySystemCurrency($converter, 'KZT', $date);
        $ticket->applySystemCurrency($converter, 'KZT', $date);

        $this->assertSame('USD', $ticket->providerCurrency);
        $this->assertSame(600000.0, $ticket->totalAmount);
    }
}
