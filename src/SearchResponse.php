<?php
declare(strict_types=1);

namespace Uiskz\Air;

use Psr\Log\LoggerInterface;
use Uiskz\Currency\ConverterInterface;

class SearchResponse
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

    public array $carriers = [];

    public array $carrier_classes = [];

    public array $cities = [];

    public array $airports = [];

    public array $equipments = [];

    public float $minPrice = 0.0;

    public float $maxPrice = 0.0;

    /**
     * @var array Numbers of stops
     */
    public array $stops = [];

    public string $routeType = AirSearchRequest::TYPE_DOMESTIC;

    public string $searchType = AirSearchRequest::SEARCH_TYPE_ONE_WAY;

    protected LoggerInterface|null $logger = null;

    public function __construct(LoggerInterface|null $logger = null)
    {
        $this->logger = $logger;
    }

    public function updatePriceFilters(): void
    {
        foreach ($this->options as $option) {
            if (empty($this->minPrice) || $this->minPrice > $option->price) {
                $this->minPrice = $option->price;
            }
            if (empty($this->maxPrice) || $this->maxPrice < $option->price) {
                $this->maxPrice = $option->price;
            }
        }
    }


}
