<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirFareFamilyCombination;
use Uiskz\Air\AirOption;
use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirTax;
use Uiskz\Travel\Fees\FeeSlot;

final class AirOptionFeesTest extends TestCase
{
    private function option(): AirOption
    {
        $option = new class extends AirOption {
            public function placeServiceFeeOf(AirPassengerFareDetails $info, string $slot, float $fee): void
            {
                $this->placeServiceFee($info, $slot, $fee);
            }

            public function placeMarkupOf(AirPassengerFareDetails $info, string $slot, float $markup): void
            {
                $this->placeMarkup($info, $slot, $markup);
            }

            public function addServiceFeeOf(string $slot, float $amount): void
            {
                $this->addOptionServiceFee($slot, $amount);
            }

            public function addHiddenFeeOf(string $slot, float $amount, bool $viewerIsRoot): void
            {
                $this->addOptionHiddenFee($slot, $amount, $viewerIsRoot);
            }
        };
        $option->currency = 'KZT';

        return $option;
    }

    private function passengerInfo(): AirPassengerFareDetails
    {
        $tax = new AirTax();
        $tax->code = 'YQ';
        $tax->amount = 200;
        $tax->currency = 'KZT';
        $info = new AirPassengerFareDetails();
        $info->passengerQty = 1;
        $info->fare = 1000;
        $info->total = 1000;
        $info->addTax($tax);

        return $info;
    }

    public function testServiceFeeLandsInTheFieldOfItsSlotAndOnlyInTheGrandTotal(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();

        $option->placeServiceFeeOf($info, FeeSlot::CONSOLIDATOR, 40);
        $option->placeServiceFeeOf($info, FeeSlot::AGENCY, 15);
        $option->placeServiceFeeOf($info, FeeSlot::PROVIDER, 5);
        $option->placeServiceFeeOf($info, FeeSlot::PROVIDER, 3);
        $option->placeServiceFeeOf($info, FeeSlot::DOWNSTREAM, 99);

        $this->assertSame(40.0, $info->serviceFee);
        $this->assertSame(15.0, $info->agentFee);
        $this->assertSame(0.0, $info->providerServiceFee);
        $this->assertSame(1200.0 + 8, $info->total);
        $this->assertSame(1200.0 + 8 + 40 + 15 + 99, $info->grandTotal);
    }

    public function testUpperAgencyServiceFeeIsHiddenInAwTax(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();

        $option->placeServiceFeeOf($info, FeeSlot::PROVIDER, 5);
        $option->placeMarkupOf($info, FeeSlot::PROVIDER, 30);
        $option->placeServiceFeeOf($info, FeeSlot::PROVIDER, 3);

        $taxes = array_combine(
            array_map(static fn(AirTax $tax) => $tax->code, $info->taxes),
            array_map(static fn(AirTax $tax) => $tax->amount, $info->taxes)
        );
        $this->assertSame(['YQ' => 200.0, 'AW' => 38.0], $taxes);
        $this->assertSame(238.0, $info->totalTaxes);
        $this->assertSame(0.0, $info->providerServiceFee);
        $this->assertSame(1238.0, $info->grandTotal);
    }

    public function testMarkupIsShownAsTaxOfItsSlot(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();

        $option->placeMarkupOf($info, FeeSlot::PROVIDER, 30);
        $option->placeMarkupOf($info, FeeSlot::CONSOLIDATOR, 20);

        $this->assertSame(['YQ', 'AW', 'AD'], array_map(static fn(AirTax $tax) => $tax->code, $info->taxes));
        $this->assertSame('KZT', $info->taxes[1]->currency);
        $this->assertSame(250.0, $info->totalTaxes);
        $this->assertSame(1250.0, $info->total);
        $this->assertSame(1250.0, $info->grandTotal);
    }

    public function testTotalIsFareWithTaxesAndGrandTotalAddsServiceFeesOnce(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();
        $option->placeServiceFeeOf($info, FeeSlot::CONSOLIDATOR, 1000);

        $option->placeMarkupOf($info, FeeSlot::PROVIDER, 30);

        $this->assertSame(1230.0, $info->total);
        $this->assertSame(1230.0 + 1000, $info->grandTotal);
    }

