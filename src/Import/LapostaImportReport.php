<?php

declare(strict_types=1);

namespace Dashed\DashedLaposta\Import;

/**
 * De uitkomst van één overname. Bewust een eigen object en geen array: het
 * command drukt hem regel voor regel af en de knop in het beheer maakt er een
 * melding van, en die twee moeten hetzelfde verhaal vertellen.
 */
class LapostaImportReport
{
    public bool $failed = false;

    /** Waarom de overname niet kon beginnen, of null als hij is begonnen. */
    public ?string $error = null;

    /** @var array<int, array{name: string, id: string, created: int, updated: int, skipped: int, reasons: array<string, string>, failed: bool}> */
    public array $lists = [];

    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public static function fail(string $error): self
    {
        $report = new self();
        $report->failed = true;
        $report->error = $error;

        return $report;
    }

    /**
     * @param array<string, string> $reasons
     */
    public function addList(string $name, string $id, int $created, int $updated, int $skipped, array $reasons): void
    {
        $this->lists[] = [
            'name' => $name,
            'id' => $id,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'reasons' => $reasons,
            'failed' => false,
        ];

        $this->created += $created;
        $this->updated += $updated;
        $this->skipped += $skipped;
    }

    public function addFailedList(string $name, string $id): void
    {
        $this->lists[] = [
            'name' => $name,
            'id' => $id,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'reasons' => [],
            'failed' => true,
        ];

        $this->failed = true;
    }

    /**
     * Eén regel met de drie getallen, voor in een melding of onder een command.
     */
    public function summary(): string
    {
        return 'aangemaakt: ' . $this->created
            . ', bijgewerkt: ' . $this->updated
            . ', overgeslagen: ' . $this->skipped;
    }
}
