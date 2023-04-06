<?php

namespace AlexWestergaard\PhpGa4Test\Unit;

use AlexWestergaard\PhpGa4\UserProperty;
use AlexWestergaard\PhpGa4\Facade\Type\Ga4Exception as TypeGa4Exception;
use AlexWestergaard\PhpGa4\Facade\Type\Event as TypeEvent;
use AlexWestergaard\PhpGa4\Exception\Ga4Exception;
use AlexWestergaard\PhpGa4\Event;
use AlexWestergaard\PhpGa4\Analytics;
use AlexWestergaard\PhpGa4Test\TestCase;

final class AnalyticsTest extends TestCase
{
    public function test_can_configure_and_export()
    {
        $analytics = Analytics::new(
            $this->prefill['measurement_id'],
            $this->prefill['api_secret'],
            $debug = true
        )
            ->setNonPersonalizedAds($nonPersonalisedAds = true)
            ->setClientId($this->prefill['client_id'])
            ->setUserId($this->prefill['user_id'])
            ->setTimestampMicros($time = time())
            ->addEvent($event = Event\JoinGroup::fromArray(['group_id' => 1]))
            ->addUserProperty($userProperty = UserProperty::fromArray(['name' => 'test', 'value' => 'testvalue']));

        $asArray = $analytics->toArray();
        $this->assertIsArray($asArray);

        $this->assertArrayHasKey('non_personalized_ads', $asArray);
        $this->assertArrayHasKey('timestamp_micros', $asArray);
        $this->assertArrayHasKey('client_id', $asArray);
        $this->assertArrayHasKey('user_id', $asArray);
        $this->assertArrayHasKey('user_properties', $asArray);
        $this->assertArrayHasKey('events', $asArray);

        $timeAsMicro = $time * 1_000_000;

        $this->assertEquals($nonPersonalisedAds, $asArray['non_personalized_ads']);
        $this->assertEquals($timeAsMicro, $asArray['timestamp_micros']);
        $this->assertEquals($this->prefill['client_id'], $asArray['client_id']);
        $this->assertEquals($this->prefill['user_id'], $asArray['user_id']);
        $this->assertEquals($userProperty->toArray(), $asArray['user_properties']);
        $this->assertEquals([$event->toArray()], $asArray['events']);
    }

    public function test_can_post_to_google()
    {
        $this->assertNull($this->analytics->post());
    }

    public function test_converts_to_full_microtime_stamp()
    {
        $this->analytics->setTimestampMicros(microtime(true));

        $arr = $this->analytics->toArray();

        $this->assertTrue($arr['timestamp_micros'] > 1_000_000);
    }

    public function test_throws_if_microtime_older_than_three_days()
    {
        $this->expectException(Ga4Exception::class);
        $this->expectExceptionCode(TypeGa4Exception::MICROTIME_EXPIRED);

        $this->analytics->setTimestampMicros(strtotime('-1 week'));
    }

    public function test_exports_userproperty_to_array()
    {
        $userProperty = UserProperty::new()
            ->setName('customer_tier')
            ->setValue('premium');

        $this->assertInstanceOf(UserProperty::class, $userProperty);
        $this->assertIsArray($userProperty->toArray());

        $this->analytics->addUserProperty($userProperty);

        $arr = $this->analytics->toArray();
        $this->assertArrayHasKey('user_properties', $arr);

        $arr = $arr['user_properties'];
        $this->assertArrayHasKey('customer_tier', $arr);

        $this->assertNull($this->analytics->post());
    }

    public function test_exports_events_to_array()
    {
        $event = Event\JoinGroup::new()
            ->setGroupId('1');

        $this->assertInstanceOf(TypeEvent::class, $event);
        $this->assertIsArray($event->toArray());

        $this->analytics->addEvent($event);

        $arr = $this->analytics->toArray();
        $this->assertArrayHasKey('events', $arr);
        $this->assertCount(1, $arr['events']);

        $this->assertNull($this->analytics->post());
    }

    public function test_throws_missing_measurement_id()
    {
        $this->expectException(Ga4Exception::class);
        $this->expectExceptionCode(Ga4Exception::REQUEST_MISSING_MEASUREMENT_ID);

        Analytics::new('', $this->prefill['api_secret'], true)->post();
    }

    public function test_throws_missing_apisecret()
    {
        $this->expectException(Ga4Exception::class);
        $this->expectExceptionCode(Ga4Exception::REQUEST_MISSING_API_SECRET);

        Analytics::new($this->prefill['measurement_id'], '', true)->post();
    }

    public function test_throws_on_too_large_request_package()
    {
        $kB = 1024;
        $preparyKB = '';
        while (mb_strlen($preparyKB) < $kB) {
            $preparyKB .= 'AAAAAAAA'; // 8 bytes
        }

        $this->expectException(Ga4Exception::class);
        $this->expectExceptionCode(Ga4Exception::REQUEST_TOO_LARGE);

        $userProperty = UserProperty::new()->setName('large_package');

        $overflowValue = '';
        while (mb_strlen(json_encode($userProperty->toArray())) <= ($kB * 131)) {
            $overflowValue .= $preparyKB;
            $userProperty->setValue($overflowValue);
        }

        $this->analytics->addUserProperty($userProperty)->post();
    }
}
