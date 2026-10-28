<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirFareFamilyCombination;
use Uiskz\Air\AirOption;
use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirSearchRequest;
use Uiskz\Air\AirTax;
use Uiskz\Travel\Fees\FeeKind;
use Uiskz\Travel\Traveler;

final class AirPassengerFareDetailsTest extends TestCase
{
    private function tax(string $code, float $amount): AirTax
    {
        $tax = new AirTax();
        $tax->code = $code;
        $tax->amount = $amount;
        $tax->currency = 'KZT';

        return $tax;
    }

    private function fare(): AirPassengerFareDetails
    {
        $fare = new AirPassengerFareDetails();
        $fare->fare = 1000;
        $fare->total = 1000;
        $fare->addTaxes([$this->tax('YQ', 150), $this->tax('KZ', 50)]);

        return $fare;
    }

    public function testAddTaxMergesSameCodeWithoutChangingTheAddedTax(): void
    {
        $fare = $this->fare();
        $tax = $this->tax('YQ', 25);

        $fare->addTax($tax);

        $this->assertCount(2, $fare->taxes);
        $this->assertSame(175.0, $fare->taxes[0]->amount);
        $this->assertSame(25.0, $tax->amount);
        $this->assertSame(225.0, $fare->totalTaxes);
        $this->assertSame(1225.0, $fare->total);
    }

    public function testAddTaxKeepsACopyOfANewTax(): void
    {
        $fare = new AirPassengerFareDetails();
        $tax = $this->tax('YQ', 10);

        $fare->addTax($tax);
        $tax->amount = 99;

        $this->assertSame(10.0, $fare->taxes[0]->amount);
    }

    public function testMergeAddsFareAndTaxesAndKeepsTotalTheirSum(): void
    {
        $fare = $this->fare();

        $fare->merge($this->fare());

        $this->assertSame(2000.0, $fare->fare);
        $this->assertSame(2400.0, $fare->total);
        $this->assertSame(300.0, $fare->taxes[0]->amount);
        $this->assertSame(400.0, $fare->totalTaxes);
    }

    public function testGrandTotalIncludesFeesAndMarkupsAndIgnoresDiscount(): void
    {
        $fare = $this->fare();
        $fare->setMarkup(30, 'consolidator');
        $fare->setMarkup(10, 'agent');
        $fare->setServiceFee(40, 'consolidator');
        $fare->setServiceFee(15, 'agent');
        $fare->providerServiceFee = 5;

        $fare->setDiscount(100, 'consolidator');

        $this->assertSame(100.0, $fare->discount);
        $this->assertSame(1200.0 + 30 + 10 + 40 + 15 + 5, $fare->grandTotal);
    }

    public function testLegacySetMarkupOverwritesBuckets(): void
    {
        $fare = $this->fare();

        $fare->setMarkup(30, 'consolidator');
        $fare->setMarkup(35, 'consolidator');
        $fare->setMarkup(10, 'agent');
        $fare->setMarkup(12, 'agent');

        $this->assertSame(35.0, $fare->getMarkup('consolidator'));
        $this->assertSame(12.0, $fare->getMarkup('agent'));
        $this->assertSame([1 => [FeeKind::MARKUP => 35.0]], $fare->getOwnerFees());
    }

    public function testSetMarkupOfOwnersSumsAgencies(): void
    {
        $fare = $this->fare();

        $fare->setMarkup(30, 'consolidator', 1);
        $fare->setMarkup(10, 'agent', 5);
        $fare->setMarkup(4, 'agent', 9);
        $fare->setMarkup(6, 'agent', 5);

        $this->assertSame(30.0, $fare->getMarkup('consolidator'));
        $this->assertSame(10.0, $fare->getMarkup('agent'));
        $this->assertSame(6.0, $fare->getOwnerFee(5, FeeKind::MARKUP));
        $this->assertSame(1240.0, $fare->grandTotal);
    }

    public function testSetServiceFeeOfOwnersSumsAgencies(): void
    {
        $fare = $this->fare();

        $fare->setServiceFee(40, 'consolidator');
        $fare->setServiceFee(15, 'agent', 5);
        $fare->setServiceFee(5, 'agent', 9);

        $this->assertSame(40.0, $fare->serviceFee);
        $this->assertSame(20.0, $fare->agentFee);
        $this->assertSame(1260.0, $fare->grandTotal);
    }

    public function testZeroServiceFeeKeepsTheCurrentFee(): void
    {
        $fare = $this->fare();
        $fare->setServiceFee(40, 'consolidator');

        $fare->setServiceFee(0, 'consolidator');

        $this->assertSame(40.0, $fare->serviceFee);
        $this->assertSame(1240.0, $fare->grandTotal);
    }

