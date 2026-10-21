<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests\Support;

use Uiskz\Currency\ConverterInterface;

/**
 * Converter with fixed rates to the default currency; records every conversion.
 */
final class FixedRateConverter implements ConverterInterface
{
    /** @var array<int, array{string, string, float}> from, to, amount */
    public array $calls = [];

    /**
     * @param array<string, float> $rates currency => units of the default currency per unit
     */
    public function __construct(
        private readonly string $defaultCurrency = 'KZT',
        private readonly array $rates = [],
    ) {
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    public function convertCurrency(string $fromCurrency, string $toCurrency, float $amount, \DateTimeInterface $date): float
    {
        $this->calls[] = [$fromCurrency, $toCurrency, $amount];

        return $amount * $this->getCurrencyToCurrencyRate($fromCurrency, $toCurrency, $date);
    }

    public function getCurrencyToCurrencyRate(string $fromCurrency, string $toCurrency, \DateTimeInterface $date): float
    {
        return $this->rate($fromCurrency) / $this->rate($toCurrency);
    }

    public function getCurrencyRate(string $currencyCode, \DateTimeInterface $date): array
    {
        return ['rate' => $this->rate($currencyCode)];
    }

    private function rate(string $currency): float
    {
        return $currency === $this->defaultCurrency ? 1.0 : $this->rates[$currency];
    }
}
