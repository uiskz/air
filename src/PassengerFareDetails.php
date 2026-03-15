<?php
declare(strict_types=1);

namespace Uis\Air;

use OpenApi\Attributes as OA;
use Uiskz\Travel\Passenger;

#[OA\Schema(
    schema: 'PassengerFareDetails',
    description: 'Fare details for a passenger',
)]
class PassengerFareDetails
{
    #[OA\Property(
        property: 'passengerType',
        description: 'Passenger type',
        type: 'string',
        example: Passenger::TYPE_ADULT,
        enum: [Passenger::TYPE_ADULT, Passenger::TYPE_CHILD, Passenger::TYPE_INFANT]
    )]
    public string $passengerType;

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
        property: 'discount',
        description: 'Discount amount',
        type: 'double',
        example: 5.00
    )]
    public float $discount = 0.0;

    #[OA\Property(
        property: 'totalTaxes',
        description: 'Total taxes amount',
        type: 'double',
        example: 20.00
    )]
    public float $totalTaxes = 0.0;

    #[OA\Property(
        property: 'serviceFee',
        description: 'Service fee amount',
        type: 'double',
        example: 10.00
    )]
    public float $serviceFee = 0.0;

    #[OA\Property(
        property: 'total',
        description: 'Total amount for the passenger, including taxes and service fee.',
        type: 'double',
        example: 180.00
    )]
    public float $total = 0.0;

    /**
     * @var Tax[] Fare taxes information
     */
    #[OA\Property(
        property: 'taxes',
        description: 'Tax details',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/Tax')
    )]
    public array $taxes = [];

    /**
     * @param Tax[] $taxes Array of taxes to be added.
     * @return void
     */
    public function addTaxes(array $taxes): void
    {
        foreach ($taxes as $tax) {
            $this->addTax($tax);
        }
    }

    public function addTax(Tax $tax): void
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
    }

    public function merge(PassengerFareDetails $fareInfo): void
    {
        $this->fare += $fareInfo->fare;
        $this->total += $fareInfo->total;

        $this->addTaxes($fareInfo->taxes);
    }

    public function convertCurrency(string $code, float $rate): void
    {
        $this->fare = round($this->fare * $rate, 2);
        $this->currency = $code;
        $totalTaxes = 0;
        foreach ($this->taxes as &$tax) {
            if ($code != $tax->currency) {
                $tax->amount = round($tax->amount * $rate, 2);
                $tax->currency = $code;
            }
            $totalTaxes += $tax->amount;
        }
        $this->totalTaxes = $totalTaxes;
        $this->total = $this->fare + $this->totalTaxes;
    }

    public function fillFromTicket(BaseTicket $ticket): void
    {
        $this->passengerType = $ticket->passengerType;
        $this->currency = $ticket->totalCurrency;
        $this->fare = empty($ticket->equivalentFareAmount) ? $ticket->baseFareAmount : $ticket->equivalentFareAmount;
        $this->totalTaxes = $ticket->taxAmount;
        $this->serviceFee = $ticket->serviceFee;
        $this->taxes = $ticket->taxes;
        $this->total = $ticket->grandTotalAmount;
        $this->passengerQty = 1;
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
    }
}