<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
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
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Account $account
 */
#[Fillable(['account_id', 'name', 'body', 'is_default'])]
class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
