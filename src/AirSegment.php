<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * AirSegment is the base class for all Air Segments classes used in providers
 *
 * AirSegment provides general information about an air segment DTO
 *
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
#[OA\Schema(
    schema: 'AirSegment',
    description: 'Air flight segment',
    required: ['origin', 'destination', 'carrier', 'flightNumber', 'bookingClass', 'departure', 'arrival'],
)]
class AirSegment
{
    /**
     * @var string XO status
     */
    public string $xoStatus = '';

    /**
     *  @var string Arrival date and time
     */
    #[OA\Property(
        property: 'arrival',
        description: 'Arrival date and time. Format: `YYYY-MM-DD HH:MM:SS`',
        type: 'string',
        format: 'date-time',
        example: '2025-11-30 16:25:00'
    )]
    public string $arrival;

    public \DateTime $arrivalUtc;

    /**
     * @var string|null Arrival airport terminal code
     */
    #[OA\Property(
        property: 'arrivalTerminal',
        description: 'Arrival airport terminal code',
        type: 'string',
        example: '1',
        nullable: true,
    )]
    public ?string $arrivalTerminal = null;

    /**
     * @var string Baggage allowance
     */
    #[OA\Property(
        property: 'baggage',
        description: 'Baggage allowance',
        type: 'string',
        example: '20KG',
        nullable: true,
    )]
    public string $baggage = '';

    /**
     * @var string Carry on allowance
     */
    #[OA\Property(
        property: 'carryOn',
        description: 'Carry on allowance',
        type: 'string',
        example: '8KG',
        nullable: true,
    )]
    public string $carryOn;

    /**
     * @var string Booking class code
     */
    #[OA\Property(
        property: 'bookingClass',
        description: 'Booking class code',
        type: 'string',
        example: 'S'
    )]
    public string $bookingClass;

    /**
     * @var string Booking class code
     */
    #[OA\Property(
        property: 'serviceClass',
        description: 'Service class code. `Y` - Economy, `B` - Business, `F` - First Class',
        type: 'string',
        example: 'Y',
        enum: ['Y', 'B', 'F']
    )]
    public string $serviceClass;

    /**
     * Marketing carrier IATA code
     *
     * @var string
     */
    #[OA\Property(
        property: 'carrier',
        description: 'Marketing carrier IATA code',
        type: 'string',
        example: 'LH'
    )]
    public string $carrier;

    public string $carrierName;

    /**
     *  @var string Departure date and time
     */
    #[OA\Property(
        property: 'departure',
        description: 'Departure date and time. Format: `YYYY-MM-DD HH:MM:SS`',
        type: 'string',
        format: 'date-time',
        example: '2025-11-30 20:05:00'
    )]
    public string $departure;

    public \DateTime $departureUtc;

    /**
     * @var string|null Departure airport terminal code
     */
    #[OA\Property(
        property: 'departureTerminal',
        description: 'Departure airport terminal code',
        type: 'string',
        example: 'E',
        nullable: true,
    )]
    public ?string $departureTerminal = null;

    /**
     * @var string Arrival airport IATA code
     */
    #[OA\Property(
        property: 'destination',
        description: 'Arrival airport IATA code',
        type: 'string',
        example: 'LON'
    )]
    public string $destination;

    public string $destinationName;

    /**
     * @var int Layover time before the next flight, in minutes
     */
    #[OA\Property(
        property: 'layover',
        description: 'Layover time before the next flight, in minutes',
        type: 'integer',
        example: 90,
    )]
    public int $layover = 0;

    /**
     * @var int Flight duration, in minutes
     */
    #[OA\Property(
        property: 'duration',
        description: 'Flight duration, in minutes',
        type: 'integer',
        example: 180,
    )]
    public int $duration = 0;

    /**
     * @var string|null Equipment IATA code
     */
    #[OA\Property(
        property: 'equipment',
        description: 'Equipment IATA code',
        type: 'string',
        example: '333',
        nullable: true,
    )]
    public string|null $equipment = null;

    /**
     * @var string|null Fare basis
     */
    #[OA\Property(
        property: 'fareBasis',
        description: 'Fare basis',
        type: 'string',
        example: 'SOWEU',
        nullable: true,
    )]
    public string|null $fareBasis = null;


    /**
     * @var string Flight number
     */
    #[OA\Property(
        property: 'flightNumber',
        description: 'Flight number',
        type: 'string',
        example: '370'
    )]
    public string $flightNumber;

    /**
     * @var string Segment ID
     */
    public string $id = '';

    /**
     *  @var string|null Validity start date
     */
    #[OA\Property(
        property: 'notValidBefore',
        description: 'Validity start date',
        type: 'date',
        example: '2025-11-30',
        nullable: true,
    )]
    public string|null $notValidBefore = null;

    /**
     *  @var string|null Validity end date
     */
    #[OA\Property(
        property: 'notValidAfter',
        description: 'Validity end date',
        type: 'date',
        example: '2025-11-30',
        nullable: true,
    )]
    public string|null $notValidAfter = null;


    /**
     * @var string Departure airport IATA code
     */
    #[OA\Property(
        property: 'origin',
        description: 'Departure airport IATA code',
        type: 'string',
        example: 'LON'
    )]
    public string $origin;

    public string $originName;

    /**
     * @var string Operation carrier IATA code
     */
    #[OA\Property(
        property: 'operatingCarrier',
        description: 'Operation carrier IATA code',
        type: 'string',
        example: 'BA',
        nullable: true
    )]
    public string $operatingCarrier;

    /**
     * @var integer Seats available in class
     */
    #[OA\Property(
        property: 'seatsAvailable',
        description: 'Available seats in class',
        type: 'integer',
        example: '9',
        nullable: true
    )]
    public int $seatsAvailable;

    /**
     * @var string Segment status
     */
    #[OA\Property(
        property: 'status',
        description: 'Segment status',
        type: 'string',
        example: 'HK',
        nullable: true
    )]
    public string $status;

    public array $stops = [];


    public string $brandID = '';

    public string $pnr = '';

    /**
     * @var int|null Leg number
     */
    #[OA\Property(
        property: 'legNumber',
        description: 'Leg number',
        type: 'int',
        example: '1',
        nullable: true
    )]
    public int|null $legNumber = null;

    /**
     * Method compares two segments details
     * @param AirSegment $segment Segment, which must be compared with `this` segment
     * @param bool $compareDate Indicates if date should also be compared
     * @return bool
     */
    public function compare(AirSegment $segment, bool $compareDate = false): bool
    {
        return $this->origin == $segment->origin
            && $this->destination == $segment->destination
            && $this->flightNumber == $segment->flightNumber
            && $this->carrier == $segment->carrier
            && (!$compareDate || $this->departure == $segment->departure);
    }

    public function addBaggage(string $baggage): void
    {
        if (empty($this->baggage)) {
            $this->baggage = $baggage;
        } else {
            $currentBaggage = $this->parseBaggage($this->baggage);
            if (!empty($currentBaggage)) {
                if (empty($currentBaggage['value'])) {
                    $this->baggage = $baggage;
                } else {
                    $newBaggage = $this->parseBaggage($baggage);
                    if (!empty($newBaggage['value']) && $currentBaggage['units'] == $newBaggage['units']) {
                        $this->baggage = ($currentBaggage['value'] + $newBaggage['value']) . $currentBaggage['units'];
                    } elseif ('KG' == $currentBaggage['units'] && $currentBaggage['value'] >= 20 && $currentBaggage['value'] <= 23) {
                        $this->baggage = ($newBaggage['value'] + 1) . $newBaggage['units'];
                    } elseif ('KG' == $newBaggage['units'] && $newBaggage['value'] >= 20 && $newBaggage['value'] <= 23) {
                        $this->baggage = ($currentBaggage['value'] + 1) . $currentBaggage['units'];
                    }
                }
            }
        }
    }

    private function parseBaggage(string $baggage): array
    {
        $data = [];
        $baggagePattern = '/^(\d{1,2})(KG|PC)$/iu';
        if (preg_match($baggagePattern, $baggage, $matches)) {
            $data = [
                'value' => $matches[1],
                'units' => $matches[2],
            ];
        }

        return $data;
    }

    /**
     * Retrieves a description of the flight, including origin, destination, carrier, and flight number.
     *
     * @return string The flight description in the format "origin-destination carrier-flightNumber".
     */
    public function getFlightDescription(): string
    {
        return $this->origin . '-' . $this->destination . ' ' . $this->carrier . '-' . $this->flightNumber;
    }

