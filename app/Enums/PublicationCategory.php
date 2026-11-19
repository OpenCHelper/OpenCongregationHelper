<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * The categories of the publications that can be received on paper, as in the Watchtower Online Library.
 *
 * @see https://wol.jw.org/en/wol/library/r1/lp-e/all-publications
 */
enum PublicationCategory: string implements HasLabel
{
    // Change the order of the cases to update the order on the UI
    case Bibles = 'bibles';
    case Watchtower = 'watchtower';
    case Awake = 'awake';
    case MeetingWorkbooks = 'meeting_workbooks';
    case Books = 'books';
    case BrochuresAndBooklets = 'brochures_and_booklets';
    case Tracts = 'tracts';
    case Indexes = 'indexes';
    case Programs = 'programs';
    case ManualsAndGuidelines = 'manuals_and_guidelines';
    case Other = 'other';

    public function getLabel(): string
    {
        return __("filament-resources.publication.categories.{$this->value}");
    }
}
