<?php

namespace App\Http\Middleware;

use App\Models\Branch;
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
                    'manageAgency' => $request->user()?->can('manage-agency') ?? false,
                ],
            ],
            'branchSelector' => fn (): ?array => $this->branchSelector($request),
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
}
