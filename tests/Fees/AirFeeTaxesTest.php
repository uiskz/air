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
        $projection->parentHiddenFee = 20;
        $projection->upperHiddenFee = 10;
        $projection->agencyHiddenFee = 4;

        return $projection;
    }

    private function view(int $viewerID): FeeView
    {
        return new FeeView(new AgencyChain([1, 5, 9], [1, 5, 9]), $viewerID);
    }

    public function testMarkupTaxCodeOfEachSlot(): void
    {
        $this->assertSame(AirTax::CONSOLIDATOR_MARKUP, AirFeeTaxes::markupTaxCode(FeeSlot::CONSOLIDATOR));
        $this->assertSame(AirTax::AGENT_MARKUP, AirFeeTaxes::markupTaxCode(FeeSlot::AGENCY));
        $this->assertSame(AirTax::UPPER_MARKUP, AirFeeTaxes::markupTaxCode(FeeSlot::PROVIDER));
        $this->assertNull(AirFeeTaxes::markupTaxCode(FeeSlot::DOWNSTREAM));
    }

    public function testAgencyWithoutMarkupsModuleSeesItsOwnMarkupAsSd(): void
    {
        $this->assertSame(
            ['AD' => 20.0, 'SFS' => 10.0, 'SD' => 4.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(9), $this->projection(), false)
        );
    }

    public function testAgencyWithMarkupsModuleDoesNotSeeSd(): void
    {
        $this->assertSame(
            ['AD' => 20.0, 'SFS' => 10.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(9), $this->projection(), true)
        );
    }

    public function testRootNeverSeesSd(): void
    {
        $this->assertSame(
            ['AD' => 20.0, 'SFS' => 10.0],
            AirFeeTaxes::hiddenFeeTaxes($this->view(1), $this->projection(), false)
        );
    }

    public function testZeroAmountsAreLeftOut(): void
    {
        $this->assertSame([], AirFeeTaxes::hiddenFeeTaxes($this->view(9), new FeeProjection(), false));
    }
}
