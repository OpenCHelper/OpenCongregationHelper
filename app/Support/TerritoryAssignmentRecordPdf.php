<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * The Territory Assignment Record (S-13) of a service year, written on the official template.
 *
 * The template is the PDF downloadable from JW Hub, uploaded by a super administrator; where the data is written on it
 * is configured in `config/s13.php`.
 */
class TerritoryAssignmentRecordPdf
{
    /**
     * Whether the template was uploaded.
     */
    public static function hasTemplate(): bool
    {
        return self::disk()->exists(self::templatePath());
    }

    /**
     * Store the contents of a template, replacing the current one.
     */
    public static function storeTemplate(string $contents): void
    {
        self::disk()->put(self::templatePath(), $contents);
    }

    /**
     * The contents of the uploaded template.
     */
    public static function template(): string
    {
        return (string) self::disk()->get(self::templatePath());
    }

    /**
     * Whether the given contents are a PDF that can be used as a template.
     */
    public static function isValidTemplate(string $contents): bool
    {
        try {
            $pdf = new Fpdi;

            return $pdf->setSourceFile(StreamReader::createByString($contents)) >= (int) config('s13.layout.template_page');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The name of the file of the record of the given service year.
     */
    public static function fileName(int $serviceYear): string
    {
        return "S-13_{$serviceYear}.pdf";
    }

    /**
     * Write the record of the given service year on the template, one page of the template for each page of rows.
     *
     * @return string The contents of the PDF
     */
    public static function render(int $serviceYear): string
    {
        /** @var array<string, mixed> $layout */
        $layout = config('s13.layout');

        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0);
        $pdf->setSourceFile(StreamReader::createByString(self::template()));
        $templateId = $pdf->importPage((int) $layout['template_page']);
        $templateSize = $pdf->getTemplateSize($templateId);

        $pages = self::lines(TerritoryAssignmentRecord::rows($serviceYear), (int) $layout['assignments_per_row'])
            ->chunk((int) $layout['rows_per_page']);

        foreach ($pages->whenEmpty(fn ($pages) => $pages->push(collect())) as $lines) {
            $pdf->AddPage($templateSize['orientation'], [$templateSize['width'], $templateSize['height']]);
            $pdf->useTemplate($templateId);
            $pdf->SetFont($layout['font'], '', $layout['font_size']);

            $box = $layout['service_year'];
            self::write($pdf, (string) $serviceYear, $box['x'], $box['y'], $box['width'], $box['height'], $layout);

            foreach ($lines->values() as $index => $line) {
                self::writeLine($pdf, $line, $layout['first_row_y'] + $index * $layout['row_height'], $layout);
            }
        }

        return $pdf->Output('S');
    }

    /**
     * Split the rows of the record in the lines of the table: a territory assigned more times than the columns of a
     * line continues on the next one.
     *
     * @param  iterable<int, array{territory: string, last_completed_at: ?Carbon, assignments: list<array{assignee: string, assigned_at: Carbon, completed_at: ?Carbon}>}>  $rows
     * @return Collection<int, array{territory: string, last_completed_at: ?Carbon, assignments: list<array{assignee: string, assigned_at: Carbon, completed_at: ?Carbon}>}>
     */
    public static function lines(iterable $rows, int $assignmentsPerLine): Collection
    {
        return collect($rows)->flatMap(function (array $row) use ($assignmentsPerLine): array {
            $chunks = array_chunk($row['assignments'], $assignmentsPerLine) ?: [[]];

            return array_map(fn (array $assignments, int $index): array => [
                'territory' => $row['territory'],
                'last_completed_at' => $index === 0 ? $row['last_completed_at'] : null,
                'assignments' => $assignments,
            ], $chunks, array_keys($chunks));
        })->values();
    }

    /**
     * Write a line of the table, whose top is at the given position.
     *
     * @param  array{territory: string, last_completed_at: ?Carbon, assignments: list<array{assignee: string, assigned_at: Carbon, completed_at: ?Carbon}>}  $line
     * @param  array<string, mixed>  $layout
     */
    protected static function writeLine(Fpdi $pdf, array $line, float $top, array $layout): void
    {
        $height = $layout['row_height'];
        $halfHeight = $height / 2;

        self::write($pdf, $line['territory'], $layout['territory']['x'], $top, $layout['territory']['width'], $height, $layout);
        self::write($pdf, self::formatDate($line['last_completed_at']), $layout['last_completed']['x'], $top, $layout['last_completed']['width'], $height, $layout);

        foreach ($line['assignments'] as $column => $assignment) {
            $left = $layout['assignments_x'] + $column * $layout['assignment_width'];
            $halfWidth = $layout['assignment_width'] / 2;

            self::write($pdf, $assignment['assignee'], $left, $top, $layout['assignment_width'], $halfHeight, $layout);
            self::write($pdf, self::formatDate($assignment['assigned_at']), $left, $top + $halfHeight, $halfWidth, $halfHeight, $layout);
            self::write($pdf, self::formatDate($assignment['completed_at']), $left + $halfWidth, $top + $halfHeight, $halfWidth, $halfHeight, $layout);
        }
    }

    /**
     * Write a text centred in a box, shrinking it when it is too wide.
     *
     * @param  array<string, mixed>  $layout
     */
    protected static function write(Fpdi $pdf, string $text, float $x, float $y, float $width, float $height, array $layout): void
    {
        if ($text === '') {
            return;
        }

        // The core fonts of FPDF are encoded in Windows-1252
        $text = (string) iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        $availableWidth = $width - 2 * $layout['padding'];
        $fontSize = $layout['font_size'];

        while ($fontSize > $layout['min_font_size'] && $pdf->GetStringWidth($text) > $availableWidth) {
            $pdf->SetFontSize(--$fontSize);
        }

        $pdf->SetXY($x + $layout['padding'], $y);
        $pdf->Cell($availableWidth, $height, $text, 0, 0, 'C');
        $pdf->SetFontSize($layout['font_size']);
    }

    protected static function formatDate(?Carbon $date): string
    {
        return $date?->locale(app()->getLocale())->isoFormat('L') ?? '';
    }

    protected static function disk(): Filesystem
    {
        return Storage::disk(config('s13.template.disk'));
    }

    protected static function templatePath(): string
    {
        return config('s13.template.path');
    }
}
