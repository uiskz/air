<?php
declare(strict_types=1);

namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * Base Air trip
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
#[OA\Schema(
    schema: 'AirTrip',
    required: ['id', 'origin', 'destination'],
)]
class AirTrip
{
    #[OA\Property(
        property: 'id',
        description: 'Trip ID',
        type: 'string',
        example: 'ABC123'
    )]
    public string $id;

    #[OA\Property(
        property: 'origin',
        description: 'Origin IATA code',
        type: 'string',
        example: 'FRA'
    )]
    public string $origin;

    #[OA\Property(
        property: 'destination',
        description: 'Destination IATA code',
        type: 'string',
        example: 'LON'
    )]
    public string $destination;

    public string $departure;

    public string $arrival;

    #[OA\Property(
        property: 'duration',
        description: 'Trip duration, in minutes',
        type: 'integer',
        example: '100'
    )]
    public int $duration = 0;

    /* @var $segments AirSegment[] */
    #[OA\Property(
        property: 'segments',
        description: 'Trip segments',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/AirSegment')
    )]
    public array $segments = [];

    public int $numberOfStops = 0;

    public string $carrier;

    /** @var AirFareFamilies[] Array of fare families */
    public array $packages = [];

    public string $brandID = '';

    public string $brandName = '';

    public array $brandServices = [];

    public function __clone()
    {
        $segments = [];
        foreach ($this->segments as $segment) {
            $segments[] = clone $segment;
        }
        $this->segments = $segments;
    }

    /**
     * Method adds a new segment to the trip
     * @param AirSegment $segment
     * @return void
     */
    public function addSegment(AirSegment $segment): void
    {
        $this->segments[] = $segment;
        $this->resortSegments();
    }

    private function resortSegments(): void
    {
        usort($this->segments, function ($a, $b) {
            $departureA = \DateTime::createFromFormat('Y-m-d H:i:s', $a->departure);
            $departureB = \DateTime::createFromFormat('Y-m-d H:i:s', $b->departure);
            return $departureA->getTimestamp() <=> $departureB->getTimestamp();
        });
        $this->duration = 0;
        foreach ($this->segments as $index => $segment) {
            if (0 == $index) {
                $this->origin = $segment->origin;
                $this->departure = $segment->departure;
            }
            $this->destination = $segment->destination;
            $this->arrival = $segment->arrival;

            $layoverTime = $this->calculateLayover($segment, $index);
            $this->duration += $segment->duration + $layoverTime;
            $this->numberOfStops = count($this->segments) - 1;
        }
    }

    private function calculateLayover(AirSegment $segment, int $index): int
    {
        if ($index <= 0) {
            return 0;
        }

        $departure = \DateTime::createFromFormat('Y-m-d H:i:s', $segment->departure);

        $previousSegment = $this->segments[$index - 1];
        $previousArrival = \DateTime::createFromFormat('Y-m-d H:i:s', $previousSegment->arrival);
        $previousSegment->layover = (int)floor(($departure->getTimestamp() - $previousArrival->getTimestamp()) / 60);

        return $previousSegment->layover;
    }

//    public function sameSegments(AirTrip $trip): bool
//    {
//        if (count($this->segments) != count($trip->segments)) {
//            return false;
//        }
//
//        foreach ($this->segments as $index => $segment) {
//            if (!$segment->sameSegment($trip->segments[$index])) {
//                return false;
//            }
//        }
//
//        return true;
//    }

    public function getKey(): string
    {
        $key = [];
        foreach ($this->segments as $segment) {
            $departure = \DateTime::createFromFormat('Y-m-d H:i:s', $segment->departure);
            $key[] = sprintf('%s_%s_%s', $segment->carrier, $segment->flightNumber, $departure->format('Ymd'));
        }

        return implode(';', $key);
    }

    public function setBrandInfo(BaseFareFamily $family): void
    {
        $this->brandID = $family->id;
        $this->brandName = $family->name;
        $segmentRefs = $family->getSegmentRefs();
        foreach ($segmentRefs as $segmentNumber) {
            $this->segments[$segmentNumber - 1]->setBrandInfo($family, $segmentNumber);
        }
    }

}