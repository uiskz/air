<?php
declare(strict_types=1);

namespace Uiskz\Air\Tests;

use PHPUnit\Framework\TestCase;
use Uiskz\Air\AirFareFamilyCombination;
use Uiskz\Air\AirOption;
use Uiskz\Air\AirPassengerFareDetails;
use Uiskz\Air\AirSearchRequest;
use Uiskz\Travel\Traveler;

class AirPassengerFareDetailsTest extends TestCase
{
    public function testTypeIsTheFareRecordKindAndPassengerTypeIsThePassenger(): void
    {
        $info = new AirPassengerFareDetails();

        $this->assertSame(AirPassengerFareDetails::TYPE_TICKET, $info->type);
        $this->assertSame(Traveler::TYPE_ADULT, $info->passengerType);
    }

    public function testSetPriceTakesQuantityFromPassengerType(): void
    {
        $combination = new AirFareFamilyCombination();
        $combination->passengerInfos = [
            $this->info(Traveler::TYPE_ADULT, 100.0),
            $this->info(Traveler::TYPE_CHILD, 50.0),
            $this->info(Traveler::TYPE_INFANT, 10.0),
        ];
        $request = (new \ReflectionClass(AirSearchRequest::class))->newInstanceWithoutConstructor();
        $request->adults = 2;
        $request->children = 1;
        $request->infants = 1;

        $option = new AirOption();
        $option->setPrice($combination, $request);

        $this->assertSame(2, $option->passengerInfos[0]->passengerQty);
        $this->assertSame(1, $option->passengerInfos[1]->passengerQty);
        $this->assertSame(1, $option->passengerInfos[2]->passengerQty);
        $this->assertEqualsWithDelta(260.0, $option->fare, 0.001);
    }

    public function testAddPassengerInfosMergesSamePassengerTypeAndKindOnly(): void
    {
        $emd = $this->info(Traveler::TYPE_ADULT, 30.0);
        $emd->type = AirPassengerFareDetails::TYPE_EMD;

        $option = new AirOption();
        $option->addPassengerInfos([
            $this->info(Traveler::TYPE_ADULT, 100.0),
            $this->info(Traveler::TYPE_ADULT, 20.0),
            $this->info(Traveler::TYPE_CHILD, 50.0),
            $emd,
        ]);

        $this->assertCount(3, $option->passengerInfos);
        $this->assertSame(Traveler::TYPE_ADULT, $option->passengerInfos[0]->passengerType);
        $this->assertEqualsWithDelta(120.0, $option->passengerInfos[0]->fare, 0.001);
        $this->assertSame(Traveler::TYPE_CHILD, $option->passengerInfos[1]->passengerType);
        $this->assertSame(AirPassengerFareDetails::TYPE_EMD, $option->passengerInfos[2]->type);
    }

    private function info(string $passengerType, float $fare): AirPassengerFareDetails
    {
        $info = new AirPassengerFareDetails();
        $info->passengerType = $passengerType;
        $info->passengerQty = 1;
        $info->fare = $fare;
        $info->total = $fare;

        return $info;
    }
}
