<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\LeaseDocumentType;
use Database\Factories\LeaseDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to a lease, such as the signed contract or an inspection report.
 *
 * @property int $id
 * @property int $account_id
 * @property int $lease_id
 * @property LeaseDocumentType $type
 * @property string $name The file name as uploaded.
 * @property string $path
 * @property string $mime_type
 * @property int $size In bytes.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url
 * @property-read string $download_url
 * @property-read Account $account
 * @property-read Lease $lease
 */
#[Fillable(['account_id', 'lease_id', 'type', 'name', 'path', 'mime_type', 'size'])]
class LeaseDocument extends Model
{
    /** @use HasFactory<LeaseDocumentFactory> */
    use BelongsToAccount, HasFactory;

    /**
     * The most documents a single lease can have.
     */
    public const int MAX_PER_LEASE = 30;

    /**
     * Documents are private files, served only to their own account.
     */
    public const string DISK = 'local';

    /**
     * @var list<string>
     */
    protected $hidden = ['path'];

    /**
     * @var list<string>
     */
    protected $appends = ['url', 'download_url'];

    /**
     * Remove the stored file together with the document.
     */
    protected static function booted(): void
    {
        static::deleted(function (LeaseDocument $document): void {
            Storage::disk(self::DISK)->delete($document->path);
        });
    }

    /**
     * @return BelongsTo<Lease, $this>
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(get: fn (): string => route('lease-documents.show', $this));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function downloadUrl(): Attribute
    {
        return Attribute::make(get: fn (): string => route('lease-documents.download', $this));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeaseDocumentType::class,
            'size' => 'integer',
        ];
    }
}
