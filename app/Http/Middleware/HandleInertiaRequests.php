<?php

namespace App\Http\Middleware;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Owner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->load('account'),
                'can' => [
                    'manageAgency' => $request->user()?->can(Permission::ManageAgency->value) ?? false,
                    'manageRentals' => $request->user()?->can(Permission::ManageRentals->value) ?? false,
                    'registerReceipts' => $request->user()?->can(Permission::RegisterReceipts->value) ?? false,
                    'manageFinance' => $request->user()?->can(Permission::ManageFinance->value) ?? false,
                    'manageSales' => $request->user()?->can(Permission::ManageSales->value) ?? false,
                    'manageShowcase' => $request->user()?->can(Permission::ManageShowcase->value) ?? false,
                ],
                'isAccountOwner' => $request->user()?->isAccountOwner() ?? false,
            ],
            'branchSelector' => fn (): ?array => $this->branchSelector($request),
            'ownerSelector' => fn (): ?array => $this->ownerSelector($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'today' => today()->toDateString(),
            'isSimulatedToday' => CarbonImmutable::hasTestNow() && ! app()->runningUnitTests(),
        ];
    }

    /**
     * Get the branches an agency user may switch between and the one currently picked.
     *
     * @return array{branches: array<int, array{id: int, name: string}>, selectedId: int|null}|null
     */
    private function branchSelector(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->account?->isAgency()) {
            return null;
        }

        $selectedId = $user->selectedBranchId();

        return [
            'branches' => Branch::accessibleBy($user)
                ->where(fn (Builder $query) => $query->active()->when($selectedId, fn (Builder $query, int $id) => $query->orWhere('id', $id)))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Branch $branch): array => ['id' => $branch->id, 'name' => $branch->name])
                ->all(),
            'selectedId' => $selectedId,
        ];
    }

    /**
     * Get the owner picked in the owner selector, once the account has more than one owner to choose from.
     *
     * @return array{selected: array{id: int, name: string}|null}|null
     */
    private function ownerSelector(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || $user->account_id === null) {
            return null;
        }

        $selectedId = $user->selectedOwnerId();

        if ($selectedId === null && Owner::query()->count() < 2) {
            return null;
        }

        $selected = $selectedId !== null ? Owner::query()->find($selectedId, ['id', 'name']) : null;

        return ['selected' => $selected !== null ? ['id' => $selected->id, 'name' => $selected->name] : null];
    }
}
