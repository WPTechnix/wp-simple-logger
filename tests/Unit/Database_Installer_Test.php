<?php

declare(strict_types=1);

namespace WPTechnix\WP_Simple_Logger\Tests\Unit;

use Brain\Monkey\Functions;
use InvalidArgumentException;
use WPTechnix\WP_Simple_Logger\Handlers\Database\Database_Installer;
use WPTechnix\WP_Simple_Logger\Tests\TestCase;

final class Database_Installer_Test extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        parent::tearDown();
    }

    public function test_invalid_table_name_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Database_Installer('bad name');
    }

    public function test_get_table_name(): void
    {
        $installer = new Database_Installer('wp_app_logs');
        $this->assertSame('wp_app_logs', $installer->get_table_name());
    }

    public function test_install_skips_schema_when_version_is_current(): void
    {
        $updated = false;
        Functions\when('get_option')->justReturn(999999);
        Functions\when('update_option')->alias(function () use (&$updated): bool {
            $updated = true;
            return true;
        });

        $installer = new Database_Installer('wp_logs');
        $installer->install();

        $this->assertFalse($updated);
    }

    public function test_install_runs_schema_and_saves_version_when_outdated(): void
    {
        $abspath = sys_get_temp_dir() . '/wpsl_abspath_' . uniqid() . '/';
        mkdir($abspath . 'wp-admin/includes', 0777, true);
        file_put_contents($abspath . 'wp-admin/includes/upgrade.php', "<?php\n");

        if (! defined('ABSPATH')) {
            define('ABSPATH', $abspath);
        }

        $GLOBALS['wpdb'] = new class {
            public function get_charset_collate(): string
            {
                return '';
            }
        };

        $savedVersion = null;
        Functions\when('get_option')->justReturn(0);
        Functions\when('dbDelta')->justReturn([]);
        Functions\when('update_option')->alias(function ($key, $value) use (&$savedVersion): bool {
            $savedVersion = $value;
            return true;
        });

        $installer = new Database_Installer('wp_logs');
        $installer->install();

        $this->assertIsInt($savedVersion);
        $this->assertGreaterThan(0, $savedVersion);

        unlink(ABSPATH . 'wp-admin/includes/upgrade.php');
    }
}
