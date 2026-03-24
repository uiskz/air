<?php
declare(strict_types=1);

namespace Uiskz\Air;

use Uiskz\Travel\CreateReservationParams;
use Uiskz\Travel\ProviderInterface;
use Uiskz\Travel\Reservation;

interface AirProviderInterface extends ProviderInterface {

    /**
     * Searches for flights based on the given request criteria.
     *
     * @param AirSearchRequest $request The request object containing search criteria such as departure, destination, and travel dates.
     * @return AirSearchResponse The response object containing the search results including available flights.
     */
    public function searchFlights(AirSearchRequest $request): AirSearchResponse;

    /**
     * Creates a reservation for the specified flight.
     *
     * @param CreateReservationParams $parms The parameters for creating the reservation.
     * @return AirReservation The reservation object representing the created reservation.
     */
    public function createReservation(CreateReservationParams $parms): AirReservation;
}