<?php

namespace App\Support;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\PdfParser;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfHexString;
use setasign\Fpdi\PdfParser\Type\PdfString;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;
use Throwable;

/**
 * The structure of an S-13 template: what the record relies on to be written in the right places.
 *
 * The official form is published (and occasionally revised) on JW Hub, so it is uploaded by the users instead of being
 * shipped with the application. Its structure is compared with the one the layout of `config/s13.php` was made for, so
 * that a revised form is noticed before a misaligned record is printed.
 */
class S13TemplateStructure
{
    /**
     * Where the changes of the structure are reported.
     */
    public const ISSUES_URL = 'https://github.com/OpenCHelper/OpenCongregationHelper/issues/new';

    /**
     * How far (in millimetres) a line of the table may be from where it is expected: the positions are measured and
     * rounded to the half millimetre, so a line near a rounding edge may land on either side of it.
     */
    public const GRID_TOLERANCE = 0.5;

    /**
     * Read the structure of a PDF: its pages, the size of the page that is filled (in millimetres, rounded), the names
     * of its form fields, sorted, and the lines of its table.
     *
     * @return array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}
     */
    public static function fromPdf(string $contents): array
    {
        $pdf = new Fpdi;
        $pages = $pdf->setSourceFile(StreamReader::createByString($contents));
        $page = min($pages, (int) config('s13.layout.template_page'));
        $size = $pdf->getTemplateSize($pdf->importPage($page));

        return [
            'pages' => $pages,
            'width' => round($size['width']),
            'height' => round($size['height']),
            'fields' => self::fieldNames($contents),
            'grid' => self::grid($contents, $page, $size['height']),
        ];
    }

    /**
     * The structure the layout was made for, if known.
     *
     * @return array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}|null
     */
    public static function expected(): ?array
    {
        return config('s13.expected_structure');
    }

    /**
     * Describe how a structure differs from the expected one (none while the expected one is not known).
     *
     * @param  array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}  $structure
     * @return list<string>
     */
    public static function differences(array $structure): array
    {
        $expected = self::expected();

        if ($expected === null) {
            return [];
        }

        $differences = [];

        if ($structure['pages'] !== $expected['pages']) {
            $differences[] = __('s13.structure.pages', ['expected' => $expected['pages'], 'actual' => $structure['pages']]);
        }

        if ($structure['width'] !== (float) $expected['width'] || $structure['height'] !== (float) $expected['height']) {
            $differences[] = __('s13.structure.size', [
                'expected' => "{$expected['width']} × {$expected['height']} mm",
                'actual' => "{$structure['width']} × {$structure['height']} mm",
            ]);
        }

        $missing = array_values(array_diff($expected['fields'], $structure['fields']));
        $added = array_values(array_diff($structure['fields'], $expected['fields']));

        if ($missing !== []) {
            $differences[] = __('s13.structure.missing_fields', ['fields' => self::summarize($missing)]);
        }

        if ($added !== []) {
            $differences[] = __('s13.structure.added_fields', ['fields' => self::summarize($added)]);
        }

        if (isset($expected['grid']) && ! self::gridsMatch($structure['grid'], $expected['grid'])) {
            $differences[] = __('s13.structure.grid');
        }

        return $differences;
    }

