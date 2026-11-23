<?php

namespace App\Support;

/**
 * Where the Territory Assignment Record (S-13) is written on the official form, and the structure of the form it was
 * made for: both depend on the edition of the form, the S-13 1/22.
 *
 * Every position and size is in millimetres, from the top-left corner of the page, calibrated on the lines of the
 * table of the S-13-I 1/22 (the PDF points of its drawing, converted to millimetres). Each territory takes one row of
 * the table, split in two lines: the assignee is written on the upper one, and the dates on which the territory was
 * assigned and completed side by side on the lower one.
 */
final class S13Layout
{
    /**
     * Where to write the record, as used by `TerritoryAssignmentRecordPdf`.
     *
     * @var array<string, mixed>
     */
    public const LAYOUT = [
        // Page of the form to fill (the first one is 1)
        'template_page' => 1,

        // TrueType font (embedded as a subset), in `resources/`, covering the scripts of the names: Liberation Sans
        // covers the Latin, Greek and Cyrillic ones, and has the same metrics as Helvetica, the font of the form
        'font' => 'fonts/LiberationSans-Regular.ttf',
        'font_size' => 8,
        // Text too long for its box is wrapped and shrunk down to this size, then cut
        'min_font_size' => 5,

        // Box of the service year, above its line (whose middle is at line_y): the box follows the line of the form
        'service_year' => ['x' => 49.95, 'y' => 28.9, 'width' => 22.8, 'height' => 5.47, 'font_size' => 11, 'line_y' => 34.55],

        // Top of the first row of the table, and height of each row (both of its lines)
        'first_row_y' => 49.54,
        'row_height' => 11.035,
        'rows_per_page' => 20,

        'territory' => ['x' => 12.14, 'width' => 11.99],
        'last_completed' => ['x' => 24.13, 'width' => 22.51],

        // Left of the first "Assigned to" column, width of each one and how many there are in a row (a territory
        // assigned more times shows only its latest assignments)
        'assignments_x' => 46.64,
        'assignment_width' => 37.68,
        'assignments_per_row' => 4,

        // Space between the borders of the boxes and the text
        'padding' => 0.8,
    ];

    /**
     * The structure of the form the layout was made for (see `S13TemplateStructure::fromPdf()`): an uploaded form with
     * a different structure (e.g. a new edition) is reported to the users before it is used. Measured on the S-13-I 1/22
     * (Italian); the other languages of the same edition share its table.
     *
     * @var array{pages: int, width: float, height: float, fields: list<string>, grid: array{columns: list<float>, rows: list<float>}}
     */
    public const EXPECTED_STRUCTURE = [
        'pages' => 1,
        'width' => 210.0,
        'height' => 297.0,
        'fields' => [],
        'grid' => [
            'columns' => [24.0, 65.5, 84.5, 103.0, 122.0, 141.0, 159.5, 178.5],
            'rows' => [
                34.5, 45.5, 49.5, 55.0, 60.5, 66.0, 71.5, 77.0, 82.5, 88.0, 93.5, 99.0, 104.5, 110.0, 115.5, 121.0, 127.0,
                132.5, 138.0, 143.5, 149.0, 154.5, 160.0, 165.5, 171.0, 176.5, 182.0, 187.5, 193.0, 198.5, 204.0, 209.5,
                215.0, 220.5, 226.0, 231.5, 237.0, 242.5, 248.0, 254.0, 259.5, 265.0,
            ],
        ],
    ];
}
