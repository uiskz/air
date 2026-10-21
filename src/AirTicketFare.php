<?php
declare(strict_types=1);

namespace Uiskz\Air;

/**
 * Accounting line of a ticket for one operation (sale or void): amounts with sign, fees and markups.
 * @package Uiskz\Air
 */
class AirTicketFare
{
    public const string COMMISSION_PERCENT = 'percent';

    public const string COMMISSION_AMOUNT = 'amount';

    public string $operation;

    public \DateTimeInterface $date;

    public float $baseFare = 0;

    public string $baseCurrency = 'KZT';

    public float $equivalentFare = 0;

    public string $equivalentCurrency = '';

    public float $commissionPercent = 0;

    public float $commissionAmount = 0;

    public string $commissionType = self::COMMISSION_PERCENT;

    public float $taxesAmount = 0;

    public string $totalCurrency = 'KZT';

    public float $penaltyAmount = 0;

    public float $totalAmount = 0;

    public float $consolidatorFee = 0;

    public float $airlineFee = 0;

    public float $serviceFee = 0;

    public float $grandTotalAmount = 0;

    public float $agentServiceFee = 0;

    public float $agentMarkup = 0;

    /**
     * Fills the line from ticket amounts. A void line is negative and charges the void fees
     * while returning the sale markups and agency fees. A sale line is dated by the ticket
     * issue date, or now when it is missing or malformed.
     */
    public function fillFromTicket(AirTicket $ticket, string $operation): void
    {
        $multiplier = 1;
        $this->operation = $operation;
        $this->date = new \DateTime('now', new \DateTimeZone('UTC'));
        if (AirTicket::OPERATION_VOID == $operation) {
            $multiplier = -1;
        } else {
            if (!empty($ticket->issueDate)) {
                $date = \DateTime::createFromFormat('Y-m-d H:i:s', $ticket->issueDate, new \DateTimeZone('Asia/Almaty'));
                if (!empty($date)) {
                    $date->setTimezone(new \DateTimeZone('UTC'));
                    $this->date = $date;
                }
            }
        }
        $this->baseCurrency = $ticket->baseFareCurrency;
        $this->baseFare = $ticket->baseFareAmount * $multiplier;
        $this->equivalentCurrency = $ticket->totalCurrency;
        $this->equivalentFare = $ticket->equivalentFareAmount * $multiplier;
        if (empty($ticket->commissionPercent) && !empty($ticket->commissionAmount)) {
            $this->commissionType = self::COMMISSION_AMOUNT;
        }
        $this->commissionPercent = $ticket->commissionPercent;
        $this->commissionAmount = $ticket->commissionAmount * $multiplier;
        $this->taxesAmount = $ticket->taxAmount * $multiplier;
        $this->airlineFee = $ticket->airlineFee * $multiplier;
        if (AirTicket::OPERATION_VOID == $operation) {
            $this->consolidatorFee = $ticket->voidMarkup - $ticket->consolidatorFee;
            $this->agentServiceFee = $ticket->voidAgentFee - $ticket->agentServiceFee;
            $this->agentMarkup = $ticket->voidAgentMarkup - $ticket->agentMarkup;
            $this->serviceFee = $ticket->voidFee;
        } else {
            $this->consolidatorFee = $ticket->consolidatorFee * $multiplier;
            $this->agentServiceFee = $ticket->agentServiceFee * $multiplier;
            $this->agentMarkup = $ticket->agentMarkup * $multiplier;
            $this->serviceFee = $ticket->serviceFee * $multiplier;
        }
        $this->penaltyAmount = $ticket->penaltyAmount * $multiplier;
        $this->totalCurrency = $ticket->totalCurrency;
        $this->totalAmount = $ticket->totalAmount * $multiplier;
        if (empty($this->equivalentFare)) {
            $this->grandTotalAmount = $this->baseFare;
        } else {
            $this->grandTotalAmount = $this->equivalentFare;
        }
        $this->grandTotalAmount += $this->taxesAmount + $this->airlineFee + $this->consolidatorFee + $this->serviceFee
            + $this->agentServiceFee + $this->agentMarkup + $this->penaltyAmount;
    }
}
