<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Tests\Cases;

use Colisys\RmqClient\Shared\Helper\Arr;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class Stub extends TestCase
{
    public function __construct(
        public int $digit
    ) {
    }
}

class NestedStub extends Stub
{
    public function __destruct()
    {
        $this->assertTrue(true);
    }
}

/**
 * @internal
 * @coversNothing
 */
class HelperTest extends TestCase
{
    public function testExample()
    {
        $this->assertTrue(true);
    }

    public function testArr()
    {
        $unit = Arr::fromArray([], 'int');
        $this->assertNull($unit->first());
        $unit->append([1, 2, 3, '4']);
        $this->assertEquals(3, $unit->count());
        $this->assertEquals(3, $unit->last());
        $unit = $unit->filter(fn (int $v) => $v % 2 == 0)->map(fn (int $v): int => $v * 2);
        $this->assertEquals(1, $unit->count());
        $this->assertEquals(4, $unit->first());
        unset($unit);

        $unit = Arr::fromArray([
            new NestedStub(1),
            new NestedStub(2),
            new NestedStub(3),
        ]);
        $this->assertEquals(3, $unit->count());
        unset($unit);
    }
}
