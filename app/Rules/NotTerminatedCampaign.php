<?php

namespace App\Rules;

use App\Models\Campaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates the campaign of an assignment: an assignment cannot be linked to a terminated campaign.
 * The campaign an assignment is already linked to (e.g. the one being edited) is always accepted,
 * so that the assignment can still be edited (and returned) after its campaign has ended.
 */
class NotTerminatedCampaign implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoredCampaignId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || (int) $value === $this->ignoredCampaignId) {
            return;
        }

        if (Campaign::find($value)?->isTerminated()) {
            $fail(__('validation.campaign.not_terminated'));
        }
    }
}
