<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ContractTemplateKind;
use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $account_id
 * @property string $name
 * @property string $body
 * @property ContractTemplateKind $kind
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 */
#[Fillable(['account_id', 'kind', 'name', 'body', 'is_default'])]
class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * @var array<string, string>
     */
    protected $attributes = [
        'kind' => 'lease',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ContractTemplateKind::class,
            'is_default' => 'boolean',
        ];
    }
}
