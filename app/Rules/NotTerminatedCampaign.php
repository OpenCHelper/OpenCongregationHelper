<?php

namespace App\Rules;

use App\Models\Campaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class NotTerminatedCampaign implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=):PotentiallyTranslatedString  $fail
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
