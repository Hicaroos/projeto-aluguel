<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\SummarizeBranches;
use App\Actions\Leases\SyncLeasePayments;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManagementController extends Controller
{
    /**
     * Display the agency overview for its administrators: each branch side by side and the team.
     */
    public function __invoke(Request $request, SummarizeBranches $summarizeBranches, SyncLeasePayments $syncLeasePayments): Response
    {
        $accountId = $request->user()->account_id;

        Branch::viewing(null, fn () => $syncLeasePayments->handleMissingForAccount($accountId));

        $members = User::where('account_id', $accountId)->get();

        return Inertia::render('management/Index', [
            'agency' => $summarizeBranches->handle(null),
            'branches' => Branch::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'city', 'state', 'is_active'])
                ->map(fn (Branch $branch): array => [
                    ...$branch->only(['id', 'name', 'city', 'state', 'is_active']),
                    'summary' => $summarizeBranches->handle([$branch->id]),
                ])
                ->all(),
            'team' => [
                'active' => $members->filter(fn (User $member): bool => $member->isActive() && ! $member->hasPendingInvitation())->count(),
                'pending' => $members->filter(fn (User $member): bool => $member->isActive() && $member->hasPendingInvitation())->count(),
                'inactive' => $members->reject(fn (User $member): bool => $member->isActive())->count(),
                'recent' => $members
                    ->filter(fn (User $member): bool => $member->isActive() && ! $member->hasPendingInvitation())
                    ->sortByDesc(fn (User $member): int => $member->last_login_at?->getTimestamp() ?? 0)
                    ->take(5)
                    ->map(fn (User $member): array => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'role' => $member->role->label(),
                        'last_login_at' => $member->last_login_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
