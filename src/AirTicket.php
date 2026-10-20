<?php
declare(strict_types=1);
namespace Uiskz\Air;

use Uiskz\Currency\ConverterInterface;
use Uiskz\Travel\Fees\FeeKind;
use Uiskz\Travel\Fees\OwnerFeesTrait;
use Uiskz\Travel\Traveler;

/**
 * Air ticket or EMD with amounts, fees of every agency level and accounting lines
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
class AirTicket
{
    use OwnerFeesTrait;

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

    /** Extra ticket numbers issued in conjunction with this ticket. */
    public array $conjunctionTickets = [];

    public array $emds = [];

    public string $issueDate;

    public string $type = self::TYPE_TICKET;

    public string $route_type = '';

    public string $status = '';

    public string $pnrCode;

    public Traveler $passenger;

    public string $passengerType = Traveler::TYPE_ADULT;

    public string $iataNumber;

    public string $fop = self::FOP_CASH;

    public float $baseFareAmount = 0;

    public string $baseFareCurrency = 'KZT';

    public float $equivalentFareAmount = 0;

    public float $taxAmount = 0;

    public string $providerCurrency = '';

    public float $providerBaseFareAmount = 0;

    public float $providerEquivalentFareAmount = 0;

    public float $providerTaxAmount = 0;

    public float $providerTotalAmount = 0;

    public float $commissionAmount = 0;

    public float $commissionPercent = 0;

    /**
     * @var AirTax[] Ticket taxes
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

    /** Consolidator markup (hidden fee) */
    public float $consolidatorFee = 0;

    /** Service fees of all agencies below the consolidator */
    public float $agentServiceFee = 0;

    /** Markups of all agencies below the consolidator */
    public float $agentMarkup = 0;

    /**
     * Service fees of the agencies above the viewer's direct agency. Display-only:
     * filled when a ticket is shown to a viewer, not part of the stored totals.
     */
    public float $providerServiceFee = 0;

    /** Consolidator markup applied by void rules (does not overwrite sale consolidatorFee). */
    public float $voidMarkup = 0;

    /** Consolidator service fee applied by void rules (does not overwrite sale serviceFee). */
    public float $voidFee = 0;

    /** Agency markup applied by void rules (does not overwrite sale agentMarkup). */
    public float $voidAgentMarkup = 0;

    /** Agency service fee applied by void rules (does not overwrite sale agentServiceFee). */
    public float $voidAgentFee = 0;

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
     * @var AirTicketFare[] Ticket fares
     */
    public array $fares = [];

    /**
     * @var AirSegment[] Flight Segments
     */
    public array $segments = [];

    public function addSegment(AirSegment $segment): void
    {
        $this->segments[] = $segment;

        if (count($this->segments)) {
            $this->originDestination = $this->segments[0]->origin
                . $this->segments[count($this->segments) - 1]->destination;
        }
    }

    public function getTotalTaxAmount(): float
    {
        $amount = 0.0;
        foreach ($this->taxes as $tax) {
            $amount += $tax->amount;
        }

        return $amount;
    }

    public function setPassenger(Traveler $passenger): void
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

    /**
     * Adds a tax, merging it into an existing tax of the same code, and refreshes the totals.
     */
    public function addTax(AirTax $tax): void
    {
        foreach ($this->taxes as $existingTax) {
            if ($existingTax->code === $tax->code) {
                $existingTax->amount += $tax->amount;
                $this->taxAmount += $tax->amount;
                $this->grandTotalAmount = $this->calculateGrandTotal();
                return;
            }
        }

        if (empty($tax->currency)) {
            $tax->currency = $this->totalCurrency;
        }

        $this->taxes[] = $tax;
        $this->taxAmount += $tax->amount;
        if (empty($this->equivalentFareAmount)) {
            $this->totalAmount = $this->baseFareAmount + $this->taxAmount;
        } else {
            $this->totalAmount = $this->equivalentFareAmount + $this->taxAmount;
        }
        $this->grandTotalAmount = $this->calculateGrandTotal();
    }

    /**
     * Discount is informational: it is shown in the discount field and is not subtracted.
     */
    public function calculateGrandTotal(): float
    {
        return $this->totalAmount + $this->airlineFee + $this->consolidatorFee + $this->serviceFee
            + $this->agentServiceFee + $this->agentMarkup;
    }

    public function updateGrandTotal(): void
    {
        $this->grandTotalAmount = $this->calculateGrandTotal();
        foreach ($this->fares as $fare) {
            if (self::OPERATION_SALE == $fare->operation) {
                $fare->serviceFee = $this->serviceFee;
                $fare->consolidatorFee = $this->consolidatorFee;
                $fare->grandTotalAmount = $this->grandTotalAmount;
            }
        }
    }

    public function formatNumber(): string
    {
        return substr($this->number, 0, 3) . '-' . substr($this->number, 3);
    }

    /**
     * Conjunction tickets print as 6552441294842-43. Other tickets keep the airline-code dash.
     */
    public function printedNumber(): string
    {
        if ($this->conjunctionTickets === []) {
            return $this->formatNumber();
        }

        $last = (string)end($this->conjunctionTickets);
        $suffix = strlen($last) >= 2 ? substr($last, -2) : $last;

        return $this->number . '-' . $suffix;
    }

    public function fillSegmentsFromEmd(AirTicket $emd): void
    {
        foreach ($this->segments as $segment) {
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

    /**
     * Copies fees of the passenger fare onto the ticket: sale fees, or void fees
     * (into the void* fields, sale fields untouched).
     */
    public function setServiceFees(AirPassengerFareDetails $pi, string $operation = self::OPERATION_SALE): void
    {
        if (self::OPERATION_VOID === $operation) {
            $this->copyOwnerFees($pi, [FeeKind::SERVICE => FeeKind::VOID_SERVICE, FeeKind::MARKUP => FeeKind::VOID_MARKUP]);
            $consolidatorFee = $pi->getMarkup('consolidator');
            if ($consolidatorFee) {
                $this->voidMarkup = $consolidatorFee;
            }
            $agentFee = $pi->getMarkup('agent');
            if ($agentFee) {
                $this->voidAgentMarkup = $agentFee;
            }
            if (!empty($pi->serviceFee)) {
                $this->setVoidFee($pi->serviceFee, 'consolidator');
            }
            if (!empty($pi->agentFee)) {
                $this->setVoidFee($pi->agentFee, 'agent');
            }
            return;
        }

        $this->copyOwnerFees($pi, [FeeKind::SERVICE => FeeKind::SERVICE, FeeKind::MARKUP => FeeKind::MARKUP]);
        $this->discount = $pi->discount;
        $consolidatorFee = $pi->getMarkup('consolidator');
        if ($consolidatorFee) {
            $this->consolidatorFee = $consolidatorFee;
        }
        $agentFee = $pi->getMarkup('agent');
        if ($agentFee) {
            $this->agentMarkup = $agentFee;
        }
        if (!empty($pi->serviceFee)) {
            $this->setServiceFee($pi->serviceFee, 'consolidator');
        }
        if (!empty($pi->agentFee)) {
            $this->setServiceFee($pi->agentFee, 'agent');
        }
    }

    /**
     * Replaces the ticket's per-owner amounts of the mapped kinds with the passenger fare ones.
     *
     * @param array<string, string> $kinds passenger fare kind => ticket kind
     */
    private function copyOwnerFees(AirPassengerFareDetails $pi, array $kinds): void
    {
        $ownerFees = $this->ownerFees;
        foreach ($ownerFees as $ownerID => $fees) {
            foreach ($kinds as $ticketKind) {
                unset($ownerFees[$ownerID][$ticketKind]);
            }
        }
        foreach ($pi->getOwnerFees() as $ownerID => $fees) {
            foreach ($kinds as $piKind => $ticketKind) {
                if (isset($fees[$piKind])) {
                    $ownerFees[$ownerID][$ticketKind] = $fees[$piKind];
                }
            }
        }
        $this->setOwnerFees(array_filter($ownerFees));
    }

    /**
     * Sets a service fee of one owner. Without $ownerID (legacy callers) the consolidator
     * or agent bucket is overwritten.
     */
    public function setServiceFee(float $serviceFee, string $type = 'consolidator', ?int $ownerID = null): void
    {
        $this->setOwnerAmount($ownerID, $type, FeeKind::SERVICE, $serviceFee, $this->serviceFee, $this->agentServiceFee);
        $this->grandTotalAmount = $this->calculateGrandTotal();

        foreach ($this->fares as $fare) {
            if (self::OPERATION_SALE == $fare->operation) {
                $fare->consolidatorFee = $this->consolidatorFee;
                $fare->serviceFee = $this->serviceFee;
                $fare->agentServiceFee = $this->agentServiceFee;
                $fare->agentMarkup = $this->agentMarkup;
                $fare->grandTotalAmount = $this->grandTotalAmount;
            }
        }
    }

    /**
     * Sets void-operation service fee of one owner without touching sale fields.
     */
    public function setVoidFee(float $serviceFee, string $type, ?int $ownerID = null): void
    {
        $this->setOwnerAmount($ownerID, $type, FeeKind::VOID_SERVICE, $serviceFee, $this->voidFee, $this->voidAgentFee);
    }

    /**
     * Sets void-operation markup of one owner without touching sale fields.
     */
    public function setVoidMarkup(float $markup, string $type, ?int $ownerID = null): void
    {
        $this->setOwnerAmount($ownerID, $type, FeeKind::VOID_MARKUP, $markup, $this->voidMarkup, $this->voidAgentMarkup);
    }

    /**
     * Builds sale (and void, when status is void) fare lines from ticket amounts.
     *
     * @param bool $returnServiceFee When true, void fare returns (credits) the issue serviceFee
     *                               and charges voidFee: net serviceFee = voidFee - issueServiceFee.
     *                               When false, only voidFee is charged.
     */
    public function populateFares(bool $returnServiceFee = true): void
    {
        $ticketFare = $this->newFare();
        $ticketFare->fillFromTicket($this, self::OPERATION_SALE);
        $this->fares[] = $ticketFare;

        if (self::STATUS_VOID == $this->status) {
            $voidFare = $this->newFare();
            $voidFare->fillFromTicket($this, self::OPERATION_VOID);

            $returnedServiceFee = $returnServiceFee ? $this->serviceFee : 0;
            $voidFare->serviceFee = $this->voidFee - $returnedServiceFee;
            // fillFromTicket already included voidFee in grandTotal; credit back issue fee when returning
            $voidFare->grandTotalAmount -= $returnedServiceFee;

            $this->fares[] = $voidFare;
        }
    }

    /**
     * Fare line object used by {@see populateFares()}; products may return their own subclass.
     */
    protected function newFare(): AirTicketFare
    {
        return new AirTicketFare();
    }

    /**
     * Copies provider amounts into internal fields, converting them when the provider
     * currency differs from the system currency.
     */
    public function applySystemCurrency(
        ConverterInterface $currencyService,
        string $systemCurrency,
        \DateTimeInterface $date
    ): void {
        $this->captureProviderAmounts($systemCurrency);

        if ($this->providerCurrency === $systemCurrency) {
            $this->baseFareAmount = $this->providerBaseFareAmount;
            $this->equivalentFareAmount = $this->providerEquivalentFareAmount;
            $this->taxAmount = $this->providerTaxAmount;
            $this->totalAmount = $this->providerTotalAmount;
            $this->totalCurrency = $systemCurrency;
            if ($this->baseFareCurrency === '' || $this->baseFareCurrency === $this->providerCurrency) {
                $this->baseFareCurrency = $systemCurrency;
            }
            foreach ($this->taxes as $tax) {
                $tax->amount = $tax->providerAmount;
                $tax->currency = $systemCurrency;
            }
            $this->grandTotalAmount = $this->calculateGrandTotal();
            return;
        }

        $from = $this->providerCurrency;
        if ($this->baseFareCurrency === '' || $this->baseFareCurrency === $from) {
            $this->baseFareAmount = $currencyService->convertCurrency(
                $from,
                $systemCurrency,
                $this->providerBaseFareAmount,
                $date
            );
            $this->baseFareCurrency = $systemCurrency;
        } else {
            $this->baseFareAmount = $this->providerBaseFareAmount;
        }
        $this->equivalentFareAmount = $currencyService->convertCurrency(
            $from,
            $systemCurrency,
            $this->providerEquivalentFareAmount,
            $date
        );
        $this->taxAmount = $currencyService->convertCurrency(
            $from,
            $systemCurrency,
            $this->providerTaxAmount,
            $date
        );
        $this->totalAmount = $currencyService->convertCurrency(
            $from,
            $systemCurrency,
            $this->providerTotalAmount,
            $date
        );
        $this->totalCurrency = $systemCurrency;
        foreach ($this->taxes as $tax) {
            $tax->amount = $currencyService->convertCurrency(
                $from,
                $systemCurrency,
                $tax->providerAmount,
                $date
            );
            $tax->currency = $systemCurrency;
        }
        $this->grandTotalAmount = $this->calculateGrandTotal();
    }

    private function captureProviderAmounts(string $systemCurrency): void
    {
        if ($this->providerCurrency !== '') {
            return;
        }

        $this->providerCurrency = $this->totalCurrency !== '' ? $this->totalCurrency : $systemCurrency;
        $this->providerBaseFareAmount = $this->baseFareAmount;
        $this->providerEquivalentFareAmount = $this->equivalentFareAmount;
        $this->providerTaxAmount = $this->taxAmount;
        $this->providerTotalAmount = $this->totalAmount;
        foreach ($this->taxes as $tax) {
            $tax->providerAmount = $tax->amount;
        }
    }
}
