<?php

namespace Tests\Feature;

use App\Models\Journal;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Real independent PostgreSQL writer, without RefreshDatabase's enclosing transaction. */
class OfficialSourceRollbackTest extends TestCase
{
    use DatabaseMigrations;

    public function test_rollback_locks_before_check_and_concurrent_official_insert_cannot_lose_history(): void
    {
        $journal = Journal::create(['title' => 'Synthetic concurrency journal']);
        $settings = config('database.connections.pgsql');
        $connectionString = [];
        foreach (['host' => 'host', 'port' => 'port', 'dbname' => 'database', 'user' => 'username', 'password' => 'password'] as $key => $setting) {
            $connectionString[] = $key."='".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $settings[$setting])."'";
        }
        $writer = pg_connect(implode(' ', $connectionString), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($writer);
        pg_query($writer, "SET statement_timeout='5s'");
        $pid = pg_get_pid($writer);
        $queued = false;
        $migration = require database_path('migrations/2026_10_12_100000_add_official_source_foundation.php');
        DB::listen(function (QueryExecuted $query) use ($writer, $pid, $journal, &$queued): void {
            if ($queued || $query->sql !== 'LOCK TABLE journal_sources, source_records IN ACCESS EXCLUSIVE MODE') {
                return;
            }
            $this->assertGreaterThan(0, DB::transactionLevel());
            foreach (['journal_sources', 'source_records'] as $table) {
                $this->assertTrue(DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_locks WHERE pid=pg_backend_pid() AND relation=?::regclass AND mode='AccessExclusiveLock' AND granted) AS held", [$table])->held);
            }
            $queued = true;
            $this->assertTrue(pg_send_query_params($writer,
                "INSERT INTO journal_sources (id,type,name,base_url,official_journal_id,official_purpose,official_url,official_origin,approval_status,first_observed_at) VALUES ($1,'official_journal','Concurrent official page','https://journal.example.org',$2,'guidelines','https://journal.example.org/guidelines','https://journal.example.org','candidate',now())",
                [(string) Str::uuid7(), $journal->id]));
            $blocked = false;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $blocked = DB::selectOne("SELECT EXISTS(SELECT 1 FROM pg_locks WHERE pid=? AND relation='journal_sources'::regclass AND NOT granted) AS waiting", [$pid])->waiting;
                if ($blocked) {
                    break;
                }
                usleep(10000);
            }
            $this->assertTrue($blocked, 'Independent writer must wait behind the rollback lock before the emptiness check.');
        });
        try {
            DB::transaction(fn () => $migration->down());
            $this->assertTrue($queued, 'Migration must acquire both locks before checking/dropping registry fields.');
            $this->assertFalse(Schema::hasColumn('journal_sources', 'official_url'));
            $result = pg_get_result($writer);
            $this->assertSame(PGSQL_FATAL_ERROR, pg_result_status($result));
            $this->assertSame('42703', pg_result_error_field($result, PGSQL_DIAG_SQLSTATE));
            $this->assertSame(0, DB::table('journal_sources')->count());
            $this->assertSame(0, DB::table('source_records')->count());
        } finally {
            pg_close($writer);
            if (! Schema::hasColumn('journal_sources', 'official_url')) {
                DB::transaction(fn () => $migration->up());
            }
            $journal->delete();
        }
        $this->assertTrue(Schema::hasColumn('journal_sources', 'official_url'));
        $this->assertTrue(Schema::hasColumn('source_records', 'raw_content'));
    }
}
