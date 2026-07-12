<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use WPTechnix\WP_Simple_Logger\Handlers\Database\Log_Repository;
use WPTechnix\WP_Simple_Logger\Log_Entry;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Log_Repository_Test extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    /**
     * @return \wpdb&MockInterface
     */
    private function mockWpdb(): MockInterface
    {
        $wpdb = Mockery::mock(\wpdb::class);
        $GLOBALS['wpdb'] = $wpdb;
        return $wpdb;
    }

    public function test_invalid_table_name_throws(): void
    {
        $this->mockWpdb();

        $this->expectException(InvalidArgumentException::class);
        new Log_Repository('bad-table-name!');
    }

    public function test_valid_table_name_is_accepted(): void
    {
        $this->mockWpdb();

        $repo = new Log_Repository('wp_app_logs');
        $this->assertInstanceOf(Log_Repository::class, $repo);
    }

    public function test_insert_many_runs_a_single_prepared_query(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->shouldReceive('prepare')->once()->andReturn('PREPARED');
        $wpdb->shouldReceive('query')->once()->with('PREPARED')->andReturn(2);

        $repo = new Log_Repository('wp_logs');
        $result = $repo->insert_many([
            ['channel' => 'ch', 'level' => 400, 'message' => 'a', 'context' => ['x' => 1], 'timestamp' => '2024-01-15 10:30:00'],
            ['channel' => 'ch', 'level' => 200, 'message' => 'b', 'context' => null, 'timestamp' => '2024-01-15 10:31:00'],
        ]);

        $this->assertTrue($result);
    }

    public function test_insert_many_with_empty_input_returns_false(): void
    {
        $this->mockWpdb();

        $repo = new Log_Repository('wp_logs');
        $this->assertFalse($repo->insert_many([]));
    }

    public function test_get_logs_maps_rows_to_log_entries(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->shouldReceive('prepare')->andReturn('SQL');
        $wpdb->shouldReceive('get_results')->once()->andReturn([
            [
                'id' => '3',
                'channel' => 'api',
                'level' => '400',
                'message' => 'boom',
                'context' => '{"x":1}',
                'timestamp' => '2024-01-15 10:30:00',
            ],
        ]);

        $repo = new Log_Repository('wp_logs');
        $entries = $repo->get_logs([], 30, 1);

        $this->assertCount(1, $entries);
        $this->assertInstanceOf(Log_Entry::class, $entries[0]);
        $this->assertSame('api', $entries[0]->get_channel());
        $this->assertSame(400, $entries[0]->get_level_priority());
        $this->assertSame(['x' => 1], $entries[0]->get_context());
        $this->assertSame(3, $entries[0]->get_id());
    }

    public function test_get_logs_applies_channel_level_and_search_filters(): void
    {
        $wpdb = $this->mockWpdb();
        $capturedSql = '';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(static fn ($value) => $value);
        $wpdb->shouldReceive('prepare')
            ->once()
            ->andReturnUsing(function ($sql) use (&$capturedSql) {
                $capturedSql = $sql;
                return 'SQL';
            });
        $wpdb->shouldReceive('get_results')->once()->andReturn([]);

        $repo = new Log_Repository('wp_logs');
        $repo->get_logs(
            ['channel' => 'api', 'level' => 'error', 'level_compare' => 'min', 'search' => 'foo'],
            30,
            1
        );

        $this->assertStringContainsString('channel = %s', $capturedSql);
        $this->assertStringContainsString('level >= %d', $capturedSql);
        $this->assertStringContainsString('LIKE %s', $capturedSql);
    }

    public function test_get_total_logs_count_without_filters(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->shouldReceive('get_var')->once()->andReturn('42');

        $repo = new Log_Repository('wp_logs');
        $this->assertSame(42, $repo->get_total_logs_count([]));
    }

    public function test_delete_by_ids_returns_affected_rows(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->shouldReceive('prepare')->once()->andReturn('DELETE_SQL');
        $wpdb->shouldReceive('query')->once()->with('DELETE_SQL')->andReturn(3);

        $repo = new Log_Repository('wp_logs');
        $this->assertSame(3, $repo->delete_by_ids([1, 2, 3]));
    }

    public function test_delete_by_ids_with_empty_input_returns_zero(): void
    {
        $this->mockWpdb();

        $repo = new Log_Repository('wp_logs');
        $this->assertSame(0, $repo->delete_by_ids([]));
    }

    public function test_get_distinct_channels_returns_list(): void
    {
        $wpdb = $this->mockWpdb();
        $wpdb->shouldReceive('get_col')->once()->andReturn(['api', 'payments']);

        $repo = new Log_Repository('wp_logs');
        $this->assertSame(['api', 'payments'], $repo->get_distinct_channels());
    }
}
