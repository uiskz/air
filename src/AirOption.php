<?php
declare(strict_types=1);

namespace Uis\Air;

use OpenApi\Attributes as OA;

/**
 * Base Air Option containing flight information and prices available for booking
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
#[OA\Schema(
    schema: 'AirOption',
    description: 'Air offer with flights and prices details available for booking',
    required: ['id', 'price', 'currency'],
    type: 'object',
)]
class AirOption
{
    /**
     * @var string
     */
    #[OA\Property(
        property: 'id',
        description: 'Unique option ID',
        type: 'string',
        example: '1234567890'
    )]
    public string $id = '';

    /**
     * @var float
     */
    public float $fare = 0.0;

    /**
     * @var float
     */
    public float $taxes = 0.0;

    /**
     * @var string
     */
    #[OA\Property(
        property: 'currency',
        description: 'Currency code of the current option',
        type: 'string',
        example: 'USD'
    )]
    public string $currency = 'KZT';

    protected float $currencyRate = 1.0;

    /**
     * @OA\Property(
     *     property="price",
     *     type="number",
     *     format = "float",
     *     description="Total option price for all passengers",
     *     example=150.15
     * )
     *
     * @var float
     */
    #[OA\Property(
        property: 'price',
        description: 'Total option price for all passengers',
        type: 'number',
        format: 'float',
        example: 150.15
    )]
    public float $price = 0.0;

    /**
     *
     * @var float Consolidator markup of the option
     */
    public float $markup = 0.0;

    /**
     *
     * @var float Airline fee of the option
     */
    public float $airlineFee = 0.0;

    /**
     *
     * @var float Agent markup of the option
     */
    public float $agentMarkup = 0.0;

    /**
     * @var float Agent discount
     */
    public float $discount = 0;

    /**
     * @var float Consolidator serviceFee
     */
    public float $consolidatorServiceFee = 0;

    /**
     * @var float Consolidator markup
     */
    public float $consolidatorMarkup = 0;


    /**
     * @var float Agency markup
     */
    public float $agencyMarkup = 0.0;

    /**
     * @var AirTrip[]
     */
    #[OA\Property(
        property: 'trips',
        description: 'Option flight legs',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/AirTrip')
    )]
    public array $trips = [];

    /**
     * @var string
     */
    #[OA\Property(
        property: 'carrier',
        description: 'Validating carrier code',
        type: 'string',
        example: 'LH'
    )]
    public string $carrier;

    public array $carriers = [];

    public string|null $class = null;

    public string|null $fareBasis = null;

    public int $maxStops = 0;

    public string|null $familyID = null;

    public array $rules = [];

    public string|null $provider;

    public int|null $providerID;

    public string|null $terminal;

    /**
     * @var boolean
     */
    public bool $isRefundable = false;

    public bool $isChangeable = false;

    /**
     *
     * @var boolean
     */
    public bool $hasBaggage = false;

    /**
     *
     * @var boolean
     */
    public bool $hasPackages = false;

    /**
     *  @var $packages BaseFareFamilies
     */
    #[OA\Property(
        property: 'packages',
        ref: '#/components/schemas/BrandsData',
        description: 'Brands for the current option',
        type: 'object'
    )]
    public BaseFareFamilies $packages;

    /**
     * @var string
     */
    public string $flightsString = '';

    /**
     *
     * @var AncillaryServiceDTO[]
     */
    public array $extraCharges = [];

    /**
     *
     * @var array
     */
    public array $policyViolations = [];

    /**
     *
     * @var float
     */
    public float $minPrice = 0.0;

    /**
     * @var float
     */
    public float $maxPrice = 0.0;


    /**
     * @var boolean
     */
    public bool $isForbidden = false;

    /**
     * @var string Segments hash string used in AWS to compare that options are for the same flight
     */
    public string $segmentsHash = '';

    /**
     * @var array Array of fare basis codes in relation to segments
     */
    public array $fareBasisCodes = [];

    /**
     * @var string Agent fare family name. Only used in AWS when fare families used
     */
    public string $agentFareFamilyName = '';

    /**
     * @var mixed Provider custom data required for booking
     */
    public mixed $providerData;

    public bool $markupEnabled = true;

    public string $mode = Provider::MODE_LIVE;

    /* @var BasePassengerInfo[] */
    public array $passengerInfos = [];

    private array $serviceFeeInfos = [];

    private array $discountInfos = [];

    private array $markupInfos = [];

    private array $appliedActions = [];

    public string $searchType = AirSearchRQ::SEARCH_TYPE_ONE_WAY;

    public function __construct()
    {
        $this->generateNewId();
        $this->packages = new BaseFareFamilies();
    }

    public function __clone()
    {
        $trips = [];
        foreach ($this->trips as $trip) {
            $trips[] = clone $trip;
        }
        $this->trips = $trips;
        if (!empty($this->packages)) {
            $this->packages = clone $this->packages;
        }
    }

    public function generateNewId(): void
    {
        $this->id = Uuid::uuid4()->toString();
    }

    /**
     * Function adds new trip to current option
     * @param AirTrip $trip
     * @param int|null $key Index for the new leg
     */
    public function addTrip(AirTrip $trip, int $key = null): void
    {
        if ($this->maxStops < $trip->numberOfStops) {
            $this->maxStops = $trip->numberOfStops;
        }

        foreach ($trip->segments as $segment)
        {
            if (!in_array($segment->carrier, $this->carriers)) {
                $this->carriers[] = $segment->carrier;
            }
        }

//        foreach ($trip->segments as $segment) {
//            if (in_array($segment->baggage, ['0KG', '1KG', '0PC'])
//                || false !== mb_strpos(mb_strtolower($segment->fareBasis), 'sf')) {
//                $this->hasPackages = true;
//            }
//            if (!empty($this->flightsString)) {
//                $this->flightsString .= ',';
//            }
//            $this->flightsString .= $segment->carrier . '_' . $segment->flightNumber
//                . '_' . $segment->departure->format('Ymd');
//            if (!empty($segment->bookingClass)) {
//                $this->flightsString .= '_' . $segment->bookingClass;
//            }
//        }

        if (empty($key)) {
            $this->trips[] = $trip;
        } else {
            $this->trips[$key] = $trip;
        }
    }

    /**
     * Function returns total flight duration time in minutes
     */
    public function getDuration(): int
    {
        $duration = 0;
        foreach ($this->trips as $trip) {
            $duration += $trip->duration;
        }

        return $duration;
    }

    /**
     * Function returns maximum leg duration time in minutes
     */
    public function getMaxDuration($hours = FALSE)
    {
        $duration = 0;
        foreach ($this->legs as $leg) {
            if ( $duration < $leg->duration ) {
                $duration = $leg->duration;
            }
        }
        return ($hours ? $duration / 60 : $duration);

    }

    /**
     * Function returns last date of travel
     */
    public function getLastTravelDate()
    {
        $ltd = new \DateTime();

        if ( count($this->legs) ) {
            $lastLeg = $this->legs[count($this->legs) - 1];

            if ( count($lastLeg->segments) ) {
                $lastSeg = $lastLeg->segments[count($lastLeg->segments) - 1];
                $ltd = $lastSeg->departure;
            }
        }

        if ( get_class($ltd) == 'DateTime' ) {
            $ltd = \DateTime::createFromFormat('Y-m-d H:i:s', $ltd->format('Y-m-d') . ' 00:00:00');
        }

        return $ltd;
    }

    /**
     * Function checks if the option is completely performed by one carrier
     * @param $carrier string Carrier code to be checked
     * @return bool
     */
    public function checkCarrier(string $carrier): bool
    {
        if ($this->carrier != $carrier) {
            return false;
        }

        foreach ($this->legs as $leg) {
            foreach ($leg->segments as $seg) {
                if ($seg->carrier != $carrier) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isBaggageIncluded()
    {
        $this->baggageIncluded = true;

        foreach ($this->legs as $leg) {
            foreach ($leg->segments as $segment) {
                if (empty($segment->baggage) || in_array($segment->baggage, ['0KG', '0PC'])) {
                    $this->baggageIncluded = false;
                }
            }
        }

        return $this->baggageIncluded;
    }

    public function GetFareFamily($familyID)
    {
        $res = null;
        foreach ($this->packages->families as $family) {
            if ($family->id == $familyID) {
                $res = $family;
                break;
            }
        }

        return $res;
    }

    public function setBrandsID(string $brandsID, AirSearchRQ $searchRQ = null): void
    {
        $this->familyID = $brandsID;
        $brandParts = explode(';', $brandsID);

        $oldPrice = $this->price;
        $this->price = 0;
        foreach ($this->packages->families as $tripIndex => $tripFamily) {
            $brandID = $brandParts[$tripIndex];
            foreach ($tripFamily as &$family) {
                if ($family->id === $brandID) {
                    $family->setSelected(true);
                    $this->price += $family->price;
                    if ($family->tripRef != -1) {
                        $trip = $this->trips[$family->tripRef - 1];
                        $trip->setBrandInfo($family);
                    }
                } else {
                    $family->setSelected(false);
                }
            }
        }

        if (!empty( $this->packages->combinations)) {
            foreach ($this->packages->combinations as &$combination) {
                $familyIDs = $combination->getFamilyIDs();
                if (empty(array_diff($familyIDs, $brandParts))) {
                    $combination->setSelected(true);
                    $this->setPrice($combination, $searchRQ);
                    break;
                }
            }
        }

        if (empty($this->price) && !empty($oldPrice)) {
            $this->price = $oldPrice;
        }

        \Yii::debug(print_r($this, true));
    }

    public function getAirportCodes(): array
    {
        $codes = [];
        foreach ($this->trips as $trip) {
            foreach ($trip->segments as $segment) {
                if (!in_array($segment->origin, $codes)) {
                    $codes[] = $segment->origin;
                }
                if (!in_array($segment->destination, $codes)) {
                    $codes[] = $segment->destination;
                }
            }
        }

        return $codes;
    }

    public function setServiceFee(string $passengerType, int $qty): void
    {
        $grandTotalServiceFee = 0;
        $grandTotalMarkupFee = 0;
        $grandTotalDiscount = 0;

        $serviceFee = $this->getServiceFeeAmount($passengerType);
        if ($serviceFee > 0) {
            foreach ($this->passengerInfos as &$passengerInfo) {
                if ($passengerInfo->passengerType === $passengerType && $passengerInfo->match) {
                    $passengerInfo->serviceFee = $serviceFee;
                    $passengerInfo->total += $serviceFee;

                    $totalServiceFee = $serviceFee * $qty;
                    $grandTotalServiceFee += $totalServiceFee;
                    \Yii::info('Сбор на 1 ' . $passengerType . ' ' . $serviceFee . ', количество ' . $qty
                        . ', сумма за всех ' . $totalServiceFee, __METHOD__);
                    $this->consolidatorServiceFee += $totalServiceFee;
                    $this->price += $totalServiceFee;
                    break;
                }
            }
        }

        $markup = $this->getMarkupAmount($passengerType);
        if ($markup > 0) {
            foreach ($this->passengerInfos as &$passengerInfo) {
                /** @var BasePassengerInfo $passengerInfo */
                if ($passengerInfo->passengerType === $passengerType && $passengerInfo->match) {
                    $tax = new BaseTax();
                    $tax->code = 'AD';
                    $tax->amount = $markup;
                    $tax->currency = $this->currency;
                    $passengerInfo->addTax($tax);
                    $passengerInfo->total += $markup;

                    $totalMarkup = $markup * $qty;
                    $grandTotalMarkupFee += $totalMarkup;
                    \Yii::info('Маркап на 1 ' . $passengerType . ' ' . $markup . ', количество ' . $qty
                        . ', сумма за всех ' . $totalMarkup, __METHOD__);
                    $this->consolidatorMarkup += $totalMarkup;
                    $this->price += $totalMarkup;
                    break;
                }
            }
        }

        $discount = $this->getDiscountAmount($passengerType);
        if ($discount > 0) {
            foreach ($this->passengerInfos as &$passengerInfo) {
                /** @var BasePassengerInfo $passengerInfo */
                if ($passengerInfo->passengerType === $passengerType && $passengerInfo->match) {
                    $passengerInfo->discount += $discount;

                    $totalDiscount = $discount * $qty;
                    $grandTotalDiscount += $totalDiscount;
                    \Yii::info('Скидка на 1 ' . $passengerType . ' ' . $discount . ', количество ' . $qty
                        . ', сумма за всех ' . $totalDiscount, __METHOD__);
                    $this->discount += $totalDiscount;
                    break;
                }
            }
            unset($passengerInfo);
        }

        if ((!empty($grandTotalServiceFee) || !empty($grandTotalMarkupFee) || !empty($grandTotalDiscount))) {

            if (empty($this->packages->combinations)) {
                if (!empty($this->packages) && !empty($this->packages->families)) {
                    foreach ($this->packages->families[0] as &$tripFamily) {
                        $tripFamily->price += $grandTotalServiceFee + $grandTotalMarkupFee - $grandTotalDiscount;
                    }
                }
            } else {
                foreach ($this->packages->combinations as &$combination) {
                    /* @var BaseFareFamilyCombination $combination */
                    $combination->price += $grandTotalServiceFee + $grandTotalMarkupFee;

                    if ($combination->hasDiscounts()) {
                        $discount = $combination->getDiscountAmount($passengerType);
                        if ( $discount > 0 ) {
                            foreach ($combination->passengerInfos as &$passengerInfo) {
                                /** @var BasePassengerInfo $passengerInfo */
                                if ($passengerInfo->passengerType === $passengerType && $passengerInfo->match) {
                                    $passengerInfo->discount += $discount;
                                    $passengerInfo->total -= $discount;

                                    $totalDiscount = $discount * $qty;
                                    \Yii::info('Скидка на 1 ' . $passengerType . ' ' . $discount . ', количество ' . $qty
                                        . ', сумма за всех ' . $totalDiscount, __METHOD__);
                                    $combination->price -= $totalDiscount;
                                    break;
                                }
                            }
                            unset($passengerInfo);
                        }
                    } else {
                        $combination->price -= $grandTotalDiscount;
                    }
                }
            }


        }

    }

    private function getServiceFeeAmount(string $passengerType): float
    {
        $serviceFee = 0;
        if (isset($this->serviceFeeInfos[$passengerType])) {
            $serviceFee = $this->serviceFeeInfos[$passengerType];
        } elseif (isset($this->serviceFeeInfos['ANY'])) {
            $serviceFee = $this->serviceFeeInfos['ANY'];
        }

        return $serviceFee;
    }

    private function getMarkupAmount(string $passengerType): float
    {
        $markup = 0;
        if (isset($this->markupInfos[$passengerType])) {
            $markup = $this->markupInfos[$passengerType];
        } elseif (isset($this->markupInfos['ANY'])) {
            $markup = $this->markupInfos['ANY'];
        }

        return $markup;
    }

    private function getDiscountAmount(string $passengerType): float
    {
        $discount = 0;
        if (isset($this->discountInfos[$passengerType])) {
            $discount = $this->discountInfos[$passengerType];
        } elseif (isset($this->discountInfos['ANY'])) {
            $discount = $this->discountInfos['ANY'];
        }

        return $discount;
    }

    public function clearProvider(): void
    {
        $this->provider = null;
    }

    public function clearPriceDetails(): void
    {
        $this->passengerInfos = [];
    }

    public function clearTaxDetails(): void
    {
        foreach ($this->passengerInfos as &$passengerInfo) {
            $passengerInfo->taxes = [];
        }
    }

    public function getPrice(): float
    {
        $price = $this->price;
        if (!empty($this->packages)) {
            if (!empty($this->packages->combinations)) {
                foreach ($this->packages->combinations as $combination) {
                    if ($combination->isSelected()) {
                        $price = $combination->price;
                        break;
                    }
                }
            } elseif (!empty($this->packages->families)) {
                $price = 0;
                foreach ($this->packages->families as $families) {
                    foreach ($families as $family) {
                        if ($family->isSelected()) {
                            $price += $family->price;
                        }
                    }
                }
            }
        }

        \Yii::info('Итоговая цена: ' . $price, __METHOD__);

        return $price;
    }

    /**
     * Checks if the given action has been already applied to the current option.
     *
     * @param string $action The action to check against the list of applied actions.
     * @return bool Returns true if the action is found in the list of applied actions, otherwise false.
     */
    public function actionApplied(string $action): bool
    {
        return in_array($action, $this->appliedActions);
    }

    /**
     * Adds or updates service fee information for the specified passenger type.
     *
     * @param string $passengerType The type of service fee to add or update.
     * @param float $amount The amount associated with the specified service fee type.
     * @return void
     */
    public function setServiceFeeInfo(string $passengerType, float $amount): void
    {
        $this->serviceFeeInfos[$passengerType] = $amount;
    }

    /**
     * Adds or updates service fee information for the specified passenger type.
     *
     * @param string $passengerType The type of service fee to add or update.
     * @param float $amount The amount associated with the specified service fee type.
     * @return void
     */
    public function setDiscountInfo(string $passengerType, float $amount): void
    {
        $this->discountInfos[$passengerType] = $amount;
    }

    /**
     * Adds or updates markup information for the specified passenger type.
     *
     * @param string $passengerType The type of service fee to add or update.
     * @param float $amount The amount associated with the specified service fee type.
     * @return void
     */
    public function setMarkupInfo(string $passengerType, float $amount): void
    {
        $this->markupInfos[$passengerType] = $amount;
    }

    public function clearAppliedActions(): void
    {
        $this->appliedActions = [];
        if (!empty($this->packages->combinations)) {
            foreach ($this->packages->combinations as &$combination) {
                $combination->clearAppliedActions();
            }
        }
    }

    public function hasServiceFees(): bool
    {
        return !empty($this->serviceFeeInfos);
    }

    public function hasMarkups(): bool
    {
        return !empty($this->markupInfos);
    }

    public function hasDiscounts(): bool
    {
        return !empty($this->discountInfos);
    }

    public function clearServiceFeesInfos(): void
    {
        $this->markupInfos = [];
        $this->serviceFeeInfos = [];
        $this->discountInfos = [];

        if (!empty($this->packages->combinations)) {
            foreach ($this->packages->combinations as &$combination) {
                $combination->clearServiceFeesInfos();
            }
        }
    }

    public function getServiceFeeInfos(): array
    {
        return $this->serviceFeeInfos;
    }

    public function getServiceMarkupInfos(): array
    {
        return $this->markupInfos;
    }

    public function getDiscounts(): array
    {
        return $this->discountInfos;
    }

    public function addAppliedAction(string $action): void
    {
        $this->appliedActions[] = $action;
    }

    public function setTripFareFamily(int $tripIndex, BaseFareFamily $family, BaseFareFamilyCombination $combination): void
    {
        $trip = $this->trips[$tripIndex];
        $trip->brandID = $family->id;
        $trip->brandName = $family->name;
        $trip->brandServices = $family->services;
        foreach ($trip->brandServices as $service) {
            if (BaseFareFamilyService::TYPE_BAGGAGE == $service->type) {
                if (BaseFareFamilyService::PAYMENT_INCLUDED == $service->paymentType) {
                    $this->hasBaggage = true;
                } else {
                    $this->hasBaggage = false;
                }

            }
        }

        $bookingClasses = $combination->getBookingClasses();
        foreach ($bookingClasses as $bookingClass) {
            if ($tripIndex == ($bookingClass['tripRef'] - 1) && !empty($trip->segments[$bookingClass['segmentRef'] - 1])) {
                $trip->segments[$bookingClass['segmentRef'] - 1]->bookingClass = $bookingClass['class'];
            }
        }

        $fareBasisCodes = $combination->getFareBasisCodes();
        foreach ($fareBasisCodes as $fareBasisCode) {
            if ($tripIndex == ($fareBasisCode['tripRef'] - 1) && !empty($trip->segments[$fareBasisCode['segmentRef'] - 1])) {
                $trip->segments[$fareBasisCode['segmentRef'] - 1]->fareBasis = $fareBasisCode['fareBasis'];
            }
        }

        $baggageInfos = $combination->getBaggageInfos();
        foreach ($baggageInfos as $baggageInfo) {
            if ($tripIndex == ($baggageInfo['tripRef'] - 1) && !empty($trip->segments[$baggageInfo['segmentRef'] - 1])) {
                $trip->segments[$baggageInfo['segmentRef'] - 1]->baggage = $baggageInfo['allowance'];
            }
        }

        if ($combination instanceof \app\Atom\Providers\MixVel\Dto\FareFamilyCombination) {
            $this->providerData = $combination->getProviderData();
        }

        $this->trips[$tripIndex] = $trip;
    }

    public function setPrice(BaseFareFamilyCombination $combination, AirSearchRQ $searchRQ = null): void
    {
        if (!empty($combination->passengerInfos)) {
            $this->passengerInfos = $combination->passengerInfos;
            $this->fare = 0;
            $this->taxes = 0;
            $this->price = 0;
            foreach ($this->passengerInfos as &$passengerInfo) {
                /* @var BasePassengerInfo $passengerInfo */
                if (!empty($searchRQ)) {
                    if ('ADT' == $passengerInfo->passengerType) {
                        $passengerInfo->passengerQty = $searchRQ->adults;
                    } elseif ('CHD' == $passengerInfo->passengerType) {
                        $passengerInfo->passengerQty = $searchRQ->children;
                    } elseif ('INF' == $passengerInfo->passengerType) {
                        $passengerInfo->passengerQty = $searchRQ->infants;
                    }
                }
                $this->fare += $passengerInfo->fare * $passengerInfo->passengerQty;
                $this->taxes += $passengerInfo->totalTaxes * $passengerInfo->passengerQty;
                $this->price += $passengerInfo->total * $passengerInfo->passengerQty;
            }
        }
    }

    public function convertCurrency(string $code, float $rate): void
    {
        $this->currencyRate = $rate;
        $fare = 0;
        $taxes = 0;
        foreach ($this->passengerInfos as &$passengerInfo) {
            $passengerInfo->convertCurrency($code, $rate);
            $fare += $passengerInfo->fare * $passengerInfo->passengerQty;
            $taxes += $passengerInfo->totalTaxes * $passengerInfo->passengerQty;
        }
        $this->fare = $fare;
        $this->taxes = $taxes;
        $this->price = $this->fare + $this->taxes;
    }

    /**
     * Method cleans price from Service Fee and Markup.
     * @return void
     */
    public function cleanPrice(): void
    {
        if (!empty($this->consolidatorServiceFee)) {
            $this->consolidatorServiceFee = 0;
        }
        if (!empty($this->consolidatorMarkup)) {
            $this->consolidatorMarkup = 0;
        }
        if (!empty($this->discount)) {
            $this->discount = 0;
        }
        $this->price = 0;
        foreach ($this->passengerInfos as &$passengerInfo) {
            $passengerInfo->cleanPrice();
            $this->price += $passengerInfo->total * $passengerInfo->passengerQty;
        }
        if (!empty($this->packages) && !empty($this->packages->combinations)) {
            foreach ($this->packages->combinations as $combination) {
                $combination->cleanPrice();
            }
        }
    }

    public function addPassengerInfos(array $fareInfos): void
    {
        foreach ($fareInfos as $fareInfo) {
            /* @var $fareInfo BasePassengerInfo */
            $this->addPassengerInfo($fareInfo);
        }
    }

    private function addPassengerInfo(BasePassengerInfo $fareInfo): void
    {
        $found = false;
        foreach ($this->passengerInfos as &$passengerInfo) {
            if ($passengerInfo->passengerType == $fareInfo->passengerType) {
                $passengerInfo->merge($fareInfo);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $this->passengerInfos[] = clone $fareInfo;
        }
    }
}