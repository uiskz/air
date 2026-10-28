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
class AirTax
{
    /** Markup of the viewer's direct agency (the consolidator's own markup for the consolidator) */
    const string CONSOLIDATOR_MARKUP = 'AD';

    /** Viewer's own agency markup, shown as a tax without the markups module */
    const string AGENT_MARKUP = 'SD';

    /** Markups of the agencies above the viewer's direct agency */
    const string UPPER_MARKUP = 'AW';

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

    #[OA\Property(
        property: 'providerAmount',
        description: 'Tax amount in provider currency',
        type: 'double',
        example: 20.00
    )]
    public float $providerAmount = 0.0;
}