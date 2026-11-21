<?php

declare(strict_types=1);

namespace App\Data;

final class ImportReport
{
    public int $experiencesCreated = 0;

    public int $experiencesUpdated = 0;

    public int $projectsCreated = 0;

    public int $projectsUpdated = 0;

    public int $coversAttached = 0;

    public bool $profileUpdated = false;

    public bool $cvCopied = false;

    /** @var list<string> */
    public array $warnings = [];
}
