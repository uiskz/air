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

    public int $adults = 1;

    public int $children = 0;

    public int $infants = 0;

    public string $class = self::CLASS_ECONOMY;

    public array $childrenAges = [];

    /**
     * @var BaseSearchTrip[]
     */
    public array $trips = [];

    public bool $onlyRefundable = false;

    public string $routeType = self::TYPE_DOMESTIC;

    public string $searchType = self::SEARCH_TYPE_ONE_WAY;

    /**
     * Fills an object with data from an array
     * @param array $data
     * @throws \DateMalformedStringException|\InvalidArgumentException
     */
    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->adults = (int)$data['adults'] ?? 1;
        $this->children = (int)$data['children'] ?? 0;
        $this->infants = (int)$data['infants'] ?? 0;
        $this->setClass($data['class']);
        $this->setTrips($data['trips']);

        if (isset($data['onlyRefundable'])) {
            $this->onlyRefundable = (bool)$data['onlyRefundable'];
        }
        if (isset($data['childrenAges'])) {
            $this->childrenAges = $data['childrenAges'];
        }
    }

    protected function setClass(string $class): void
    {
        $this->class = strtoupper($class);
        if (!in_array($this->class, [self::CLASS_ECONOMY, self::CLASS_BUSINESS, self::CLASS_FIRST])) {
            throw new \InvalidArgumentException('Invalid class');
        }
    }

    /**
     * @param array $trips
     * @return void
     * @throws \DateMalformedStringException
     */
    protected function setTrips(array $trips): void
    {
        if (empty($trips)) {
            throw new \InvalidArgumentException('Empty trips');
        }
        foreach ($trips as $trip) {
            $this->trips[] = new BaseSearchTrip($trip);
            $this->setSearchType();
        }
    }

    private function setSearchType(): void
    {
        if (count($this->trips) == 2 && $this->trips[0]->origin !== $this->trips[1]->destination
            && $this->trips[0]->destination == $this->trips[1]->origin) {
            $this->searchType = self::SEARCH_TYPE_ROUND_TRIP;
        } elseif (count($this->trips) >= 2) {
            $this->searchType = self::SEARCH_TYPE_MULTI_DIRECTIONAL;
        }
    }

    /**
     * Converts the current object state into a string representation.
     *
     * The string includes details of the trips and passenger information,
     * formatted in a specific structure for further usage.
     *
     * @return string The string representation of the object, including trip details and passenger counts.
     */
    public function __toString(): string
    {
        $tripsData = [];
        foreach ($this->trips as $trip) {
            $tripsData[] = (string)$trip;
        }

        return implode(';', $tripsData) . '&' . $this->adults . '_' . $this->children . '_' . $this->infants
            . '&' . $this->class;
    }
}