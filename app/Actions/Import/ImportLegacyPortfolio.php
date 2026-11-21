<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Data\ImportReport;
use App\Enums\PublishStatus;
use App\Enums\TagType;
use App\Models\Experience;
use App\Models\Project;
use App\Settings\ProfileSettings;
use App\Support\Markdown\MarkdownRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * One-off importer for the old cb-portfolio plugin export (docs/CONTENT_IMPORT.md).
 * Idempotent: experiences are keyed by (company, started_on) and projects by slug.
 * Never imports the phone number or the street address.
 */
final class ImportLegacyPortfolio
{
    /** Tag spellings normalised on import (lower-case key => canonical name). */
    private const TAG_ALIASES = [
        'wordpress' => 'WordPress',
        'woocommerce' => 'WooCommerce',
        'javascript' => 'JavaScript',
        'next js' => 'Next.js',
        'nextjs' => 'Next.js',
        'express js' => 'Express.js',
        'vue js' => 'Vue.js',
        'vuejs' => 'Vue.js',
        'php' => 'PHP',
        'mysql' => 'MySQL',
        'elementor pro' => 'Elementor Pro',
    ];

    public function __construct(
        private readonly MarkdownRenderer $markdown,
        private readonly ProfileSettings $profile,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Decoded export JSON.
     * @param  string|null  $assetsDir  Folder holding projects/<image> and resume.pdf; null skips files.
     */
    public function __invoke(array $data, ?string $assetsDir = null): ImportReport
    {
        foreach (['portfolio', 'experience', 'projects'] as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                throw new InvalidArgumentException("Export is missing the '{$key}' section.");
            }
        }

        $report = new ImportReport;

        /** @var array<string, mixed> $portfolio */
        $portfolio = $data['portfolio'];
        $this->importProfile($portfolio, $assetsDir, $report);

        /** @var list<array<string, mixed>> $experience */
        $experience = $data['experience'];
        foreach ($experience as $row) {
            $this->importExperience($row, $report);
        }

        /** @var list<array<string, mixed>> $projects */
        $projects = $data['projects'];
        foreach ($projects as $row) {
            $this->importProject($row, $assetsDir, $report);
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function importProfile(array $p, ?string $assetsDir, ImportReport $report): void
    {
        $this->profile->name = $this->str($p['name'] ?? null) ?: $this->profile->name;
        $this->profile->title = $this->str($p['title'] ?? null) ?: $this->profile->title;
        $this->profile->tagline = $this->str($p['tagline'] ?? null) ?: $this->profile->tagline;

        $aboutHtml = $this->str($p['about'] ?? null);
        if ($aboutHtml !== '') {
            $this->profile->about_md = trim((new HtmlConverter(['strip_tags' => true]))->convert($aboutHtml));
            $this->profile->about_html = $this->markdown->render($this->profile->about_md);
        }

        $email = $this->str($p['email'] ?? null);
        $this->profile->email = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : $this->profile->email;

        $socials = [];
        foreach (['github_url' => 'github', 'linkedin_url' => 'linkedin', 'twitter_url' => 'x'] as $field => $network) {
            $url = $this->url($p[$field] ?? null);
            if ($url !== null) {
                $socials[] = ['network' => $network, 'url' => $url];
            }
        }
        if ($socials !== []) {
            $this->profile->socials = $socials;
        }

        if ($assetsDir !== null && is_file($cv = $assetsDir.'/resume.pdf')) {
            Storage::disk('local')->put('cv/resume.pdf', (string) file_get_contents($cv));
            $this->profile->cv_path = 'cv/resume.pdf';
            $report->cvCopied = true;
        }

        $this->profile->save();
        $report->profileUpdated = true;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importExperience(array $row, ImportReport $report): void
    {
        $company = $this->str($row['company'] ?? null);
        $startedOn = $this->monthYear($row['start_date'] ?? null);
        if ($company === '' || $startedOn === null) {
            $report->warnings[] = 'Skipped an experience row without company or start date.';

            return;
        }

        $current = (int) ($row['current'] ?? 0) === 1;
        $experience = Experience::query()
            ->where('company', $company)
            ->whereDate('started_on', $startedOn->toDateString())
            ->first() ?? new Experience(['company' => $company, 'started_on' => $startedOn->toDateString()]);
        $exists = $experience->exists;

        $experience->fill([
            'company_url' => $this->url($row['company_website'] ?? null),
            'position' => $this->str($row['position'] ?? null),
            'ended_on' => $current ? null : $this->monthYear($row['end_date'] ?? null)?->toDateString(),
            'description_md' => $this->str($row['description'] ?? null),
            'sort_order' => (int) ($row['order_index'] ?? 0),
            'is_published' => true,
        ])->save();

        $experience->syncTagNames($this->tagNames($row['skills'] ?? null), TagType::Tech);
        $exists ? $report->experiencesUpdated++ : $report->experiencesCreated++;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importProject(array $row, ?string $assetsDir, ImportReport $report): void
    {
        $title = $this->str($row['title'] ?? null);
        if ($title === '') {
            $report->warnings[] = 'Skipped a project without a title.';

            return;
        }

        $project = Project::query()->firstOrNew(['slug' => Str::slug($title)]);
        $exists = $project->exists;
        $year = (int) $this->str($row['year'] ?? null);

        $project->fill([
            'title' => $title,
            'summary' => $this->str($row['description'] ?? null),
            'live_url' => $this->url($row['live_url'] ?? null),
            'repo_url' => $this->url($row['github_url'] ?? null),
            'made_at' => $this->str($row['made_at'] ?? null) ?: null,
            'year' => $year > 0 ? $year : null,
            'is_featured' => (int) ($row['featured'] ?? 0) === 1,
            'sort_order' => (int) ($row['order_index'] ?? 0),
        ]);
        if (! $exists) {
            $project->fill(['status' => PublishStatus::Published, 'published_at' => now(), 'has_case_study' => false]);
        }
        $project->save();

        $project->syncTagNames($this->tagNames($row['technologies'] ?? null), TagType::Tech);
        $exists ? $report->projectsUpdated++ : $report->projectsCreated++;

        $this->attachCover($project, $this->str($row['image_url'] ?? null), $assetsDir, $report);
    }

    private function attachCover(Project $project, string $imageUrl, ?string $assetsDir, ImportReport $report): void
    {
        if ($assetsDir === null || $imageUrl === '') {
            return;
        }

        // Files may be saved under the encoded or the decoded URL name.
        // Plain string ops: parse_url() and basename() both mangle raw multibyte characters (e.g. an en dash).
        $rawName = Str::afterLast(Str::before(Str::before($imageUrl, '#'), '?'), '/');
        $fileName = rawurldecode($rawName);
        $path = collect([$fileName, $rawName])
            ->map(fn (string $name): string => $assetsDir.'/projects/'.$name)
            ->first(fn (string $candidate): bool => is_file($candidate), '');
        if ($path === '') {
            $report->warnings[] = "Cover image not found for {$project->title}.";

            return;
        }

        $existing = $project->getFirstMedia('cover');
        if ($existing !== null && $existing->getCustomProperty('source') === $fileName) {
            return;
        }

        $project->addMedia($path)
            ->preservingOriginal()
            ->usingFileName(Str::slug($project->title).'.'.pathinfo($fileName, PATHINFO_EXTENSION))
            ->withCustomProperties(['alt' => $project->title.' website', 'source' => $fileName])
            ->toMediaCollection('cover');
        $report->coversAttached++;
    }

    /**
     * @return list<string>
     */
    private function tagNames(mixed $csv): array
    {
        return collect(explode(',', $this->str($csv)))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->map(function (string $name): string {
                $key = Str::lower(preg_replace('/\s+/', ' ', $name) ?? $name);

                return self::TAG_ALIASES[$key] ?? (string) preg_replace('/\bWordpress\b/i', 'WordPress', $name);
            })
            ->unique()
            ->values()
            ->all();
    }

    private function monthYear(mixed $value): ?CarbonImmutable
    {
        $value = $this->str($value);
        if ($value === '') {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!M Y', $value);

        return $date === null ? null : $date->startOfMonth();
    }

    private function url(mixed $value): ?string
    {
        $value = $this->str($value);

        return filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('#^https?://#i', $value) === 1 ? $value : null;
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
