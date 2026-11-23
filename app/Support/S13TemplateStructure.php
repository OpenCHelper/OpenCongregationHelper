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
 * shipped with the application. Its structure is compared with the one the layout of `S13Layout` was made for, so
 * that a revised form is noticed before a misaligned record is printed.
 */
class S13TemplateStructure
{
    /**
     * Where the changes of the structure are reported.
     */
    public const ISSUES_URL = 'https://github.com/OpenCHelper/OpenCongregationHelper/issues/new';

    /**
     * How far (in millimetres) a line of the table may be from where it is expected, once the whole table is moved: the
     * positions are measured and rounded to the half millimetre, so a line near a rounding edge may land on either side
     * of it.
     */
    public const GRID_TOLERANCE = 0.5;

    /**
     * How far (in millimetres) the table may be moved: less than a line of a row, so that a table with a line more or
     * less is not taken for a moved one.
     */
    public const MAX_OFFSET = 4.0;

    /**
     * Read the structure of a PDF: its pages, the size of the page that is filled (in millimetres, rounded), the names
     * of its form fields, sorted, and the lines of its table.
     *
     * @return array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}
     */
    public static function fromPdf(string $contents): array
    {
        $contents = ClassicPdf::from($contents);
        $pdf = new Fpdi;
        $pages = $pdf->setSourceFile(StreamReader::createByString($contents));
        $page = min($pages, (int) S13Layout::LAYOUT['template_page']);
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
     * Describe how a structure differs from the expected one (by default, the one the layout was made for).
     *
     * @param  array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}  $structure
     * @param  array{pages: int, width: int|float, height: int|float, fields: list<string>, grid: array{columns: list<int|float>, rows: list<int|float>}}|null  $expected
     * @return list<string>
     */
    public static function differences(array $structure, ?array $expected = null): array
    {
        $expected ??= S13Layout::EXPECTED_STRUCTURE;

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

        if (self::offset($structure['grid'], $expected['grid']) === null) {
            $differences[] = __('s13.structure.grid');
        }

        return $differences;
    }

    /**
     * How far the table of a form is from where the layout expects it, as [x, y] in millimetres (null if the table has
     * not the same shape: other columns, rows or sizes).
     *
     * The table is compared line by line: the columns, and the lines of the rows of territories (the last ones of the
     * page; the lines of the header change with the language of the form). The same edition of the form in another
     * language has the same table, only moved by a millimetre or two, which the layout follows.
     *
     * @param  array{columns: list<int|float>, rows: list<int|float>}  $grid
     * @param  array{columns: list<int|float>, rows: list<int|float>}|null  $expected
     * @return array{x: float, y: float}|null
     */
    public static function offset(array $grid, ?array $expected = null): ?array
    {
        $expected ??= S13Layout::EXPECTED_STRUCTURE['grid'];
        $bodyLines = min(2 * (int) S13Layout::LAYOUT['rows_per_page'], count($expected['rows']));

        if (count($grid['columns']) !== count($expected['columns']) || count($grid['rows']) < $bodyLines) {
            return null;
        }

        $x = self::uniformShift($grid['columns'], $expected['columns']);
        $y = self::uniformShift(array_slice($grid['rows'], -$bodyLines), array_slice($expected['rows'], -$bodyLines));

        if ($x === null || $y === null || abs($x) > self::MAX_OFFSET || abs($y) > self::MAX_OFFSET) {
            return null;
        }

        return ['x' => $x, 'y' => $y];
    }

    /**
     * The shift that moves each expected position onto the actual one, if they are all moved alike (within the
     * tolerance).
     *
     * @param  list<int|float>  $positions
     * @param  list<int|float>  $expected
     */
    protected static function uniformShift(array $positions, array $expected): ?float
    {
        if ($positions === []) {
            return 0.0;
        }

        $shifts = array_map(fn (int|float $position, int|float $expectedPosition): float => (float) $position - (float) $expectedPosition, $positions, $expected);
        sort($shifts);
        $median = $shifts[intdiv(count($shifts), 2)];

        foreach ($shifts as $shift) {
            if (abs($shift - $median) > self::GRID_TOLERANCE + 1e-9) {
                return null;
            }
        }

        return $median;
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
            'The structure of the S-13 template downloaded from JW Hub differs from the one the S-13 layout (`S13Layout`) was made for.',
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
        $halfMillimetre = fn (float $millimetres): float => round($millimetres * 2) / 2;
        $columns = [];
        $rows = [];

        foreach (self::lines($contents, $page, $pageHeight) as $line) {
            if ($line['vertical']) {
                $columns[] = $halfMillimetre($line['x']);
            } else {
                $rows[] = $halfMillimetre($line['y']);
            }
        }

        $columns = array_values(array_unique($columns, SORT_REGULAR));
        $rows = array_values(array_unique($rows, SORT_REGULAR));
        sort($columns);
        sort($rows);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * The line on which the service year is written: the topmost horizontal line of the page, as its left end, width
     * and height from the top, in millimetres (null if the page has no line).
     *
     * @return array{x: float, width: float, y: float}|null
     */
    public static function serviceYearLine(string $contents): ?array
    {
        $contents = ClassicPdf::from($contents);

        try {
            $pdf = new Fpdi;
            $pages = $pdf->setSourceFile(StreamReader::createByString($contents));
            $page = min($pages, (int) S13Layout::LAYOUT['template_page']);
            $pageHeight = $pdf->getTemplateSize($pdf->importPage($page))['height'];
        } catch (Throwable) {
            return null;
        }

        $topmost = collect(self::lines($contents, $page, $pageHeight))
            ->reject(fn (array $line): bool => $line['vertical'])
            ->sortBy('y')
            ->first();

        return $topmost === null ? null : ['x' => $topmost['x'], 'width' => $topmost['length'], 'y' => $topmost['y']];
    }

    /**
     * The lines of a page: the forms draw them as thin filled rectangles, which do not depend on the language of the
     * form. Each line is given by its middle (x for a vertical line, y for a horizontal one, from the top), its start (y
     * for a vertical line, x for a horizontal one) and its length, in millimetres.
     *
     * @return list<array{vertical: bool, x: float, y: float, length: float}>
     */
    protected static function lines(string $contents, int $page, float $pageHeight): array
    {
        try {
            $stream = (new PdfReader(new PdfParser(StreamReader::createByString($contents))))->getPage($page)->getContentStream();
        } catch (Throwable) {
            return [];
        }

        $millimetres = fn (float $points): float => $points * 25.4 / 72;
        $lines = [];

        preg_match_all('/(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+re\s+[fF]\*?(?![\w])/', $stream, $rectangles, PREG_SET_ORDER);

        foreach ($rectangles as [, $x, $y, $width, $height]) {
            [$x, $y, $width, $height] = [(float) $x, (float) $y, (float) $width, (float) $height];
            // Normalise rectangles drawn with a negative width or height
            [$x, $width] = $width < 0 ? [$x + $width, -$width] : [$x, $width];
            [$y, $height] = $height < 0 ? [$y + $height, -$height] : [$y, $height];

            if ($width < 2 && $height > 5) {
                $lines[] = ['vertical' => true, 'x' => $millimetres($x + $width / 2), 'y' => $pageHeight - $millimetres($y + $height), 'length' => $millimetres($height)];
            } elseif ($height < 2 && $width > 5) {
                $lines[] = ['vertical' => false, 'x' => $millimetres($x), 'y' => $pageHeight - $millimetres($y + $height / 2), 'length' => $millimetres($width)];
            }
        }

        return $lines;
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
