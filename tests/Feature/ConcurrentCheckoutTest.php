<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Proves the last-unit-of-stock race condition described in BRIEF.md §04/§06
 * is actually prevented under real concurrency. See AI_AGENT.md §4.3 for why
 * this cannot run meaningfully against SQLite and must run against MySQL or
 * PostgreSQL instead.
 *
 * Run with:
 *   DB_CONNECTION=mysql DB_DATABASE=gold_checkout_test php artisan test --group=concurrency
 */
#[Group('concurrency')]
class ConcurrentCheckoutTest extends TestCase
{
    use DatabaseTruncation;

    private const CONCURRENT_ATTEMPTS = 5;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(config('database.default'), ['mysql', 'pgsql'], true)) {
            $this->markTestSkipped(
                'Concurrency is only meaningfully testable against MySQL/PostgreSQL, '.
                'which provide real row-level locking. SQLite serializes writers at the '.
                'whole-database level and ignores lockForUpdate(). See AI_AGENT.md §4.3.'
            );
        }

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('The pcntl extension is required to run this test.');
        }
    }

    public function test_only_one_checkout_wins_the_last_unit_of_stock(): void
    {
        $product = Product::factory()->create(['stock' => 1]);

        // Each child process needs to see this row without depending on the
        // parent's open transaction (DatabaseTruncation commits real rows,
        // unlike RefreshDatabase's wrapping transaction).
        $resultDir = sys_get_temp_dir().'/concurrent-checkout-'.uniqid();
        mkdir($resultDir);

        $pids = [];

        for ($i = 0; $i < self::CONCURRENT_ATTEMPTS; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $this->fail('pcntl_fork() failed.');
            }

            if ($pid === 0) {
                // Child process: must not share the parent's DB connection.
                DB::purge(config('database.default'));

                $outcome = 'failed';

                try {
                    app(OrderService::class)->placeOrder($product->id, 1);
                    $outcome = 'succeeded';
                } catch (\Throwable) {
                    $outcome = 'failed';
                }

                file_put_contents("{$resultDir}/{$i}.result", $outcome);

                exit(0);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        // Parent's connection may be stale after the children wrote via
        // their own connections; force a fresh read.
        DB::purge(config('database.default'));

        $outcomes = array_map(
            fn (int $i) => trim(file_get_contents("{$resultDir}/{$i}.result")),
            range(0, self::CONCURRENT_ATTEMPTS - 1)
        );

        array_map('unlink', glob("{$resultDir}/*.result"));
        rmdir($resultDir);

        $successCount = count(array_filter($outcomes, fn ($o) => $o === 'succeeded'));

        $this->assertSame(1, $successCount, 'Exactly one concurrent checkout should win the last unit of stock.');
        $this->assertSame(1, Order::count());

        $finalStock = Product::find($product->id)->stock;
        $this->assertSame(0, $finalStock);
        $this->assertGreaterThanOrEqual(0, $finalStock, 'Stock must never go negative.');
    }
}
