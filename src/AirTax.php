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
    /** Markup of the root consolidator */
    const string CONSOLIDATOR_MARKUP = 'AD';

    /** Viewer's own agency markup, shown as a tax without the markups module */
    const string AGENT_MARKUP = 'SD';

    /** Service fees the viewer does not see as fees: of the agencies above its parent (the root
     * consolidator's included), and its parent's when the viewer has no service fees module */
    const string HIDDEN_SERVICE_FEE = 'AW';

    /** Markups of the agencies between the root consolidator and the viewer */
    const string AGENCY_MARKUP = 'S2';

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