    public function testAgentDiscountsAddUpAndConsolidatorDiscountOverwrites(): void
    {
        $fare = $this->fare();

        $fare->setDiscount(10, 'agent');
        $fare->setDiscount(5, 'agent');
        $this->assertSame(15.0, $fare->discount);

        $fare->setDiscount(7, 'consolidator');
        $this->assertSame(7.0, $fare->discount);
    }

    public function testCleanPriceDropsFeesAndDiscount(): void
    {
        $fare = $this->fare();
        $fare->setServiceFee(40, 'consolidator');
        $fare->setServiceFee(15, 'agent');
        $fare->providerServiceFee = 5;
        $fare->setDiscount(10, 'consolidator');
        $fare->total = 1300;

        $fare->cleanPrice();

        $this->assertSame(0.0, $fare->serviceFee);
        $this->assertSame(0.0, $fare->agentFee);
        $this->assertSame(0.0, $fare->providerServiceFee);
        $this->assertSame(0.0, $fare->discount);
        $this->assertSame(1200.0, $fare->total);
        $this->assertSame(1200.0, $fare->grandTotal);
    }

    public function testReconciledOwnerFeesGiveLegacyAgencyBucketsToTheLegacyAgency(): void
    {
        $fare = $this->fare();
        $fare->setMarkup(30, 'consolidator');
        $fare->setServiceFee(40, 'consolidator');
        $fare->setMarkup(10, 'agent');
        $fare->setServiceFee(15, 'agent');

        $this->assertEquals(
            [
                1 => [FeeKind::MARKUP => 30.0, FeeKind::SERVICE => 40.0],
                9 => [FeeKind::SERVICE => 15.0, FeeKind::MARKUP => 10.0],
            ],
            $fare->getReconciledOwnerFees(9)
        );
    }

    public function testReconciledOwnerFeesKeepTrackedOwners(): void
    {
        $fare = $this->fare();
        $fare->setMarkup(10, 'agent', 5);
        $fare->setServiceFee(15, 'agent', 5);

        $this->assertEquals(
            [5 => [FeeKind::MARKUP => 10.0, FeeKind::SERVICE => 15.0]],
            $fare->getReconciledOwnerFees(9)
        );
    }

    public function testTypeIsTheFareRecordKindAndPassengerTypeIsThePassenger(): void
    {
        $info = new AirPassengerFareDetails();

        $this->assertSame(AirPassengerFareDetails::TYPE_TICKET, $info->type);
        $this->assertSame(Traveler::TYPE_ADULT, $info->passengerType);
    }

    public function testSetPriceTakesQuantityFromPassengerType(): void
    {
        $combination = new AirFareFamilyCombination();
        $combination->passengerInfos = [
            $this->info(Traveler::TYPE_ADULT, 100.0),
            $this->info(Traveler::TYPE_CHILD, 50.0),
            $this->info(Traveler::TYPE_INFANT, 10.0),
        ];
        $request = (new \ReflectionClass(AirSearchRequest::class))->newInstanceWithoutConstructor();
        $request->adults = 2;
        $request->children = 1;
        $request->infants = 1;

        $option = new AirOption();
        $option->setPrice($combination, $request);

        $this->assertSame(2, $option->passengerInfos[0]->passengerQty);
        $this->assertSame(1, $option->passengerInfos[1]->passengerQty);
        $this->assertSame(1, $option->passengerInfos[2]->passengerQty);
        $this->assertEqualsWithDelta(260.0, $option->fare, 0.001);
    }

    public function testAddPassengerInfosMergesSamePassengerTypeAndKindOnly(): void
    {
        $emd = $this->info(Traveler::TYPE_ADULT, 30.0);
        $emd->type = AirPassengerFareDetails::TYPE_EMD;

        $option = new AirOption();
        $option->addPassengerInfos([
            $this->info(Traveler::TYPE_ADULT, 100.0),
            $this->info(Traveler::TYPE_ADULT, 20.0),
            $this->info(Traveler::TYPE_CHILD, 50.0),
            $emd,
        ]);

        $this->assertCount(3, $option->passengerInfos);
        $this->assertSame(Traveler::TYPE_ADULT, $option->passengerInfos[0]->passengerType);
        $this->assertEqualsWithDelta(120.0, $option->passengerInfos[0]->fare, 0.001);
        $this->assertSame(Traveler::TYPE_CHILD, $option->passengerInfos[1]->passengerType);
        $this->assertSame(AirPassengerFareDetails::TYPE_EMD, $option->passengerInfos[2]->type);
    }

    private function info(string $passengerType, float $fare): AirPassengerFareDetails
    {
        $info = new AirPassengerFareDetails();
        $info->passengerType = $passengerType;
        $info->passengerQty = 1;
        $info->fare = $fare;
        $info->total = $fare;

        return $info;
    }
}
