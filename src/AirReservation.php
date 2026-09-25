<?php
declare(strict_types=1);

namespace Uiskz\Air;

use Uiskz\Currency\ConverterInterface;

class AirReservation extends \Uiskz\Travel\Reservation
{

    public string $validatingCarrier = '';

    /**
     * @var AirSegment[]
     */
    public array $segments = [];

    /**
     * @var AirPassengerFareDetails[]
     */
    public array $passengerInfos = [];

    /**
     * @var AirTicket[]
     */
    public array $tickets = [];

    /**
     * Deadline to ticket a held booking. After this the seats go back.
     */
    public ?\DateTimeInterface $timeLimit = null;

    public function convertCurrency(ConverterInterface $currencyService, string $currencyCode): void
    {
        $this->applyProviderAmounts($currencyService);
    }

    /**
     * Stores original provider amounts on passengerInfos and tickets, then
     * copies or converts them into internal fields in the system currency.
     */
    public function applyProviderAmounts(ConverterInterface $currencyService): void
    {
        $systemCurrency = $currencyService->getDefaultCurrency();
        $date = new \DateTime('now');
        if (isset($this->issuedAt)) {
            $date = $this->issuedAt;
        } elseif (isset($this->createdAt)) {
            $date = $this->createdAt;
        }

        foreach ($this->passengerInfos as $passengerInfo) {
            if (method_exists($passengerInfo, 'applySystemCurrency')) {
                $passengerInfo->applySystemCurrency($currencyService, $systemCurrency, $date);
            }
        }
        foreach ($this->tickets as $ticket) {
            if (method_exists($ticket, 'applySystemCurrency')) {
                $ticket->applySystemCurrency($currencyService, $systemCurrency, $date);
            }
        }

        $this->currency = $systemCurrency;
    }
}
