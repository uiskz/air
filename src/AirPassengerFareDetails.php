<?php
declare(strict_types=1);

namespace Uiskz\Air;

use OpenApi\Attributes as OA;
use Uiskz\Travel\Fees\FeeKind;
use Uiskz\Travel\Fees\FeeView;
use Uiskz\Travel\Fees\OwnerFeesTrait;
use Uiskz\Travel\Traveler;

/**
 * Fare details for one passenger category of an option or a reservation.
 *
 * Field semantics are fixed and must not be changed or swapped:
 *  - $passengerType — the passenger category: ADT, CHD or INF ({@see Traveler}::TYPE_*);
 *  - $type          — the kind of document the fare is for: {@see self::TYPE_TICKET} or {@see self::TYPE_EMD}.
 *
 * Never put the passenger category into $type. Providers that only know the passenger
 * category set $passengerType and leave $type at its TICKET default.
 */
#[OA\Schema(
    schema: 'PassengerFareDetails',
    description: 'Fare details for a passenger',
)]
class AirPassengerFareDetails
{
    use OwnerFeesTrait;

    const string TYPE_TICKET = 'TICKET';

    const string TYPE_EMD = 'EMD';

    #[OA\Property(
        property: 'passengerType',
        description: 'Passenger type',
        type: 'string',
        example: Traveler::TYPE_ADULT,
        enum: [Traveler::TYPE_ADULT, Traveler::TYPE_CHILD, Traveler::TYPE_INFANT]
    )]
    public string $passengerType = Traveler::TYPE_ADULT;

    #[OA\Property(
        property: 'type',
        description: 'Type of fare record: ticket or EMD',
        type: 'string',
        example: self::TYPE_TICKET,
        enum: [self::TYPE_TICKET, self::TYPE_EMD]
    )]
    public string $type = self::TYPE_TICKET;

    #[OA\Property(
        property: 'passengerQty',
        description: 'Number of passengers of the given type.',
        type: 'integer',
        example: 1
    )]
    public int $passengerQty = 0;

    #[OA\Property(
        property: 'fare',
        description: 'Base fare amount',
        type: 'double',
        example: 150.00
    )]
    public float $fare = 0.0;

    #[OA\Property(
        property: 'currency',
        description: 'ISO currency code',
        type: 'string',
        example: 'USD'
    )]
    public string $currency = 'KZT';

    #[OA\Property(
        property: 'totalTaxes',
        description: 'Total taxes amount',
        type: 'double',
        example: 20.00
    )]
    public float $totalTaxes = 0.0;

    #[OA\Property(
        property: 'total',
        description: 'Fare plus taxes for the passenger (markups shown as taxes included, service fees excluded).',
        type: 'double',
        example: 180.00
    )]
    public float $total = 0.0;

    #[OA\Property(
        property: 'discount',
        description: 'Discount amount',
        type: 'double',
        example: 5.00
    )]
    public float $discount = 0.0;

    #[OA\Property(
        property: 'serviceFee',
        description: 'Service fee amount',
        type: 'double',
        example: 10.00
    )]
    public float $serviceFee = 0.0;

    #[OA\Property(
        property: 'agentFee',
        description: 'Travel agency service fee amount',
        type: 'double',
        example: 10.00
    )]
    public float $agentFee = 0.0;

    #[OA\Property(
        property: 'providerServiceFee',
        description: 'Service fees of the agencies above the viewer\'s direct agency',
        type: 'double',
        example: 10.00
    )]
    public float $providerServiceFee = 0.0;

    #[OA\Property(
        property: 'grandTotal',
        description: 'Total plus the service fees of all agencies.',
        type: 'double',
        example: 180.00
    )]
    public float $grandTotal = 0.0;

    protected float $consolidatorMarkup = 0.0;

    protected float $agentMarkup = 0.0;

    /**
     * Amounts with no field of their own (fees of agencies below the viewer, airline fees and
     * penalties): they count only in grandTotal.
     */
    protected float $extraFee = 0.0;

    /**
     * @var AirTax[] Fare taxes information
     */
    #[OA\Property(
        property: 'taxes',
        description: 'Tax details',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/Tax')
    )]
    public array $taxes = [];

    /**
     * @param AirTax[] $taxes Array of taxes to be added.
     * @return void
     */
    public function addTaxes(array $taxes): void
    {
        foreach ($taxes as $tax) {
            $this->addTax($tax);
        }
    }

    public function addTax(AirTax $tax): void
    {
        $found = false;
        foreach ($this->taxes as &$existingTax) {
            if ($existingTax->code === $tax->code) {
                $existingTax->amount += $tax->amount;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->taxes[] = clone $tax;
        }
        $this->totalTaxes += $tax->amount;
        $this->total += $tax->amount;
    }

    public function merge(AirPassengerFareDetails $fareInfo): void
    {
        $this->fare += $fareInfo->fare;
        $this->total += $fareInfo->fare;

        $this->addTaxes($fareInfo->taxes);
    }

    /**
     * Discount is informational: it is shown in the discount field and is not subtracted.
     */
    public function calculateGrandTotal(): void
    {
        $this->grandTotal = $this->total + $this->consolidatorMarkup + $this->agentMarkup
            + $this->serviceFee + $this->agentFee + $this->providerServiceFee + $this->extraFee;
    }

    /**
     * Adds an amount that counts only in grandTotal ({@see $extraFee}).
     */
    public function addExtraFee(float $amount): void
    {
        $this->extraFee += $amount;
        $this->calculateGrandTotal();
    }

    /**
     * Sets the markup of one owner. $ownerID is the fee owner (client id); without it (legacy
     * callers) the consolidator or agent bucket is overwritten.
     */
    public function setMarkup(float $amount, string $type, ?int $ownerID = null): void
    {
        $this->setOwnerAmount($ownerID, $type, FeeKind::MARKUP, $amount, $this->consolidatorMarkup, $this->agentMarkup);
        $this->calculateGrandTotal();
    }

    /**
     * Markup of the consolidator (client 1) or the sum of all agencies' markups.
     */
    public function getMarkup(string $type): float
    {
        if ('agent' == $type) {
            return $this->agentMarkup;
        }

        return $this->consolidatorMarkup;
    }

    /**
     * Sets the service fee of one owner. $ownerID is the fee owner (client id); without it
     * (legacy callers) the consolidator or agent bucket is overwritten.
     */
    public function setServiceFee(float $amount, string $type, ?int $ownerID = null): void
    {
        if (!empty($amount)) {
            $this->setOwnerAmount($ownerID, $type, FeeKind::SERVICE, $amount, $this->serviceFee, $this->agentFee);
        }
        $this->calculateGrandTotal();
    }

    /**
     * Discount is shown separately and is not subtracted from the price.
     */
    public function setDiscount(float $amount, string $type): void
    {
        if ('agent' == $type) {
            $this->discount += $amount;
        } else {
            $this->discount = $amount;
        }
        $this->calculateGrandTotal();
    }

    /**
     * Owner amounts of this fare, completed from its consolidator/agent buckets
     * ({@see FeeView::reconcile()}).
     *
     * @return array<int, array<string, float>>
     */
    public function getReconciledOwnerFees(int $legacyAgencyID): array
    {
        return FeeView::reconcile(
            $this->ownerFees,
            $this->serviceFee,
            $this->consolidatorMarkup,
            $this->agentFee,
            $this->agentMarkup,
            $legacyAgencyID
        );
    }

    public function cleanPrice(): void
    {
        if (!empty($this->serviceFee)) {
            $this->serviceFee = 0;
        }

        if (!empty($this->discount)) {
            $this->discount = 0;
        }
        $this->agentFee = 0.0;
        $this->providerServiceFee = 0.0;
        $this->extraFee = 0.0;

        $this->total = $this->fare + $this->totalTaxes;
        $this->calculateGrandTotal();
    }

//    public function fillFromTicket(BaseTicket $ticket): void
//    {
//        $this->passengerType = $ticket->passengerType;
//        $this->currency = $ticket->totalCurrency;
//        $this->fare = empty($ticket->equivalentFareAmount) ? $ticket->baseFareAmount : $ticket->equivalentFareAmount;
//        $this->totalTaxes = $ticket->taxAmount;
//        $this->serviceFee = $ticket->serviceFee;
//        $this->taxes = $ticket->taxes;
//        $this->total = $ticket->grandTotalAmount;
//        $this->passengerQty = 1;
//    }
}