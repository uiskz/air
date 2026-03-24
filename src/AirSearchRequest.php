<?php
declare(strict_types=1);

namespace Uiskz\Air;

use Uiskz\Travel\BaseSearchTrip;

class AirSearchRequest
{
    const string CLASS_ECONOMY = 'Y';

    const string CLASS_BUSINESS = 'B';

    const string CLASS_FIRST = 'F';

    const string TYPE_DOMESTIC = 'D';

    const string TYPE_INTERNATIONAL = 'I';

    const string SEARCH_TYPE_ONE_WAY = 'OW';

    const string SEARCH_TYPE_ROUND_TRIP = 'RT';

    const string SEARCH_TYPE_MULTI_DIRECTIONAL = 'MD';

    public string $id;

    public string $currency;

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    public string $class = self::CLASS_ECONOMY;

    public array $childrenAges = [];

    public array $travelers = [];

    /**
     * @var BaseSearchTrip[]
     */
    public array $trips = [];

    public bool $onlyRefundable = false;

    public string $routeType = self::TYPE_DOMESTIC;

    public string $searchType = self::SEARCH_TYPE_ONE_WAY;


    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->currency = $data['currency'];
        $this->adults = (int)$data['adults'] ?? 1;
        $this->children = (int)$data['children'] ?? 0;
        $this->infants = (int)$data['infants'] ?? 0;
        $this->setClass($data['class']);
        $this->setTrips($data['trips']);
    }

    protected function setClass(string $class): void
    {
        $this->class = strtoupper($class);
        if (!in_array($this->class, [self::CLASS_ECONOMY, self::CLASS_BUSINESS, self::CLASS_FIRST])) {
            throw new \InvalidArgumentException('Invalid class');
        }
    }

    protected function setTrips(array $trips): void
    {
        if (empty($trips)) {
            throw new \InvalidArgumentException('Empty trips');
        }
        foreach ($trips as $trip) {
            $this->trips[] = new BaseSearchTrip($trip);
        }
    }

    public function getCacheKey(): string
    {
        $tripsData = [];
        foreach ($this->trips as $trip) {
            $tripsData[] = $trip->origin . '_' . $trip->destination . '_' . $trip->date->format('Y-m-d');
        }

        return implode(';', $tripsData) . '&' . $this->adults . '_' . $this->children . '_' . $this->infants
            . '&' . $this->class;
    }
}