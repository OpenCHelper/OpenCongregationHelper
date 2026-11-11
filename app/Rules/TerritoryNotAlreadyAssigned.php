<?php

namespace App\Rules;

use App\Models\TerritoryAssignment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates the end date of an assignment: an assignment can be left open
 * (no end date) only if its territory has no other assignment in progress.
 */
class TerritoryNotAlreadyAssigned implements ValidationRule
{
    /**
     * Indicates whether the rule should be implicit (run also on empty values).
     *
     * @var bool
     */
    public $implicit = true;

    public function __construct(
        private readonly ?int $territoryId,
        private readonly ?int $ignoredAssignmentId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) || $this->territoryId === null) {
            return;
        }

        if (TerritoryAssignment::territoryHasOpenAssignment($this->territoryId, $this->ignoredAssignmentId)) {
            $fail(__('validation.territory_assignment.already_assigned'));
        }
    }
}
