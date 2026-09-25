<?php
declare(strict_types=1);

namespace Uiskz\Air;

use Uiskz\Travel\CreateReservationParams;
use Uiskz\Travel\ProviderInterface;
use Uiskz\Travel\ReservationIdentity;

/**
 * Air provider interface
 * @author Dmitriy Gritsenko <dg@uis.kz>
 * @package Uiskz\Air
 * @version 1.0.0
 */
interface AirProviderInterface extends ProviderInterface {

    /**
     * Searches for flights based on the given request criteria.
     *
     * @param AirSearchRequest $request The request object containing search criteria such as departure, destination, and travel dates.
     * @return AirSearchResponse The response object containing the search results including available flights.
     */
    public function searchFlights(AirSearchRequest $request): AirSearchResponse;

    /**
     * Get fare families for the given option, request, and response.
     * @param AirOption $option Selected option for fare families retrieval.
     * @param AirSearchRequest $request The request object containing search criteria such as departure, destination, and travel dates.
     * @param AirSearchResponse $response The response object containing the search results including available flights.
     * @return AirOption Options containing fare families as Combination arrays or Fare Family arrays.
     */
    public function getFareFamilies(AirOption $option, AirSearchRequest $request, AirSearchResponse $response): AirOption;

    /**
     * Get fare families for the given option, request, and response.
     * @param AirOption $option Selected option for fare families retrieval.
     * @param AirSearchRequest $request The request object containing search criteria such as departure, destination, and travel dates.
     * @param AirSearchResponse $response The response object containing the search results including available flights.
     * @return AirOption[] An array of fare options for a selected flight.
     */
    public function getFareFamilyOptions(AirOption $option, AirSearchRequest $request, AirSearchResponse $response): array;

    /**
     * Creates a reservation for the specified flight.
     *
     * @param CreateReservationParams $params The parameters for creating the reservation.
     * @return AirReservation The reservation object representing the created reservation.
     */
    public function createReservation(CreateReservationParams $params): AirReservation;

    /**
     * Retrieves an existing reservation by its ID (Locator).
     *
     * @param ReservationIdentity $identity Reservation identifiers I.e. PNR Locator, traveler's Last name, Order ID and etc.
     * @return AirReservation The reservation object representing the created reservation.
     */
    public function retrieveReservation(ReservationIdentity $identity): AirReservation;

    /**
     * Cancel an existing unticketed reservation
     * @param ReservationIdentity $identity Reservation identifiers I.e. PNR Locator, traveler's Last name, Order ID and etc.
     * @return AirReservation Cancelled reservation
     */
    public function cancelReservation(ReservationIdentity $identity): AirReservation;

    /**
     * Issue tickets in the reservation
     * @param AirReservation $reservation Reservation to issue tickets
     * @return AirReservation New reservation with issued tickets
     */
    public function issueReservation(AirReservation $reservation): AirReservation;

    /**
     * VOID an existing ticketed reservation
     * @param AirReservation $reservation Reservation to VOID
     * @return AirReservation VOIDed reservation
     */
    public function voidReservation(AirReservation $reservation): AirReservation;

//    public function getRules(AirOption $option, AirSearchRequest $request, ?int $category = null, ?int $targetTrip = null): array;
}