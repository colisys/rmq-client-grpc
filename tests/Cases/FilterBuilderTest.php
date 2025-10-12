<?php

declare(strict_types=1);
/**
 * Third-party RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license MIT
 * @copyright 2025 Colisys
 */

namespace Colisys\Rocketmq\Tests\Cases;

use Colisys\Rocketmq\Builder\FilterBuilder;
use Hyperf\Testing\TestCase;

/**
 * @internal
 * @coversNothing
 */
class FilterBuilderTest extends TestCase
{
    public function testWhere()
    {
        $builder = FilterBuilder::make();
        $builder->where(FilterBuilder::FIELD_MESSAGE_TAGS, 'tag1');
        $builder->where('region', 'beijing');
        $this->assertEquals('TAGS = "tag1" AND region = "beijing"', $builder->build());

        $builder->whereOr(FilterBuilder::FIELD_MESSAGE_TAGS, 'tag1');
        $builder->whereIn('region', ['beijing', 'shanghai']);
        $this->assertEquals('TAGS = "tag1" OR region IN ("beijing", "shanghai")', $builder->build());
    }

    public function testWhereOr()
    {
        $builder = FilterBuilder::make();
        $builder->whereOr(FilterBuilder::FIELD_MESSAGE_TAGS, 'tag1');
        $builder->whereOr('region', 'beijing');
        $this->assertEquals('TAGS = "tag1" OR region = "beijing"', $builder->build());
    }

    public function testWhereIn()
    {
        $builder = FilterBuilder::make();
        $builder->whereIn(FilterBuilder::FIELD_MESSAGE_TAGS, ['tag1', 'tag2']);
        $this->assertEquals('TAGS IN ("tag1", "tag2")', $builder->build());
    }

    public function testWhereRaw()
    {
        $builder = FilterBuilder::make();
        $builder->whereRaw('region = "beijing"');
        $this->assertEquals('region = "beijing"', $builder->build());
    }

    public function testWhereNull()
    {
        $builder = FilterBuilder::make();
        $builder->whereNull('region');
        $this->assertEquals('region IS NULL', $builder->build());
    }

    public function testWhereNotNull()
    {
        $builder = FilterBuilder::make();
        $builder->whereNotNull('region');
        $this->assertEquals('region IS NOT NULL', $builder->build());
    }

    public function testWhereBetween()
    {
        $builder = FilterBuilder::make();
        $builder->whereBetween('region', ['beijing', 'shanghai']);
        $this->assertEquals('region BETWEEN "beijing" AND "shanghai"', $builder->build());
    }

    public function testWhereNotBetween()
    {
        $builder = FilterBuilder::make();
        $builder->whereNotBetween('region', ['beijing', 'shanghai']);
        $this->assertEquals('region NOT BETWEEN "beijing" AND "shanghai"', $builder->build());
    }
}
