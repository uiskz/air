<?php
declare(strict_types=1);

namespace Uiskz\Air;

use OpenApi\Attributes as OA;
use Uiskz\Travel\Traveler;

#[OA\Schema(
    schema: 'PassengerFareDetails',
    description: 'Fare details for a passenger',
)]
class AirPassengerFareDetails
{
    #[OA\Property(
        property: 'type',
        description: 'Passenger type',
        type: 'string',
        example: Traveler::TYPE_ADULT,
        enum: [Traveler::TYPE_ADULT, Traveler::TYPE_CHILD, Traveler::TYPE_INFANT]
    )]
    public string $type;

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
        description: 'Total amount for the passenger, including taxes and service fee.',
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
        property: 'grandTotal',
        description: 'Total amount for the passenger, including service fee.',
        type: 'double',
        example: 180.00
    )]
    public float $grandTotal = 0.0;

    protected float $consolidatorMarkup = 0.0;

    protected float $agentMarkup = 0.0;

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
        $this->total += $fareInfo->total;

        $this->addTaxes($fareInfo->taxes);
    }

    public function calculateGrandTotal(): void
    {
        $this->grandTotal = $this->total + $this->consolidatorMarkup + $this->agentMarkup
            + $this->serviceFee + $this->agentFee - $this->discount;
    }

    public function cleanPrice(): void
    {
        if (!empty($this->serviceFee)) {
            $this->serviceFee = 0;
        }

        if (!empty($this->discount)) {
            $this->discount = 0;
        }

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