    public function testDownstreamMarkupOnlyRaisesTheGrandTotal(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();

        $option->placeMarkupOf($info, FeeSlot::DOWNSTREAM, 30);

        $this->assertCount(1, $info->taxes);
        $this->assertSame(200.0, $info->totalTaxes);
        $this->assertSame(1200.0, $info->total);
        $this->assertSame(1230.0, $info->grandTotal);
    }

    public function testSetPriceTakesTheGrandTotalOfTheCombination(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();
        $info->passengerQty = 2;
        $option->placeServiceFeeOf($info, FeeSlot::CONSOLIDATOR, 40);
        $option->placeMarkupOf($info, FeeSlot::DOWNSTREAM, 10);
        $combination = new AirFareFamilyCombination();
        $combination->passengerInfos = [$info];

        $option->setPrice($combination);

        $this->assertSame(2000.0, $option->fare);
        $this->assertSame(400.0, $option->taxes);
        $this->assertSame(2 * (1200.0 + 40 + 10), $option->price);
    }

    public function testOptionServiceFeesAddUpBySlot(): void
    {
        $option = $this->option();

        $option->addServiceFeeOf(FeeSlot::CONSOLIDATOR, 40);
        $option->addServiceFeeOf(FeeSlot::CONSOLIDATOR, 10);
        $option->addServiceFeeOf(FeeSlot::AGENCY, 15);
        $option->addServiceFeeOf(FeeSlot::PROVIDER, 5);
        $option->addServiceFeeOf(FeeSlot::DOWNSTREAM, 99);

        $this->assertSame(50.0, $option->consolidatorServiceFee);
        $this->assertSame(15.0, $option->agencyServiceFee);
        // Upper agencies' service fees are shown as tax AW, like their markups
        $this->assertSame(0.0, $option->providerServiceFee);
        $this->assertSame(5.0, $option->consolidatorMarkup);
    }

    public function testOptionHiddenFeesOfRootViewer(): void
    {
        $option = $this->option();

        $option->addHiddenFeeOf(FeeSlot::CONSOLIDATOR, 30, true);
        $option->addHiddenFeeOf(FeeSlot::DOWNSTREAM, 99, true);

        $this->assertSame(30.0, $option->consolidatorMarkup);
        $this->assertSame(30.0, $option->consolidatorHiddenFee);
        $this->assertSame(0.0, $option->agencyHiddenFee);
    }

    public function testOptionHiddenFeesOfAgencyViewer(): void
    {
        $option = $this->option();

        $option->addHiddenFeeOf(FeeSlot::CONSOLIDATOR, 20, false);
        $option->addHiddenFeeOf(FeeSlot::PROVIDER, 10, false);
        $option->addHiddenFeeOf(FeeSlot::AGENCY, 4, false);

        $this->assertSame(34.0, $option->consolidatorMarkup);
        $this->assertSame(0.0, $option->consolidatorHiddenFee);
        $this->assertSame(4.0, $option->agencyHiddenFee);
    }

    public function testCleanPriceDropsOptionAndPassengerFees(): void
    {
        $option = $this->option();
        $info = $this->passengerInfo();
        $info->passengerQty = 2;
        $info->setServiceFee(40, 'consolidator');
        $info->agentFee = 15;
        $info->providerServiceFee = 5;
        $option->passengerInfos = [$info];
        $option->addServiceFeeOf(FeeSlot::AGENCY, 15);
        $option->addServiceFeeOf(FeeSlot::PROVIDER, 5);
        $option->addHiddenFeeOf(FeeSlot::CONSOLIDATOR, 20, true);
        $option->addHiddenFeeOf(FeeSlot::AGENCY, 4, false);
        $option->placeMarkupOf($info, FeeSlot::DOWNSTREAM, 7);

        $option->cleanPrice();

        $this->assertSame(0.0, $option->agencyServiceFee);
        $this->assertSame(0.0, $option->providerServiceFee);
        $this->assertSame(0.0, $option->consolidatorHiddenFee);
        $this->assertSame(0.0, $option->agencyHiddenFee);
        $this->assertSame(0.0, $option->consolidatorMarkup);
        $this->assertSame(0.0, $info->agentFee);
        $this->assertSame(0.0, $info->providerServiceFee);
        $this->assertSame(1200.0, $info->grandTotal);
        $this->assertSame(2400.0, $option->price);
    }
}
