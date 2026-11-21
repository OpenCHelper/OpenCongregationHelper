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
    | Unknown (`null`) until the layout is calibrated against the official form.
    */
    'expected_structure' => null,

    'layout' => [
        // Page of the template to fill (the first one is 1)
        'template_page' => 1,

        'font' => 'Helvetica',
        'font_size' => 8,
        // Text too wide for its box is shrunk down to this size
        'min_font_size' => 5,

        'service_year' => ['x' => 38, 'y' => 23, 'width' => 25, 'height' => 5],

        // Top of the first row of the table, and height of each row (both of its lines)
        'first_row_y' => 38.5,
        'row_height' => 11.6,
        'rows_per_page' => 20,

        'territory' => ['x' => 10, 'width' => 14],
        'last_completed' => ['x' => 24, 'width' => 20],

        // Left of the first "Assigned to" column, width of each one and how many there are in a row
        'assignments_x' => 44,
        'assignment_width' => 39,
        'assignments_per_row' => 4,

        // Space between the borders of the boxes and the text
        'padding' => 0.8,
    ],

];
