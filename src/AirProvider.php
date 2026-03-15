<?php
declare(strict_types=1);

namespace Uis\Air;

use Uiskz\Travel\Provider;

/**
 * Base Air provider
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
class AirProvider extends Provider
{

    /**
     * Method escapes the email address to be used in GDS contacts
     * @param string $email
     * @return string
     */
    public function escapeEmailAddress(string $email): string
    {
        return str_replace(['@', '-', '_'], ['//', './', '..'], $email);
    }


}