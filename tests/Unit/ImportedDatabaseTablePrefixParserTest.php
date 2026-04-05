<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ImportedDatabaseTablePrefixParser;
use PHPUnit\Framework\TestCase;

class ImportedDatabaseTablePrefixParserTest extends TestCase
{
    public function test_prefers_wp_options(): void
    {
        $out = "wp_options\nwp_2_options\n";
        $this->assertSame('wp_', ImportedDatabaseTablePrefixParser::fromShowTablesOutput($out));
    }

    public function test_custom_prefix_table(): void
    {
        $out = "acme_options\n";
        $this->assertSame('acme_', ImportedDatabaseTablePrefixParser::fromShowTablesOutput($out));
    }

    public function test_ignores_wp_n_options_when_other_exists(): void
    {
        $out = "wp_3_options\nacme_options\n";
        $this->assertSame('acme_', ImportedDatabaseTablePrefixParser::fromShowTablesOutput($out));
    }

    public function test_empty_output(): void
    {
        $this->assertNull(ImportedDatabaseTablePrefixParser::fromShowTablesOutput(''));
    }

    public function test_only_multisite_blog_options_defaults_to_wp(): void
    {
        $out = "wp_2_options\nwp_3_options\n";
        $this->assertSame('wp_', ImportedDatabaseTablePrefixParser::fromShowTablesOutput($out));
    }
}
