<?php

namespace Uiskz\Air;

use Psr\Log\LoggerInterface;
use Uiskz\Currency\ConverterInterface;

class AirSearchResponse
{
    public string $currency;

    /**
     * List of errors encountered during the search process.
     *
     * @var string[]
     */
    public array $errors = [];

    /**
     * List of available options for the search request.
     * @var AirOption[]
     */
    public array $options = [];

    protected LoggerInterface|null $logger = null;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function addOption(AirOption $option): void
    {
        $this->options[] = $option;
    }

    public function convertCurrency(ConverterInterface $currencyService, string $currencyCode): void
    {
        $this->applyProviderAmounts($currencyService);
    }

    /**
     * Stores original provider amounts on options and converts them into the
     * system default currency when the search result currency differs.
     */
    public function applyProviderAmounts(ConverterInterface $currencyService): void
    {
        $this->currency = $currencyService->getDefaultCurrency();
        $date = new \DateTime('now');
        foreach ($this->options as $option) {
            if (method_exists($option, 'applySystemCurrency')) {
                $option->applySystemCurrency($currencyService, $this->currency, $date);
            }
        }
    }
}