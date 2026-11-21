<?php

/*
|--------------------------------------------------------------------------
| Territory Assignment Record (S-13)
|--------------------------------------------------------------------------
|
| The S-13 is filled by writing the assignments on the official template (downloadable from JW Hub), uploaded by a super
| administrator. Every position and size is in millimetres, from the top-left corner of the page of the template.
|
| Each territory takes one row of the table, split in two lines: the assignee is written on the upper one, and the
| dates on which the territory was assigned and completed side by side on the lower one. A territory assigned more
| times than the columns of a row continues on the next row.
|
*/

return [

    'template' => [
        'disk' => env('S13_TEMPLATE_DISK', 'local'),
        'path' => env('S13_TEMPLATE_PATH', 'templates/S-13.pdf'),
    ],

    /*
    | The structure of the official form the layout was made for (see `App\Support\S13TemplateStructure::fromPdf()`):
    | an uploaded form with a different structure (e.g. a new edition) is reported to the users before it is used.
    | Measured on the S-13-I 1/22 (Italian); the other languages of the same edition share its table.
    */
    'expected_structure' => [
        'pages' => 1,
        'width' => 210.0,
        'height' => 297.0,
        'fields' => [],
        'grid' => [
            'columns' => [24, 65.5, 84.5, 103, 122, 141, 159.5, 178.5],
            'rows' => [
                34.5, 45.5, 49.5, 55, 60.5, 66, 71.5, 77, 82.5, 88, 93.5, 99, 104.5, 110, 115.5, 121, 127, 132.5, 138,
                143.5, 149, 154.5, 160, 165.5, 171, 176.5, 182, 187.5, 193, 198.5, 204, 209.5, 215, 220.5, 226, 231.5,
                237, 242.5, 248, 254, 259.5, 265,
            ],
        ],
    ],

    /*
    | Calibrated on the lines of the table of the S-13-I 1/22 (the PDF points of its drawing, converted to millimetres).
    */
    'layout' => [
        // Page of the template to fill (the first one is 1)
        'template_page' => 1,

        'font' => 'Helvetica',
        'font_size' => 8,
        // Text too long for its box is wrapped and shrunk down to this size, then cut
        'min_font_size' => 5,

        'service_year' => ['x' => 49.95, 'y' => 28.9, 'width' => 22.8, 'height' => 5.47, 'font_size' => 11],

        // Top of the first row of the table, and height of each row (both of its lines)
        'first_row_y' => 49.54,
        'row_height' => 11.035,
        'rows_per_page' => 20,

        'territory' => ['x' => 12.14, 'width' => 11.99],
        'last_completed' => ['x' => 24.13, 'width' => 22.51],

        // Left of the first "Assigned to" column, width of each one and how many there are in a row
        'assignments_x' => 46.64,
        'assignment_width' => 37.68,
        'assignments_per_row' => 4,

        // Space between the borders of the boxes and the text
        'padding' => 0.8,
    ],

];
