<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * Class AirFareFamilyService
 */
#[OA\Schema(
    schema: 'BrandServiceData',
    description: 'Brand details',
    required: ['id', 'name'],
    type: 'object',
    examples: [
        new OA\Examples(
            summary: 'Example of seat service description',
            value: [
                'description' => 'Basic seat',
                'paymentType' => AirFareFamilyService::PAYMENT_INCLUDED,
            ],
        ),
        new OA\Examples(
            summary: 'Example of exchange service description',
            value: [
                'description' => 'Changes',
                'paymentType' => AirFareFamilyService::PAYMENT_CHARGEABLE,
            ]
        ),
        new OA\Examples(
            summary: 'Example of refundability service description',
            value: [
                'description' => 'Refund',
                'paymentType' => AirFareFamilyService::PAYMENT_NOT_AVAILABLE,
            ]
        )
    ]
)]
class AirFareFamilyService
{
    const string TYPE_BAGGAGE = 'baggage';

    const string TYPE_CARY_ON_BAGGAGE = 'carry';

    const string TYPE_MEAL = 'meal';

    const string TYPE_REFUND = 'refund';

    const string PAYMENT_INCLUDED = 'included';

    const string PAYMENT_CHARGEABLE = 'chargeable';

    const string PAYMENT_NOT_AVAILABLE = 'na';

    /**
     * @OA\Property(
     *   property="type",
     *   type="string",
     *   description="Service type code"
     * )
     *
     * @var string
     */
    public string $type = '';

    /**
     * @var string
     */
    #[OA\Property(
        property: 'description',
        description: 'Service description returned by the provider.',
        type: 'string',
        example: 'BASIC SEAT'
    )]
    public string $description = '';

    /**
     * @var string
     */
    #[OA\Property(
        property: 'paymentType',
        description: <<<DESC
        Payment type for this service. Possible values:
          * __included__ - Free service
          * __chargeable__ - Paid service
          * __na__ - Not available in this package
        DESC,
        type: 'string',
        example: 'included'
    )]
    public string $paymentType = '';

    public static function getPaymentTypesList(): array
    {
        return [
            [
                'id' => self::PAYMENT_INCLUDED,
                'label' => 'Включено',
            ],
            [
                'id' => self::PAYMENT_CHARGEABLE,
                'label' => 'Платно',
            ],
            [
                'id' => self::PAYMENT_NOT_AVAILABLE,
                'label' => 'Недоступно',
            ],
        ];
    }
}