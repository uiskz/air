<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BrandsCombination',
    required: ['price', 'familyIDs'],
    properties: [
        new OA\Property(
            property: 'price',
            description: 'Price of the combination, in addition to base fare.',
            type: 'number',
            example: 10.75
        ),
        new OA\Property(
            property: 'familyIDs',
            description: 'Fare families IDs in this combination.',
            type: 'array',
            items: new OA\Items(type: 'string', example: 'LIGHT')
        )
    ],
    type: 'object'
)]
class AirFareFamilyCombination
{
    public string $id = '';

    public bool $isDefault = false;

    public float $price = 0;

    /**
     * @var AirFareFamily[] Fare families on this trip
     */
    protected array $families = [];

    protected array $fareBasisCodes = [];

    protected array $baggageInfos = [];

    protected array $bookingClasses = [];

    public ?\DateTimeInterface $lastTicketingDate = null;

    /**
     * @var BasePassengerInfo[]
     */
    public array $passengerInfos = [];

    protected bool $selected = false;

    private array $appliedActions = [];

    private array $discountInfos = [];

    public function addFamily(AirFareFamily $family): void
    {
        $this->isDefault = $family->isDefault;
        foreach ($this->families as $existingFamily) {
            if ($existingFamily->id == $family->id && $existingFamily->tripRef == $family->tripRef) {
                return;
            }
            if (!empty($existingFamily->code) && !empty($family->code) && $existingFamily->code == $family->code
                && $existingFamily->tripRef == $family->tripRef) {
                return;
            }
        }
        $this->families[] = $family;

        $this->generateId();
    }

    public function getFamilyIDs(): array
    {
        $familyIDs = [];
        foreach ($this->families as $family) {
            $familyIDs[] = $family->id;
        }

        return $familyIDs;
    }

    protected function generateId(): void
    {
        $familyIDs = $this->getFamilyIDs();
        $base58 = new Base58();
        $this->id = $base58->encode(implode(';', $familyIDs));
    }

    public function getFamilies(): array
    {
        foreach ($this->families as &$family) {
            if ($family->emptyBookingClasses()) {
                foreach ($this->bookingClasses as $bookingClass) {
                    if ($bookingClass['tripRef'] == $family->tripRef) {
                        $family->addBookingClass($bookingClass['segmentRef'], $bookingClass['class']);
                    }
                }
            }
        }
        return $this->families;
    }

    public function hasFamilies(): bool
    {
        return count($this->families) > 0;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'isDefault' => $this->isDefault,
            'price' => $this->price,
            'familyIDs' => $this->getFamilyIDs(),
            'passengerInfos' => $this->passengerInfos,
        ];
    }

    public function getBookingClasses(): array
    {
        return $this->bookingClasses;
    }

    public function getFareBasisCodes(): array
    {
        return $this->fareBasisCodes;
    }

    public function getBaggageInfos(): array
    {
        return $this->baggageInfos;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function getRulesText(int $tripIndex, int $segmentIndex): string
    {
        foreach ($this->families as $family) {
            if ($family->tripRef == ($tripIndex + 1) && $family->hasSegmentRef($segmentIndex + 1)) {
                return $family->getRulesText($segmentIndex + 1);
            }
        }

        return '';
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

    public function addAppliedAction(string $action): void
    {
        $this->appliedActions[] = $action;
    }

    public function clearAppliedActions(): void
    {
        $this->appliedActions = [];
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

    public function getDiscounts(): array
    {
        return $this->discountInfos;
    }

    public function hasDiscounts(): bool
    {
        return !empty($this->discountInfos);
    }

    public function getDiscountAmount(string $passengerType): float
    {
        $discount = 0;
        if (isset($this->discountInfos[$passengerType])) {
            $discount = $this->discountInfos[$passengerType];
        } elseif (isset($this->discountInfos['ANY'])) {
            $discount = $this->discountInfos['ANY'];
        }

        return $discount;
    }

    public function clearServiceFeesInfos(): void
    {
//        $this->markupInfos = [];
//        $this->serviceFeeInfos = [];
        $this->discountInfos = [];
    }

    public function addPassengerInfos(array $passengerInfos): void
    {
        foreach ($passengerInfos as $passengerInfo) {
            $this->passengerInfos[] = clone $passengerInfo;
        }
    }

    public function cleanPrice(): void
    {
        foreach ($this->passengerInfos as &$passengerInfo) {
            $passengerInfo->cleanPrice();
        }
    }
}