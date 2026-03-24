<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * Class AirFareFamily
 */
#[OA\Schema(
    schema: 'BrandData',
    description: 'Brand details',
    required: ['id', 'name'],
    type: 'object',
)]
class AirFareFamily
{
    /**
     * @var string
     */
    #[OA\Property(
        property: 'id',
        description: 'Brand ID returned by the provider.',
        type: 'string',
        example: 'LIGHT',
    )]
    public string $id = '';

    /**
     * @var string
     */
    #[OA\Property(
        property: 'name',
        description: 'Brand name returned by the provider.',
        type: 'string',
        example: 'Economy Light'
    )]
    public string $name = '';

    /**
     * @var bool
     */
    public bool $isDefault = false;

    /**
     * @var bool Indicates if this brand is selected
     */
    public bool $selected = false;

    /**
     * @var float
     */
    public float $price = 0.0;

    public float $adAmount = 0.0;

    public float $consolidatorFee = 0.0;

//    /**
//     * @var string
//     */
//    public string $badge = '';
//
//    /**
//     * @var string
//     */
//    public string $economy = '';

    /**
     * @var AirFareFamilyService[]
     */
    #[OA\Property(
        property: 'services',
        description: 'Services details in current brand.',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/BrandServiceData')
    )]
    public array $services = [];

    #[OA\Property(
        property: 'baggageInfos',
        description: 'Baggage information in this fare family.',
        type: 'array',
        items: new OA\Items(description: 'Baggage allowance per segments', type: 'string', example: '1PC')
    )]
    protected array $baggageInfos = [];

    public int $tripRef = -1;

    protected array $segmentRefs = [];

    protected array $bookingClasses = [];

    protected string $fareBasis = '';

    public function setName(string $name): void
    {
        if (isset($this->fares[$name])) {
            $this->name = $this->fares[$name];
        } else {
            $this->name = $name;
        }
//        if (isset($this->badges[$name])) {
//            $this->badge = $this->badges[$name];
//        }
//        if (isset($this->economies[$name])) {
//            $this->economy = $this->economies[$name];
//        }
    }
//
//    public function fillDetails(string $id, string $carrier, int $providerID): void
//    {
//        $airService = \Yii::$container->get(AirService::class);
//        /* @var $airService AirService */
//
//        $bundle = $airService->getBundleInfo($id, $carrier, $providerID);
//        if (!empty($bundle)) {
//            $this->name = $bundle->description;
//
//            foreach ($bundle->services as $service) {
//                $ffService = new BaseFareFamilyService();
//                $ffService->paymentType = $service->payment_type;
//                $ffService->description = $service->description;
//                $this->AddService($ffService);
//            }
//        }
//    }

    /**
     * Method adds Fare family service to Family
     * @param AirFareFamilyService $service
     */
    public function addService(AirFareFamilyService $service): void
    {
        $this->services[] = $service;
        usort($this->services, static::sortFamilyDescriptions(...));
    }

    public static function sortFamilyDescriptions(AirFareFamilyService $a, AirFareFamilyService $b): int
    {
        if (($a->paymentType === AirFareFamilyService::PAYMENT_NOT_AVAILABLE) && ($b->paymentType !== AirFareFamilyService::PAYMENT_NOT_AVAILABLE)) {
            return 1;
        } elseif (($b->paymentType === AirFareFamilyService::PAYMENT_NOT_AVAILABLE) && ($a->paymentType !== AirFareFamilyService::PAYMENT_NOT_AVAILABLE)) {
            return -1;
        } elseif (AirFareFamilyService::PAYMENT_CHARGEABLE == $a->paymentType && !in_array($b->paymentType, [AirFareFamilyService::PAYMENT_NOT_AVAILABLE, AirFareFamilyService::PAYMENT_CHARGEABLE])) {
            return 1;
        } elseif (AirFareFamilyService::PAYMENT_CHARGEABLE == $b->paymentType && !in_array($a->paymentType, [AirFareFamilyService::PAYMENT_NOT_AVAILABLE, AirFareFamilyService::PAYMENT_CHARGEABLE])) {
            return -1;
        }

        return 0;
    }

    public function hasSegmentRef(int $ref): bool
    {
        return in_array($ref, $this->segmentRefs);
    }

    public function addSegmentRef(int $ref): void
    {
        $this->segmentRefs[] = $ref;
    }

    public function getSegmentRefs(): array
    {
        return $this->segmentRefs;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function getFareBasis(): string
    {
        return $this->fareBasis;
    }

    public function setFareBasis(string $fareBasis): void
    {
        $this->fareBasis = $fareBasis;
    }

    public function getSegmentBookingClass(int $segmentNumber): string
    {
        if (!empty($this->bookingClasses[$segmentNumber])) {
            return $this->bookingClasses[$segmentNumber];
        }

        return '';
    }

    public function emptyBookingClasses(): bool
    {
        return empty($this->bookingClasses);
    }

    public function addBookingClass(int $segmentRef, string $class): void
    {
        $this->bookingClasses[$segmentRef] = $class;
    }
}