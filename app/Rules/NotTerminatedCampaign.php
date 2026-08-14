<?php

namespace App\Rules;

use Illuminate\Translation\PotentiallyTranslatedString;
use App\Models\Campaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotTerminatedCampaign implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param Closure(string, ?string=):PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value && $campaign = Campaign::find($value)) {
            if ($campaign->isTerminated()) {
                $fail(__('validation.campaign.not_terminated'));
            }
        }
    }
}