    /**
     * Whether two tables have the same lines, each within the tolerance of its counterpart.
     *
     * @param  array{columns: list<int|float>, rows: list<int|float>}  $grid
     * @param  array{columns: list<int|float>, rows: list<int|float>}  $expected
     */
    public static function gridsMatch(array $grid, array $expected): bool
    {
        foreach (['columns', 'rows'] as $lines) {
            if (count($grid[$lines]) !== count($expected[$lines])) {
                return false;
            }

            foreach (array_map(null, $grid[$lines], $expected[$lines]) as [$position, $expectedPosition]) {
                if (abs((float) $position - (float) $expectedPosition) > self::GRID_TOLERANCE + 1e-9) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * The link to a new issue, pre-filled with the structure of the template and its differences from the expected one.
     *
     * @param  array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}  $structure
     * @param  list<string>  $differences
     */
    public static function issueUrl(array $structure, array $differences, ?string $fileName = null): string
    {
        $body = implode("\n", [
            'The structure of the S-13 template downloaded from JW Hub differs from the one the S-13 layout (`config/s13.php`) was made for.',
            '',
            '**Template**: '.($fileName ?? 'S-13.pdf'),
            '**Application version**: '.config('app.version', 'unknown'),
            '',
            '### Differences',
            ...array_map(fn (string $difference): string => "- {$difference}", $differences),
            '',
            '### Structure of the template',
            '```json',
            json_encode($structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            '```',
            '',
            '<!-- Please attach the template (PDF), so that the layout can be updated. -->',
        ]);

        return self::ISSUES_URL.'?'.http_build_query([
            'title' => 'S-13 template changed',
            'labels' => 'enhancement',
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The lines of the table of a page, which the layout is aligned to: where its vertical lines are from the left
     * border of the page, and its horizontal ones from the top, in millimetres (rounded to the half millimetre).
     *
     * The forms draw their lines as thin filled rectangles, which do not depend on the language of the form.
     *
     * @return array{columns: list<float>, rows: list<float>}
     */
    protected static function grid(string $contents, int $page, float $pageHeight): array
    {
        try {
            $stream = (new PdfReader(new PdfParser(StreamReader::createByString($contents))))->getPage($page)->getContentStream();
        } catch (Throwable) {
            return ['columns' => [], 'rows' => []];
        }

        $millimetres = fn (float $points): float => round($points * 25.4 / 72 * 2) / 2;
        $columns = [];
        $rows = [];

        preg_match_all('/(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+re\s+[fF]\*?(?![\w])/', $stream, $rectangles, PREG_SET_ORDER);

        foreach ($rectangles as [, $x, $y, $width, $height]) {
            [$x, $y, $width, $height] = [(float) $x, (float) $y, abs((float) $width), abs((float) $height)];

            if ($width < 2 && $height > 5) {
                $columns[] = $millimetres($x + $width / 2);
            } elseif ($height < 2 && $width > 5) {
                $rows[] = round(($pageHeight - $millimetres($y + $height / 2)) * 2) / 2;
            }
        }

        $columns = array_values(array_unique($columns, SORT_REGULAR));
        $rows = array_values(array_unique($rows, SORT_REGULAR));
        sort($columns);
        sort($rows);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * The fully qualified names of the form fields of a PDF (none if it is not a form), sorted.
     *
     * @return list<string>
     */
    protected static function fieldNames(string $contents): array
    {
        $parser = new PdfParser(StreamReader::createByString($contents));
        $acroForm = PdfType::resolve(PdfDictionary::get($parser->getCatalog(), 'AcroForm'), $parser);

        if (! $acroForm instanceof PdfDictionary) {
            return [];
        }

        $fields = PdfType::resolve(PdfDictionary::get($acroForm, 'Fields'), $parser);
        $names = $fields instanceof PdfArray ? self::collectFieldNames($fields, $parser, '') : [];
        sort($names, SORT_NATURAL);

        return array_values(array_unique($names));
    }

    /**
     * Collect the names of the terminal fields of a field tree, joined by dots as in the PDF specification.
     *
     * @return list<string>
     */
    protected static function collectFieldNames(PdfArray $fields, PdfParser $parser, string $prefix, int $depth = 0): array
    {
        $names = [];

        foreach ($fields->value as $reference) {
            $field = PdfType::resolve($reference, $parser);

            if (! $field instanceof PdfDictionary || $depth > 10) {
                continue;
            }

            $partialName = PdfType::resolve(PdfDictionary::get($field, 'T'), $parser);
            $name = match (true) {
                $partialName instanceof PdfString => self::decodeText(PdfString::unescape($partialName->value)),
                $partialName instanceof PdfHexString => self::decodeText((string) hex2bin($partialName->value)),
                default => null,
            };
            $fullName = $name === null ? $prefix : ltrim("{$prefix}.{$name}", '.');

            $kids = PdfType::resolve(PdfDictionary::get($field, 'Kids'), $parser);
            $childNames = $kids instanceof PdfArray ? self::collectFieldNames($kids, $parser, $fullName, $depth + 1) : [];

            array_push($names, ...($childNames !== [] ? $childNames : ($fullName !== '' ? [$fullName] : [])));
        }

        return $names;
    }

    /**
     * Decode a PDF text string: UTF-16 when it starts with its byte order mark, PDFDocEncoding (Latin-1 alike) else.
     */
    protected static function decodeText(string $text): string
    {
        if (str_starts_with($text, "\xFE\xFF")) {
            return mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        }

        return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1');
    }

    /**
     * List some items, and how many more there are.
     *
     * @param  list<string>  $items
     */
    protected static function summarize(array $items, int $limit = 10): string
    {
        $listed = implode(', ', array_slice($items, 0, $limit));

        return count($items) > $limit ? $listed.' '.__('s13.structure.and_more', ['count' => count($items) - $limit]) : $listed;
    }
}
