<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests\Fees;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirReservation;
use Uiskz\Air\AirTax;
use Uiskz\Travel\Fees\AgencyChain;
use Uiskz\Travel\Fees\FeeView;

/**
 * Fees of an air reservation for the chain root (1) -> sub-agent (5) -> sub-agent (9, buyer).
 */
final class PassengerFeesViewTraitTest extends TestCase
{
    private function tax(string $code, float $amount): AirTax
    {
        $tax = new AirTax();
        $tax->code = $code;
        $tax->amount = $amount;
        $tax->currency = 'KZT';

        return $tax;
    }

    private function passengerInfo(int $qty, float $yq): AirPassengerFareDetails
    {
        $info = new AirPassengerFareDetails();
        $info->passengerQty = $qty;
        $info->fare = 1000;
        $info->total = 1000;
        $info->addTaxes([$this->tax('YQ', $yq), $this->tax('KZ', 50)]);
        $info->setMarkup(10, 'consolidator', 1);
        $info->setMarkup(20, 'agent', 5);
        $info->setMarkup(4, 'agent', 9);
        $info->setServiceFee(40, 'consolidator', 1);
        $info->setServiceFee(15, 'agent', 5);
        $info->setServiceFee(3, 'agent', 9);

        return $info;
    }

    private function reservation(): AirReservation
    {
        $reservation = new AirReservation();
        $reservation->passengerInfos = [$this->passengerInfo(2, 150), $this->passengerInfo(1, 100)];

        return $reservation;
    }

    private function view(int $viewerID, bool $parentServiceFeeShown = true): FeeView
    {
        return new FeeView(new AgencyChain([1, 5, 9], [1, 5, 9]), $viewerID, $parentServiceFeeShown);
    }

    /**
     * @param AirTax[] $taxes
     * @return array<string, float>
     */
    private function amounts(array $taxes): array
    {
        $amounts = [];
        foreach ($taxes as $tax) {
            $amounts[$tax->code] = $tax->amount;
        }

        return $amounts;
    }

    public function testBuyerAgencySeesUpperAgenciesFeesByLevel(): void
    {
        $projection = $this->reservation()->getFeeProjection($this->view(9));

        $this->assertSame(9.0, $projection->agencyServiceFee);
        $this->assertSame(12.0, $projection->agencyHiddenFee);
        $this->assertSame(45.0, $projection->consolidatorServiceFee);
        $this->assertSame(60.0, $projection->parentHiddenFee);
        $this->assertSame(120.0, $projection->providerServiceFee);
        $this->assertSame(30.0, $projection->upperHiddenFee);
    }

    public function testRootSeesItsOwnFeesAndFirstLevelSubAgentFeesAsAgency(): void
    {
        $projection = $this->reservation()->getFeeProjection($this->view(1));

        $this->assertSame(120.0, $projection->consolidatorServiceFee);
        $this->assertSame(30.0, $projection->consolidatorHiddenFee);
        $this->assertSame(45.0, $projection->agencyServiceFee);
        $this->assertSame(60.0, $projection->agencyHiddenFee);
        $this->assertSame(9.0, $projection->downstreamServiceFee);
        $this->assertSame(12.0, $projection->downstreamHiddenFee);
        $this->assertSame(0.0, $projection->parentHiddenFee);
    }

    public function testFirstLevelSubAgentSeesConsolidatorFeeAsFeeAndMarkupAsAd(): void
    {
        $reservation = $this->reservation();

        $this->assertSame(120.0, $reservation->getFeeProjection($this->view(5))->consolidatorServiceFee);
        $this->assertSame(['AD' => 30.0], $reservation->hiddenFeeTaxAmounts($this->view(5), true));
    }

    public function testSubAgentWithoutServiceFeesSeesConsolidatorFeeAsAw(): void
    {
        $reservation = $this->reservation();
        $view = $this->view(5, false);

        $this->assertSame(0.0, $reservation->getFeeProjection($view)->consolidatorServiceFee);
        $this->assertSame(['AD' => 30.0, 'AW' => 120.0], $reservation->hiddenFeeTaxAmounts($view, true));
    }

    public function testLegacyAgentBucketsBelongToTheBuyer(): void
    {
        $info = new AirPassengerFareDetails();
        $info->passengerQty = 1;
        $info->setMarkup(7, 'agent');
        $reservation = new AirReservation();
        $reservation->passengerInfos = [$info];

        $this->assertSame(7.0, $reservation->getFeeProjection($this->view(9))->agencyHiddenFee);
        $this->assertSame(7.0, $reservation->getFeeProjection($this->view(5))->downstreamHiddenFee);
    }

    public function testTaxesOfBuyerAgencyIncludeHiddenFeesAsTaxes(): void
    {
        $reservation = $this->reservation();

        $taxes = $reservation->getTaxes($this->view(9), false);

        // per-passenger taxes times passengerQty: YQ 150 * 2 + 100, KZ 50 * 3
        $this->assertSame(
            ['YQ' => 400.0, 'KZ' => 150.0, 'AD' => 60.0, 'AW' => 150.0, 'SD' => 12.0],
            $this->amounts($taxes)
        );
        $this->assertSame('KZT', $taxes[4]->currency);
        // provider taxes of the passenger fares stay untouched
        $this->assertSame(150.0, $reservation->passengerInfos[0]->taxes[0]->amount);
    }

    public function testTaxesOfRootHaveNoHiddenFees(): void
    {
        $this->assertSame(
            ['YQ' => 400.0, 'KZ' => 150.0],
            $this->amounts($this->reservation()->getTaxes($this->view(1), false))
        );
    }

    public function testTaxesBreakdownAddsUpToTotalTaxes(): void
    {
        $reservation = $this->reservation();

        foreach ([[9, false], [9, true], [5, false], [1, false]] as [$viewerID, $showMarkup]) {
            $view = $this->view($viewerID);
            $this->assertSame(
                $reservation->getTotalTaxes($view, $showMarkup),
                array_sum($this->amounts($reservation->getTaxes($view, $showMarkup)))
            );
        }
    }

    public function testMiddleAgencyWithMarkupsModuleSeesOnlyParentMarkup(): void
    {
        $this->assertSame(
            ['AD' => 30.0],
            $this->reservation()->hiddenFeeTaxAmounts($this->view(5), true)
        );
    }

    public function testTotalTaxesCountPassengersAndHiddenFees(): void
    {
        $reservation = $this->reservation();

        // (200 * 2 + 150) + AD 60 + AW (markup 30 + service fee 120) + SD 12
        $this->assertSame(772.0, $reservation->getTotalTaxes($this->view(9), false));
        $this->assertSame(550.0, $reservation->getTotalTaxes($this->view(1), true));
    }

    public function testHiddenFeeTaxesUseTaxFactory(): void
    {
        $reservation = new class extends AirReservation {
            protected function newFeeTax(): AirTax
            {
                return new class extends AirTax {
                };
            }
        };
        $reservation->passengerInfos = [$this->passengerInfo(1, 100)];

        $taxes = $reservation->getTaxes($this->view(9), false);

        $this->assertNotSame(AirTax::class, end($taxes)::class);
    }
}
