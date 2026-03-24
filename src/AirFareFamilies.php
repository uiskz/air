<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * Class BaseFareFamilies
 */
#[OA\Schema(
    schema: 'BrandsData',
    description: 'Brands for the current option',
    required: ['currency', 'baseFare'],
    type: 'object',
)]
class AirFareFamilies
{
    #[OA\Property(
        property: 'currency',
        description: 'Currency code.',
        type: 'string',
        example: 'USD'
    )]
    public string $currency = '';

    /**
     * @var double
     */
    #[OA\Property(
        property: 'baseFare',
        description: 'Base price of the option. All brands are prices should be added to this value.',
        type: 'number',
        format: 'double',
        example: 150.15
    )]
    public float $baseFare = 0.0;

    public array $trips;

    /**
     * @var BaseFareFamilyCombination[]
     */
    #[OA\Property(
        property: 'combinations',
        description: 'Brand combinations with their prices.',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/BrandsCombination')
    )]
    public array $combinations = [];

    /**
     * @var AirFareFamily[][]
     */
    #[OA\Property(
        property: 'families',
        description: 'Brands for the current option. Each trip has its own array of brands.',
        type: 'array',
        items: new OA\Items(ref: '#/components/schemas/BrandData')
    )]
    public array $families = [];

    public function addFamily(AirFareFamily $family): void
    {
        $this->families[] = $family;
    }

    public function addCombination(BaseFareFamilyCombination $combination): void
    {
        $families = $combination->getFamilies();
        foreach ($families as $tripIndex => $family) {
            if (!$this->hasFamily($tripIndex, $family->id)) {
                $this->families[$tripIndex][] = $family;
            }
        }

        $this->combinations[] = clone $combination;
    }

    private function hasFamily(int $tripIndex, string $familyID): bool
    {
        if (!isset($this->families[$tripIndex])) {
            return false;
        }
        foreach ($this->families[$tripIndex] as $family) {
            if ($family->id === $familyID) {
                return true;
            }
        }

        return false;
    }

    /**
     * Method returns fare family by trip index and family ID
     * @param int $tripIndex
     * @param mixed $familyID
     * @return AirFareFamily|null
     */
    public function getFareFamily(int $tripIndex, mixed $familyID): AirFareFamily|null
    {
        foreach ($this->families[$tripIndex] as $family) {
            if ($family->id === $familyID) {
                return $family;
            }
        }
        return null;
    }
}