//    public function setBrandInfo(BaseFareFamily $family, int $segmentNumber): void
//    {
//        $this->brandID = $family->id;
//        if (!$family instanceof \app\Atom\Providers\Hitit\Dto\BrandedFare) {
//            $this->fareBasis = $family->getFareBasis();
//            $this->bookingClass = $family->getSegmentBookingClass($segmentNumber);
//        }
//        if (!empty($family->serviceClasses[$segmentNumber])) {
//            $this->serviceClass = $family->getSegmentServiceClass($segmentNumber);
//        }
//        if (($family instanceof \app\Atom\Providers\Hitit\Dto\BrandedFare)
//            && ($this instanceof \app\Atom\Providers\Hitit\Dto\FlightSegment)) {
//            if ($family->fareType != $this->fareType) {
//                $this->setFareInfo($this->providerObject->additionalFareInfo);
//            } else {
//                $this->setFareInfo($this->providerObject->fareInfo);
//            }
//            if (!empty($this->providerObject->fareInfo->farePkgInfoList)) {
//                foreach ($this->providerObject->fareInfo->farePkgInfoList as &$fpi) {
//                    $packageCategory = strtoupper($this->fareType) . ' ' . $fpi->pkgCatagory;
//                    if ($family->id == $packageCategory) {
//                        $fpi->selected = true;
//                    } else {
//                        $fpi->selected = false;
//                    }
//                }
//            }
//        }
//    }
}