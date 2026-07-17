<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit\Normalizers;

use DateTime;
use DateTimeImmutable;
use Exception;
use JsonSerializable;
use RuntimeException;
use WPTechnix\WP_Simple_Logger\Normalizers\Data_Normalizer;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Data_Normalizer_Test extends TestCase
{
    private Data_Normalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new Data_Normalizer();
    }

    public function test_normalize_scalar_values(): void
    {
        $result = $this->normalizer->normalize_context([
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
        ]);

        $this->assertSame('hello', $result['string']);
        $this->assertSame(42, $result['int']);
        $this->assertSame(3.14, $result['float']);
        $this->assertSame(true, $result['bool']);
        $this->assertNull($result['null']);
    }

    public function test_normalize_nested_array(): void
    {
        $result = $this->normalizer->normalize_context([
            'nested' => ['a' => 1, 'b' => ['c' => 2]],
        ]);

        $this->assertSame(['a' => 1, 'b' => ['c' => 2]], $result['nested']);
    }

    public function test_normalize_handles_infinite_float(): void
    {
        $result = $this->normalizer->normalize_context(['val' => INF]);
        $this->assertSame('INF', $result['val']);

        $result = $this->normalizer->normalize_context(['val' => -INF]);
        $this->assertSame('-INF', $result['val']);
    }

    public function test_normalize_handles_nan(): void
    {
        $result = $this->normalizer->normalize_context(['val' => NAN]);
        $this->assertSame('NaN', $result['val']);
    }

    public function test_normalize_array_truncation(): void
    {
        $large_array = range(1, 2000);
        $result = $this->normalizer->normalize_context(['items' => $large_array]);

        $this->assertCount(1001, $result['items']);
        $this->assertArrayHasKey('...', $result['items']);
        $this->assertStringContainsString('Over 1000 items (2000 total), aborting normalization', $result['items']['...']);
    }

    public function test_normalize_recursion_limit(): void
    {
        $deep = [];
        $ref = &$deep;
        for ($i = 0; $i < 15; ++$i) {
            $ref['nested'] = [];
            $ref = &$ref['nested'];
        }

        $result = $this->normalizer->normalize_context($deep);
        $this->assertStringContainsString('levels deep, aborting normalization', \json_encode($result));
    }

    public function test_normalize_closure_round_trips_to_empty_array_under_class_name(): void
    {
        $result = $this->normalizer->normalize_context([
            'fn' => function (): void {},
        ]);

        $this->assertSame(['Closure' => []], $result['fn']);
    }

    public function test_normalize_resource(): void
    {
        $res = tmpfile();

        try {
            $result = $this->normalizer->normalize_context([
                'res' => $res,
            ]);

            $this->assertStringContainsString('[resource(', $result['res']);
        } finally {
            fclose( $res );
        }
    }

    public function test_normalize_datetime_immutable(): void
    {
        $date = new DateTimeImmutable('2025-01-15T10:30:00+00:00');
        $result = $this->normalizer->normalize_context(['when' => $date]);

        $this->assertSame('2025-01-15T10:30:00+00:00', $result['when']);
    }

    public function test_normalize_mutable_datetime(): void
    {
        $date = new DateTime('2025-01-15T10:30:00+00:00');
        $result = $this->normalizer->normalize_context(['when' => $date]);

        $this->assertSame('2025-01-15T10:30:00+00:00', $result['when']);
    }

    public function test_normalize_plain_object_wraps_public_properties_under_class_name(): void
    {
        // An anonymous class with no parent or interfaces collapses to 'class@anonymous',
        // mirroring Monolog's Utils::getClass() handling of anonymous class names.
        $obj = new class {
            public string $foo = 'bar';
            public int $count = 1;
        };

        $result = $this->normalizer->normalize_context(['obj' => $obj]);

        $this->assertSame(['class@anonymous' => ['foo' => 'bar', 'count' => 1]], $result['obj']);
    }

    public function test_normalize_json_serializable_object(): void
    {
        // The anonymous class implements only JsonSerializable, so that interface
        // name is used to build the collapsed anonymous-class key.
        $obj = new class implements JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['a' => 1];
            }
        };

        $result = $this->normalizer->normalize_context(['obj' => $obj]);

        $this->assertSame(['JsonSerializable@anonymous' => ['a' => 1]], $result['obj']);
    }

    public function test_normalize_named_class_uses_fully_qualified_class_name(): void
    {
        $obj = new Data_Normalizer_Fixture_Object();

        $result = $this->normalizer->normalize_context(['obj' => $obj]);

        $this->assertSame([Data_Normalizer_Fixture_Object::class => ['foo' => 'bar']], $result['obj']);
    }

    public function test_normalize_stringable_object(): void
    {
        $obj = new class {
            public function __toString(): string
            {
                return 'stringy';
            }
        };

        $result = $this->normalizer->normalize_context(['obj' => $obj]);

        $this->assertSame(['stringy'], array_values($result['obj']));
    }

    public function test_normalize_exception_uses_file_line_trace(): void
    {
        $exception = new Exception('boom', 5);
        $result = $this->normalizer->normalize_context(['err' => $exception]);

        $this->assertSame(Exception::class, $result['err']['class']);
        $this->assertSame('boom', $result['err']['message']);
        $this->assertSame(5, $result['err']['code']);
        $this->assertStringContainsString(':', $result['err']['file']);
        $this->assertIsArray($result['err']['trace']);

        foreach ($result['err']['trace'] as $frame) {
            $this->assertMatchesRegularExpression('/^.+:\d+$/', $frame);
        }
    }

    public function test_normalize_exception_chains_previous(): void
    {
        $previous = new RuntimeException('root cause');
        $exception = new Exception('wrapper', 0, $previous);

        $result = $this->normalizer->normalize_context(['err' => $exception]);

        $this->assertArrayHasKey('previous', $result['err']);
        $this->assertSame(RuntimeException::class, $result['err']['previous']['class']);
        $this->assertSame('root cause', $result['err']['previous']['message']);
    }
}

final class Data_Normalizer_Fixture_Object
{
    public string $foo = 'bar';
}
