<?php

namespace App\Console\Commands;

use App\Models\Journal;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\EditorialExtraction\ExtractEditorialSnapshot;
use App\Services\OfficialSources\OfficialSources;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

class JournalSources extends Command
{
    protected $signature = 'journals:sources {action : register|approve|fetch|deactivate|status|extract}
        {--record= : Stored snapshot UUID, extract only}
        {--journal= : Existing journal UUID} {--source= : Registered source UUID}
        {--type=official_journal : official_journal|official_publisher}
        {--purpose=website : website|guidelines|submission|fees|template|article_types|editorial}
        {--url= : Candidate HTTPS URL, register only} {--actor= : Non-sensitive operator reference}
        {--basis= : Ownership, applicability, robots/access and retention review reference}
        {--dry-run : Preview without any database writes}';

    protected $description = 'Register, approve and check one explicit official journal evidence page';

    public function handle(OfficialSources $sources, ExtractEditorialSnapshot $extractor): int
    {
        try {
            $action = $this->argument('action');
            if (! in_array($action, ['register', 'approve', 'fetch', 'deactivate', 'status', 'extract'], true)
                || ($action !== 'register' && $this->option('url') !== null)
                || ($action !== 'extract' && $this->option('record') !== null)) {
                throw new DomainException('official_invalid_action');
            }
            $dry = (bool) $this->option('dry-run');
            if ($action === 'extract') {
                $record = SourceRecord::findOrFail($this->option('record'));
                if ($this->option('source') !== null && $record->journal_source_id !== $this->option('source')) {
                    throw new DomainException('editorial_invalid_source');
                }
                $result = $extractor->extract($record, $dry, $this->option('journal'));
            } elseif ($action === 'register') {
                $journal = Journal::findOrFail($this->option('journal'));
                $source = $sources->register($journal, $this->option('type'), $this->option('purpose'), (string) $this->option('url'), $dry);
                $result = ['source_id' => $source->id, 'approval_status' => $source->approval_status, 'dry_run' => $dry];
            } else {
                $source = JournalSource::findOrFail($this->option('source'));
                if (! $source->official_journal_id || ($this->option('journal') !== null && $source->official_journal_id !== $this->option('journal'))) {
                    throw new DomainException('official_invalid_source');
                }
                if ($action === 'fetch') {
                    $result = $sources->fetch($source, $dry);
                } elseif ($action === 'approve') {
                    $source = $sources->approve($source, (string) $this->option('actor'), (string) $this->option('basis'), $dry);
                    $result = ['source_id' => $source->id, 'approval_status' => $source->approval_status, 'dry_run' => $dry];
                } elseif ($action === 'deactivate') {
                    $sources->deactivate($source, $dry);
                    $result = ['source_id' => $source->id, 'dry_run' => $dry];
                } else {
                    $result = ['source_id' => $source->id, 'journal_id' => $source->official_journal_id,
                        'approval_status' => $source->approval_status, 'last_outcome' => $source->last_outcome,
                        'last_checked_at' => $source->last_checked_at?->toIso8601String(),
                        'last_changed_at' => $source->last_changed_at?->toIso8601String(),
                        'source_record_id' => $source->last_source_record_id];
                }
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $code = $exception instanceof DomainException && preg_match('/^(official|editorial)_[a-z0-9_]+$/D', $exception->getMessage())
                ? $exception->getMessage() : 'official_operation_failed';
            $this->error($code);

            return self::FAILURE;
        }
    }
}
