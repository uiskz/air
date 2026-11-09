<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests\Fees;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirTax;
use Uiskz\Air\Fees\AirFeeTaxes;
use Uiskz\Travel\Fees\AgencyChain;
use Uiskz\Travel\Fees\FeeProjection;
use Uiskz\Travel\Fees\FeeSlot;
use Uiskz\Travel\Fees\FeeView;

final class AirFeeTaxesTest extends TestCase
{
    private function projection(): FeeProjection
    {
        $projection = new FeeProjection();
        $projection->rootHiddenFee = 30;
        $projection->parentHiddenFee = 20;
        $projection->upperHiddenFee = 10;
        $projection->providerServiceFee = 5;
        $projection->agencyHiddenFee = 4;

        return $projection;
    }

    private function view(int $viewerID): FeeView
    {
        return new FeeView(new AgencyChain([1, 5, 9], [1, 5, 9]), $viewerID);
    }

    public function testMarkupTaxCodeOfEachSlot(): void
    {
        // The root consolidator's markup is AD wherever it sits, other agencies' markups above are S2
        $this->assertSame('AD', AirFeeTaxes::markupTaxCode(FeeSlot::CONSOLIDATOR, true));
        $this->assertSame('AD', AirFeeTaxes::markupTaxCode(FeeSlot::PROVIDER, true));
        $this->assertSame('S2', AirFeeTaxes::markupTaxCode(FeeSlot::CONSOLIDATOR, false));
        $this->assertSame('S2', AirFeeTaxes::markupTaxCode(FeeSlot::PROVIDER, false));
        $this->assertSame(AirTax::AGENT_MARKUP, AirFeeTaxes::markupTaxCode(FeeSlot::AGENCY, false));
        $this->assertNull(AirFeeTaxes::markupTaxCode(FeeSlot::DOWNSTREAM, false));
    }

    public function testTaxCodes(): void
    {
        $this->assertSame('AD', AirTax::CONSOLIDATOR_MARKUP);
        $this->assertSame('AW', AirTax::HIDDEN_SERVICE_FEE);
        $this->assertSame('S2', AirTax::AGENCY_MARKUP);
        $this->assertSame('SD', AirTax::AGENT_MARKUP);
    }

    public function testAgencyWithoutMarkupsModuleSeesItsOwnMarkupAsSd(): void
    {
        $this->assertSame(
            ['AD' => 30.0, 'AW' => 5.0, 'S2' => 30.0, 'SD' => 4.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(9), $this->projection(), false)
        );
    }

    public function testAgencyWithMarkupsModuleDoesNotSeeSd(): void
    {
        $this->assertSame(
            ['AD' => 30.0, 'AW' => 5.0, 'S2' => 30.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(9), $this->projection(), true)
        );
    }

    public function testRootNeverSeesSd(): void
    {
        $this->assertSame(
            ['AD' => 30.0, 'AW' => 5.0, 'S2' => 30.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(1), $this->projection(), false)
        );
    }

    public function testRootSeesChildMarkupAsFeeNotAsTax(): void
    {
        $projection = new FeeProjection();
        $projection->agencyHiddenFee = 4;

        $this->assertSame([], AirFeeTaxes::hiddenFeeTaxes($this->view(1), $projection, false));
    }

    public function testZeroAmountsAreLeftOut(): void
    {
        $this->assertSame([], AirFeeTaxes::hiddenFeeTaxes($this->view(9), new FeeProjection(), false));
    }
}
