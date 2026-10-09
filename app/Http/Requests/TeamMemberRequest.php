<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TeamMemberRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Check, before validating, that the member being updated may be changed by the user.
     */
    public function authorize(): bool
    {
        $member = $this->member();

        if ($member !== null) {
            Gate::authorize('update', $member);
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Administrators work in every branch; everybody else needs at least one branch.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->enum('role', Role::class);
        $needsBranches = $role !== null && ! $role->seesEveryBranch();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $this->member() === null ? $this->emailRules() : ['exclude'],
            'role' => ['required', Rule::enum(Role::class)],
            'branch_ids' => $needsBranches ? ['required', 'array', 'min:1'] : ['exclude'],
            'branch_ids.*' => ['integer', Rule::in($this->assignableBranchIds())],
        ];
    }

    /**
     * Get the branch ids the member will work in.
     *
     * @return array<int, int>
     */
    public function branchIds(): array
    {
        return array_map(intval(...), $this->validated('branch_ids', []));
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'role' => __('papel'),
            'branch_ids' => __('unidades'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'branch_ids.required' => __('Escolha pelo menos uma unidade.'),
            'branch_ids.min' => __('Escolha pelo menos uma unidade.'),
            'branch_ids.*.in' => __('Escolha unidades ativas da imobiliária.'),
        ];
    }

    /**
     * Get the active branches of the agency, plus the ones the member already works in.
     *
     * @return array<int, int>
     */
    private function assignableBranchIds(): array
    {
        $currentIds = $this->member()?->branches()->pluck('branches.id')->all() ?? [];

        return Branch::query()
            ->where(fn ($query) => $query->active()->orWhereIn('id', $currentIds))
            ->get(['id'])
            ->map(fn (Branch $branch): int => $branch->id)
            ->all();
    }

    /**
     * Get the team member being updated, if any.
     */
    private function member(): ?User
    {
        $member = $this->route('member');

        return $member instanceof User ? $member : null;
    }
}
