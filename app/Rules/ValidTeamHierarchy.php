<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidTeamHierarchy implements ValidationRule
{
    protected $teamId;

    public function __construct($teamId = null)
    {
        $this->teamId = $teamId;
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if no parent_id is provided
        if (is_null($value)) {
            return;
        }

        // Prevent a team from being its own parent
        if ($this->teamId && $value == $this->teamId) {
            $fail('A team cannot be its own parent.');

            return;
        }

        // Prevent setting a descendant as the parent
        $team = Team::find($this->teamId);
        if ($team) {
            $descendants = collect();
            $team->getDescendants($team->id, $descendants);

            if ($descendants->contains($value)) {
                $fail('Cannot set a descendant as parent.');
            }
        }
    }
}
