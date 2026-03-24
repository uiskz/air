<?php
declare(strict_types=1);
namespace Uiskz\Air;

use OpenApi\Attributes as OA;

/**
 * Base Air tax
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
#[OA\Schema(
    schema: 'Tax',
    description: 'Tax details',
)]
class Tax
{
    #[OA\Property(
        property: 'code',
        description: 'Tax code',
        type: 'string',
        example: 'UJ'
    )]
    public string $code;

    #[OA\Property(
        property: 'currency',
        description: 'ISO currency code',
        type: 'string',
        example: 'USD'
    )]
    public string $currency;


    #[OA\Property(
        property: 'amount',
        description: 'Tax amount amount',
        type: 'double',
        example: 20.00
    )]
    public float $amount = 0.0;
}