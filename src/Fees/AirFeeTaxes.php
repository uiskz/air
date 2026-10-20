<?php
declare(strict_types=1);

namespace Uiskz\Air\Fees;

use Uiskz\Air\AirTax;
use Uiskz\Travel\Fees\FeeProjection;
use Uiskz\Travel\Fees\FeeSlot;
use Uiskz\Travel\Fees\FeeView;

/**
 * How hidden fees (markups) are shown in air prices: as taxes.
 * Direct agency -> AD, agencies above it -> SFS, own agency markup -> SD.
 */
final class AirFeeTaxes
{
    /**
     * Tax code a hidden fee of the slot is shown as on a search option; null when it is not shown.
     */
    public static function markupTaxCode(string $slot): ?string
    {
        return match ($slot) {
            FeeSlot::CONSOLIDATOR => AirTax::CONSOLIDATOR_MARKUP,
            FeeSlot::AGENCY => AirTax::AGENT_MARKUP,
            FeeSlot::PROVIDER => AirTax::UPPER_MARKUP,
            default => null,
        };
    }

    /**
     * Hidden fees the viewer sees only as taxes: the parent agency's markup as AD, upper agencies'
     * markups as SFS and, without the markups module, an agency's own markup as SD.
     *
     * @return array<string, float> tax code => amount, zero amounts left out
     */
    public static function hiddenFeeTaxes(FeeView $view, FeeProjection $projection, bool $showMarkup): array
    {
        $amounts = [
            AirTax::CONSOLIDATOR_MARKUP => $projection->parentHiddenFee,
            AirTax::UPPER_MARKUP => $projection->upperHiddenFee,
            AirTax::AGENT_MARKUP => (!$showMarkup && !$view->viewerIsRoot()) ? $projection->agencyHiddenFee : 0.0,
        ];

        return array_filter($amounts, static fn(float $amount): bool => $amount != 0.0);
    }
}
