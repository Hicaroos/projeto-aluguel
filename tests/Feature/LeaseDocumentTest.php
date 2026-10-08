<?php

use App\Enums\LeaseDocumentType;
use App\Enums\LeaseStatus;
use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake(LeaseDocument::DISK);
});

/**
 * @return array{user: User, lease: Lease}
 */
function documentScenario(array $leaseAttributes = []): array
{
    $account = Account::factory()->create();

    return [
        'user' => User::factory()->for($account, 'account')->create(),
        'lease' => Lease::factory()->for($account, 'account')->create($leaseAttributes),
    ];
}

function storedDocument(Lease $lease, array $attributes = []): LeaseDocument
{
    $path = "leases/{$lease->id}/".fake()->uuid().'.pdf';
    Storage::disk(LeaseDocument::DISK)->put($path, '%PDF-1.4');

    return LeaseDocument::factory()->for($lease)->create(['path' => $path, ...$attributes]);
}

test('a signed contract is stored privately with its original name, type and size', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario();

    $this->actingAs($user)
        ->from(route('leases.index', ['page' => 2]))
        ->post(route('leases.documents.store', $lease), [
            'type' => LeaseDocumentType::SignedContract->value,
            'file' => UploadedFile::fake()->create('Contrato assinado.pdf', 800, 'application/pdf'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('leases.index', ['page' => 2, 'show' => $lease->id]));

    $document = $lease->documents()->sole();

    expect($document->account_id)->toBe($lease->account_id)
        ->and($document->type)->toBe(LeaseDocumentType::SignedContract)
        ->and($document->name)->toBe('Contrato assinado.pdf')
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->size)->toBe(800 * 1024)
        ->and($document->path)->toStartWith("leases/{$lease->id}/");

    Storage::disk(LeaseDocument::DISK)->assertExists($document->path);
});

test('documents can be attached to finished leases too', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario(['status' => LeaseStatus::Ended]);

    $this->actingAs($user)
        ->post(route('leases.documents.store', $lease), [
            'type' => LeaseDocumentType::MoveOutInspection->value,
            'file' => UploadedFile::fake()->create('vistoria.jpg', 400, 'image/jpeg'),
        ])
        ->assertSessionHasNoErrors();

    expect($lease->documents()->sole()->type)->toBe(LeaseDocumentType::MoveOutInspection);
});

test('only PDFs and images up to 10 MB with a known type are accepted', function (array $payload, string $error) {
    ['user' => $user, 'lease' => $lease] = documentScenario();

    $this->actingAs($user)
        ->post(route('leases.documents.store', $lease), [
            'type' => LeaseDocumentType::SignedContract->value,
            ...$payload,
        ])
        ->assertSessionHasErrors($error);

    expect($lease->documents()->count())->toBe(0);
})->with([
    'word file' => [fn () => ['file' => UploadedFile::fake()->create('contrato.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')], 'file'],
    'too large' => [fn () => ['file' => UploadedFile::fake()->create('contrato.pdf', 11000, 'application/pdf')], 'file'],
    'unknown type' => [fn () => ['type' => 'receipt', 'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf')], 'type'],
]);

test('a lease can have at most 30 documents', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario();
    LeaseDocument::factory()->for($lease)->count(LeaseDocument::MAX_PER_LEASE)->create();

    $this->actingAs($user)
        ->post(route('leases.documents.store', $lease), [
            'type' => LeaseDocumentType::Other->value,
            'file' => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors(['file' => 'Cada contrato pode ter no máximo 30 documentos.']);
});

test('documents can only be attached to leases of the same account', function () {
    ['lease' => $lease] = documentScenario();

    $this->actingAs(User::factory()->create())
        ->post(route('leases.documents.store', $lease), [
            'type' => LeaseDocumentType::SignedContract->value,
            'file' => UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'),
        ])
        ->assertNotFound();
});

test('documents open inline or download with their original name, only for their own account', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario();
    $document = storedDocument($lease, ['name' => 'contrato-assinado.pdf']);

    $this->actingAs($user)
        ->get(route('lease-documents.show', $document))
        ->assertOk()
        ->assertHeader('content-disposition', 'inline; filename=contrato-assinado.pdf');

    $this->actingAs($user)
        ->get(route('lease-documents.download', $document))
        ->assertOk()
        ->assertDownload('contrato-assinado.pdf');

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('lease-documents.show', $document))->assertNotFound();
    $this->actingAs($intruder)->get(route('lease-documents.download', $document))->assertNotFound();
});

test('removing a document also deletes its file', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario();
    $document = storedDocument($lease);

    $this->actingAs($user)
        ->delete(route('lease-documents.destroy', $document))
        ->assertRedirect();

    $this->assertModelMissing($document);
    Storage::disk(LeaseDocument::DISK)->assertMissing($document->path);
});

test('documents of another account cannot be removed', function () {
    ['lease' => $lease] = documentScenario();
    $document = storedDocument($lease);

    $this->actingAs(User::factory()->create())
        ->delete(route('lease-documents.destroy', $document))
        ->assertNotFound();

    $this->assertModelExists($document);
});

test('the leases list sends each lease documents, newest first, without their storage path', function () {
    ['user' => $user, 'lease' => $lease] = documentScenario();
    $older = storedDocument($lease);
    $newer = storedDocument($lease, ['type' => LeaseDocumentType::MoveInInspection]);

    $this->actingAs($user)
        ->get(route('leases.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leases.data.0.documents', 2)
            ->where('leases.data.0.documents.0.id', $newer->id)
            ->where('leases.data.0.documents.0.type', 'move_in_inspection')
            ->where('leases.data.0.documents.0.download_url', route('lease-documents.download', $newer))
            ->where('leases.data.0.documents.1.id', $older->id)
            ->missing('leases.data.0.documents.0.path')
        );
});
