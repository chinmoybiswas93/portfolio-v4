<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Import\ImportLegacyPortfolio;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class ImportLegacyPortfolioCommand extends Command
{
    protected $signature = 'import:legacy-portfolio
        {path : Path to the cb-portfolio JSON export}
        {--assets= : Folder holding the projects image folder and resume.pdf}
        {--dry-run : Run inside a transaction and roll back (files are not copied)}';

    protected $description = 'Import profile, experience and projects from the old portfolio plugin export';

    public function handle(ImportLegacyPortfolio $import): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Invalid JSON: '.$e->getMessage());

            return self::FAILURE;
        }
        if (! is_array($data)) {
            $this->error('The export must be a JSON object.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $assets = $dryRun ? null : ($this->option('assets') ? rtrim((string) $this->option('assets'), '/') : null);

        $report = null;
        try {
            DB::transaction(function () use ($import, $data, $assets, $dryRun, &$report): void {
                /** @var array<string, mixed> $data */
                $report = $import($data, $assets);
                if ($dryRun) {
                    throw new RuntimeException('dry-run');
                }
            });
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== 'dry-run') {
                throw $e;
            }
        }

        if ($report === null) {
            return self::FAILURE;
        }

        $this->table(['Item', 'Created', 'Updated'], [
            ['Experience', $report->experiencesCreated, $report->experiencesUpdated],
            ['Projects', $report->projectsCreated, $report->projectsUpdated],
        ]);
        $this->line('Covers attached: '.$report->coversAttached.' · CV copied: '.($report->cvCopied ? 'yes' : 'no'));
        foreach ($report->warnings as $warning) {
            $this->warn($warning);
        }
        $this->info($dryRun ? 'Dry run: nothing was saved.' : 'Import complete.');

        return self::SUCCESS;
    }
}
