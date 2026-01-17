<?php

declare(strict_types=1);
/**
 * Unofficial RocketMQ Client SDK for Hyperf
 *
 * @contact colisys@duck.com
 * @license Apache-2.0
 * @copyright 2025 Colisys
 */

namespace Colisys\RmqClient\Grpc\Builder;

use Colisys\RmqClient\Grpc\Contract\FilterBoolean;
use Colisys\RmqClient\Grpc\Contract\FilterOperator;
use Colisys\Rocketmq\Helper\Assert;

/**
 * This builder is used to build SQL92 filter expression.
 */
final class FilterBuilder
{
    public const FIELD_MESSAGE_TAGS = 'TAGS';

    /**
     * @var array{0: string, 1: null|int|string, 2: FilterOperator, 3: FilterBoolean}[]
     */
    private array $wheres = [];

    private string $lastSql = '';

    public function __construct()
    {
        $this->cleanup();
    }

    public static function make()
    {
        return new self();
    }

    public function where(
        string $field,
        int|string|null $value,
        FilterOperator $operator = FilterOperator::EQ,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        $this->wheres[] = [$field, match (gettype($value)) {
            'string' => "\"{$value}\"",
            default => $value
        }, $operator, $boolean];
        return $this;
    }

    public function whereBetween(
        string $field,
        array $value,
        FilterBoolean $boolean = FilterBoolean::AND,
    ): self {
        Assert::length($value, 2, 'The value of whereNotBetween must be an array with two elements.');
        $this->wheres[] = [
            $field,
            array_map(fn ($val) => match (gettype($val)) {
                'string' => "\"{$val}\"",
                default => $val
            }, $value),
            FilterOperator::BETWEEN,
            $boolean,
        ];
        return $this;
    }

    public function whereNotBetween(
        string $field,
        array $value,
        FilterBoolean $boolean = FilterBoolean::AND,
    ): self {
        Assert::length($value, 2, 'The value of whereNotBetween must be an array with two elements.');
        $this->wheres[] = [
            $field,
            array_map(fn ($val) => match (gettype($val)) {
                'string' => "\"{$val}\"",
                default => $val
            }, $value),
            FilterOperator::NOTBETWEEN,
            $boolean,
        ];
        return $this;
    }

    /**
     * @template BuilderCallback of \Closure(FilterBuilder $builder): FilterBuilder
     *
     * @param BuilderCallback $callback
     * @return $this
     */
    public function whereLambda(
        callable $callback,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        $this->wheres[] = [
            null,
            $callback(new self())->build(),
            FilterOperator::LAMBDA,
            $boolean,
        ];
        return $this;
    }

    public function whereOr(
        string $field,
        int|string $value,
        FilterOperator $operator = FilterOperator::EQ
    ): self {
        return $this->where($field, $value, $operator, FilterBoolean::OR);
    }

    public function whereNull(
        string $field,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        return $this->where($field, null, FilterOperator::ISNULL, $boolean);
    }

    public function whereNotNull(
        string $field,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        return $this->where($field, null, FilterOperator::ISNOTNULL, $boolean);
    }

    public function whereIn(
        string $field,
        array $values,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        $this->wheres[] = [
            $field,
            implode(
                ', ',
                array_map(fn ($val) => match (gettype($val)) {
                    'string' => "\"{$val}\"",
                    default => $val
                }, $values)
            ),
            FilterOperator::IN,
            $boolean,
        ];
        return $this;
    }

    public function whereRaw(
        string $expression,
        FilterBoolean $boolean = FilterBoolean::AND
    ): self {
        $this->wheres[] = [
            '',
            $expression,
            FilterOperator::RAW,
            $boolean,
        ];
        return $this;
    }

    public function lastSql(): string
    {
        return $this->lastSql;
    }

    public function build(): string
    {
        $str = [];
        while (count($this->wheres) > 0) {
            $where = array_shift($this->wheres);
            $segment = match ($where[2]) {
                FilterOperator::EQ,
                FilterOperator::NE,
                FilterOperator::GT,
                FilterOperator::LT,
                FilterOperator::GE,
                FilterOperator::LE,
                FilterOperator::IN,
                FilterOperator::NOTIN => sprintf($where[2]->value, $where[0], $where[1]),
                FilterOperator::ISNULL,
                FilterOperator::ISNOTNULL => sprintf($where[2]->value, $where[0]),
                FilterOperator::RAW,
                FilterOperator::LAMBDA => sprintf($where[2]->value, $where[1]),
                FilterOperator::BETWEEN,
                FilterOperator::NOTBETWEEN => sprintf($where[2]->value, $where[0], $where[1][0], $where[1][1])
            };

            $str[] = $segment;
            $str[] = $where[3]->value;
        }
        array_pop($str);
        $this->lastSql = implode(' ', $str);
        return $this->lastSql();
    }

    private function cleanup()
    {
        $this->wheres = [];
    }
}
