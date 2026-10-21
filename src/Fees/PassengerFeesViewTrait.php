<?php
declare(strict_types=1);

namespace Uiskz\Air\Fees;

use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirTax;
use Uiskz\Travel\Fees\FeeProjection;
use Uiskz\Travel\Fees\FeeView;

/**
 * Viewer-dependent fee output of an air booking priced by passenger fares.
 */
trait PassengerFeesViewTrait
{
    /**
     * @return AirPassengerFareDetails[]
     */
    abstract protected function feePassengerInfos(): array;

    /**
     * Tax object used for hidden fees shown as taxes; products may return their own subclass.
     */
    protected function newFeeTax(): AirTax
    {
        return new AirTax();
    }

    /**
     * Fees of all passengers as the viewer sees them.
     */
    public function getFeeProjection(FeeView $view): FeeProjection
    {
        $projection = new FeeProjection();
        foreach ($this->feePassengerInfos() as $passengerInfo) {
            $ownerFees = $passengerInfo->getReconciledOwnerFees($view->chain->buyerID());
            $projection->add($view->project($ownerFees), $passengerInfo->passengerQty);
        }

        return $projection;
    }

    /**
     * Provider taxes of all passengers (a passenger fare holds the taxes of one passenger, so they
     * count passengerQty times) plus the hidden fees the viewer may not see as fees
     * ({@see AirFeeTaxes::hiddenFeeTaxes()}).
     *
     * @return AirTax[]
     */
    public function getTaxes(FeeView $view, bool $showMarkup): array
    {
        $taxes = [];
        $currency = '';
        foreach ($this->feePassengerInfos() as $passengerInfo) {
            if (empty($currency)) {
                $currency = $passengerInfo->currency;
            }
            foreach ($passengerInfo->taxes as $tax) {
                $amount = $tax->amount * $passengerInfo->passengerQty;
                foreach ($taxes as $existingTax) {
                    if ($existingTax->code == $tax->code) {
                        $existingTax->amount += $amount;
                        continue 2;
                    }
                }
                $newTax = clone $tax;
                $newTax->amount = $amount;
                $taxes[] = $newTax;
            }
        }

        foreach ($this->hiddenFeeTaxAmounts($view, $showMarkup) as $code => $amount) {
            $tax = $this->newFeeTax();
            $tax->code = $code;
            $tax->amount = $amount;
            $tax->currency = $currency;
            $taxes[] = $tax;
        }

        return $taxes;
    }

    public function getTotalTaxes(FeeView $view, bool $showMarkup): float
    {
        $taxesTotal = 0.0;
        foreach ($this->feePassengerInfos() as $passengerInfo) {
            $taxesTotal += $passengerInfo->totalTaxes * $passengerInfo->passengerQty;
        }

        return $taxesTotal + array_sum($this->hiddenFeeTaxAmounts($view, $showMarkup));
    }

    /**
     * @return array<string, float> tax code => amount, zero amounts left out
     */
    public function hiddenFeeTaxAmounts(FeeView $view, bool $showMarkup): array
    {
        return AirFeeTaxes::hiddenFeeTaxes($view, $this->getFeeProjection($view), $showMarkup);
    }
}
