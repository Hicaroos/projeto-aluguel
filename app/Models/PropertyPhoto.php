<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Concerns\BelongsToVisibleBranches;
use Database\Factories\PropertyPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $account_id
 * @property int $property_id
 * @property string $path
 * @property string|null $thumbnail_path
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url
 * @property-read string $thumbnail_url
 * @property-read Account $account
 * @property-read Property $property
 */
#[Fillable(['account_id', 'property_id', 'path', 'thumbnail_path', 'sort_order'])]
class PropertyPhoto extends Model
{
    /** @use HasFactory<PropertyPhotoFactory> */
    use BelongsToAccount, BelongsToVisibleBranches, HasFactory;

    /**
     * The most photos a single property can have.
     */
    public const int MAX_PER_PROPERTY = 20;

    /**
     * Photos are private files, served only to their own account.
     */
    public const string DISK = 'local';

    /**
     * @var list<string>
     */
    protected $hidden = ['path', 'thumbnail_path'];

    /**
     * @var list<string>
     */
    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Remove the stored files together with the photo.
     */
    protected static function booted(): void
    {
        static::deleted(function (PropertyPhoto $photo): void {
            Storage::disk(self::DISK)->delete(array_filter([$photo->path, $photo->thumbnail_path]));
        });
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(get: fn (): string => route('property-photos.show', $this));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::make(get: fn (): string => route('property-photos.thumbnail', $this));
    }

    /**
     * Limit the query to the photos of the properties of the given branches.
     *
     * @param  Builder<static>  $query
     * @param  array<int, int>  $branchIds
     */
    public static function restrictToBranches(Builder $query, array $branchIds): void
    {
        $query->whereIn($query->qualifyColumn('property_id'), Property::idsInBranches($branchIds));
    }
}
