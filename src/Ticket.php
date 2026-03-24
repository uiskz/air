<?php
declare(strict_types=1);
namespace Uiskz\Air;

use Uiskz\Travel\Passenger;

/**
 * Base Air tax
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
class Ticket
{
    const string TYPE_TICKET = 'TICKET';

    const string TYPE_EMD = 'EMD';

    const string FOP_CASH = 'CASH';

    const string FOP_INVOICE = 'INVOICE';

    const string STATUS_ISSUED = 'issued';

    const string STATUS_REFUNDED = 'refunded';

    const string STATUS_VOID = 'void';

    const string OPERATION_SALE = 'sale';

    const string OPERATION_REFUND = 'refund';

    const string OPERATION_VOID = 'void';

    const string OPERATION_EXCHANGE = 'exchange';

    public string $number;

    public string $originalNumber;

    public array $associatedTickets = [];

    public array $emds = [];

    public string $issueDate;

    public string $type = self::TYPE_TICKET;

    public string $route_type = '';

    public string $status = '';

    public string $pnrCode;

    public Passenger $passenger;

    public string $passengerType = Passenger::TYPE_ADULT;

    public string $iataNumber;

    public string $fop = self::FOP_CASH;

    public float $baseFareAmount = 0;

    public string $baseFareCurrency = 'KZT';

    public float $equivalentFareAmount = 0;

    public float $taxAmount = 0;

    public float $commissionAmount = 0;

    public float $commissionPercent = 0;

    /**
     * @var Tax[] Ticket taxes
     */
    public array $taxes = [];

    public float $penaltyAmount = 0.0;

    public float $totalAmount = 0;

    public float $serviceFee = 0;

    public float $discount = 0;

    public float $totalVat = 0;

    public float $grandTotalAmount = 0;

    public string $totalCurrency = 'KZT';

    public float $airlineFee = 0;

    public float $consolidatorFee = 0;

    public string $fareCalculation = '';

    public string $documentNumber = '';

    public string $comment = '';

    public string $originDestination = '';

    public string $provider = '';

    public string $airlineName = '';

    public string $endorsement = '';

    public string $ffNumber = '';

    public string $tourCode = '';

    public float $bsr = 1;

    /**
     * @var BaseTicketFare[] Ticket fares
     */
    public array $fares = [];

    /**
     * @var BaseSegment[] Flight Segments
     */
    public array $segments = [];

    public function addSegment(BaseSegment $segment): void {
        $this->segments[] = $segment;

        if (count($this->segments)) {
            $this->originDestination = $this->segments[0]->origin
                . $this->segments[count($this->segments) - 1]->destination;
        }
    }

    public function getTotalTaxAmount(): float
    {
        $amount = 0;
        foreach ($this->taxes as $tax) {
            $amount += $tax->amount;
        }

        return $amount;
    }

    public function setPassenger(Passenger $passenger): void
    {
        $this->passenger = $passenger;
        $this->passengerType = $passenger->type;
    }

    public function addTaxes(array $taxes): void
    {
        foreach ($taxes as $tax) {
            $this->addTax($tax);
        }
    }

    public function addTax(Tax $tax): void
    {
        $this->taxes[] = $tax;
        $this->taxAmount += $tax->amount;
        $this->grandTotalAmount = $this->calculateGrandTotal();
    }

    public function calculateGrandTotal(): float
    {
        return $this->totalAmount + $this->airlineFee + $this->consolidatorFee + $this->serviceFee - $this->discount;
    }

    public function formatNumber(): string
    {
        return substr($this->number, 0, 3) . '-' . substr($this->number, 3);
    }

    public function fillSegmentsFromEmd(Ticket $emd): void
    {
        foreach ($this->segments as &$segment) {
            foreach ($emd->segments as $emdSegment) {
                if ($segment->compare($emdSegment)) {
                    if (empty($segment->meal) && !empty($emdSegment->meal)) {
                        $segment->meal = $emdSegment->meal;
                    }
                    if (empty($segment->seat) && !empty($emdSegment->seat)) {
                        $segment->seat = $emdSegment->seat;
                    }
                    if (empty($segment->bundle) && !empty($emdSegment->bundle)) {
                        $segment->bundle = $emdSegment->bundle;
                    }
                    if (!empty($emdSegment->baggage)) {
                        $segment->addBaggage($emdSegment->baggage);
                    }
                }
            }
        }
    }

    public function setServiceFee(float $serviceFee): void
    {
        $this->serviceFee = $serviceFee;
        $this->grandTotalAmount = $this->calculateGrandTotal();

        foreach ($this->fares as &$fare) {
            if (self::OPERATION_SALE == $fare->operation) {
                $fare->serviceFee = $this->serviceFee;
                $fare->grandTotalAmount = $this->grandTotalAmount;
            }
        }
    }
}