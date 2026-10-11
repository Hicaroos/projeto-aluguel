<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\DealOutcome;
use Database\Factories\DealStageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A column of the account's deal funnel. Open stages are the account's own; the won and lost
 * stages close the funnel and always exist.
 *
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property DealOutcome|null $outcome
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 * @property-read Collection<int, Deal> $deals
 */
#[Fillable(['account_id', 'name', 'outcome', 'sort_order'])]
class DealStage extends Model
{
    /** @use HasFactory<DealStageFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * The stages every account starts with: the open ones, then the won and lost ones.
     *
     * @var array<int, array{name: string, outcome: DealOutcome|null}>
     */
    public const array DEFAULTS = [
        ['name' => 'Novo contato', 'outcome' => null],
        ['name' => 'Visita', 'outcome' => null],
        ['name' => 'Proposta', 'outcome' => null],
        ['name' => 'Documentação', 'outcome' => null],
        ['name' => 'Fechado', 'outcome' => DealOutcome::Won],
        ['name' => 'Perdido', 'outcome' => DealOutcome::Lost],
    ];

    /**
     * @return HasMany<Deal, $this>
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Scope the query to the funnel order: open stages first, then won, then lost.
     *
     * @param  Builder<DealStage>  $query
     */
    public function scopeInFunnelOrder(Builder $query): void
    {
        $query->orderByRaw("CASE outcome WHEN 'won' THEN 1 WHEN 'lost' THEN 2 ELSE 0 END")
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Scope the query to the stages deals move through before closing.
     *
     * @param  Builder<DealStage>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('outcome');
    }

    /**
     * Determine whether deals in this stage are still being negotiated.
     */
    public function isOpen(): bool
    {
        return $this->outcome === null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => DealOutcome::class,
        ];
    }
}
