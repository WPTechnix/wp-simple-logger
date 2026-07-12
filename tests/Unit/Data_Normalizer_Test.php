<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use WPTechnix\WP_Simple_Logger\Utils\Data_Normalizer;
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
        $this->assertStringContainsString('truncated', $result['items']['...']);
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
        $this->assertStringContainsString('recursion limit', \json_encode($result));
    }

    public function test_normalize_closure(): void
    {
        $result = $this->normalizer->normalize_context([
            'fn' => function (): void {},
        ]);

        $this->assertSame('[Closure]', $result['fn']);
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
}
