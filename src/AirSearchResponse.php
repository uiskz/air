<?php
declare(strict_types=1);

namespace Uiskz\Air;

use StephenHill\Base58;
use Uiskz\Currency\ConverterInterface;

class AirSearchResponse extends SearchResponse
{
    public function merge(SearchResponse $response, string $searchID, int $providerID, string $providerMode, string $providerName = null): void
    {
        $base58 = new Base58();
        foreach ($response->options as $option) {
            $mergeOption = clone $option;
            $mergeOption->providerID = $providerID;
            $mergeOption->mode = $providerMode;
            $mergeOption->provider = $providerName;
            $mergeOption->id = $base58->encode($searchID . '_' . $option->id);
            if (property_exists($mergeOption, 'searchType')) {
                $mergeOption->searchType = $this->searchType;
            }
            $this->addOption($mergeOption, true);
        }
        usort($this->options, static function (AirOption $a, AirOption $b) {
            return $a->price <=> $b->price;
        });

        sort($this->stops);
        $this->logger?->info('Объединяем результаты поиска');
    }

    public function addOption(AirOption $option, bool $updateFilters = false): void
    {
        if ($updateFilters) {
            $this->updateFiltersFromOption($option);
        }
        $this->options[] = $option;
    }

    public function updateOption(AirOption $option): void
    {
        foreach ($this->options as $key => $currentOption) {
            if ($currentOption->id == $option->id) {
                $this->options[$key] = $option;
                return;
            }
        }

        $this->addOption($option);
    }

    public function getOption(string $id, ?string $brandsID = null): ?AirOption
    {
        foreach ($this->options as $key => $option) {
            if ($id === $option->id) {
                if (!empty($brandsID)) {
                    $option->setBrandsID($brandsID);
                    $this->options[$key] = $option;
                }
                return $option;
            }
        }
        return null;
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

    protected function buildNextLevelCombination(int $level, array $data): array
    {
        $levels = count($data);
        $combination = [];

        if ($levels == $level + 1) {
            $keys = array_keys($data[$level]);
            if ($levels == 1) {
                foreach ($keys as $key) {
                    $combination[] = [$key];
                }
                return $combination;
            } else {
                return $keys;
            }
        }

        $currentLevel = $data[$level];
        $nextLevel = $this->buildNextLevelCombination($level + 1, $data);
        for ($i = 0; $i < count($currentLevel); $i++) {
            for ($j = 0; $j < count($nextLevel); $j++) {
                $nextLevelElement = $nextLevel[$j];

                if (is_array($nextLevelElement)) {
                    $combination[] = array_merge([$i], $nextLevelElement);
                } else {
                    $combination[] = [
                        $i,
                        $nextLevelElement
                    ];
                }

            }
        }

        return $combination;
    }

    protected function updateFiltersFromOption(AirOption $option): void
    {
        if (!in_array($option->maxStops, $this->stops)) {
            $this->stops[] = $option->maxStops;
        }

        foreach ($option->trips as $trip) {
            if (!in_array($option->carrier, $this->carriers)) {
                $this->carriers[] = $option->carrier;
            }
            if (!in_array($trip->carrier, $this->carriers)) {
                $this->carriers[] = $trip->carrier;
            }
            foreach ($trip->segments as $segment) {
                if (!in_array($segment->carrier, $this->carriers)) {
                    $this->carriers[] = $segment->carrier;
                }
                if (!empty($segment->operatingCarrier) && !in_array($segment->operatingCarrier, $this->carriers)) {
                    $this->carriers[] = $segment->operatingCarrier;
                }
                if (!in_array($segment->origin, $this->airports)) {
                    $this->airports[] = $segment->origin;
                }
                if (!in_array($segment->destination, $this->airports)) {
                    $this->airports[] = $segment->destination;
                }
                if (!in_array($segment->equipment, $this->equipments)) {
                    $this->equipments[] = $segment->equipment;
                }
            }
        }
    }
}
