<?php

use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;

test('pages carry the security headers', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'")
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('pages report what the full policy would block, letting in the inline scripts with its nonce', function () {
    // As with the built assets, even while the Vite dev server runs.
    Vite::useHotFile(storage_path('framework/testing/no-hot-file'));

    $response = $this->get(route('login'))->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("connect-src 'self' https://viacep.com.br")
        ->toContain("object-src 'none'")
        ->toMatch("/script-src 'self' 'nonce-([^']+)'/");

    preg_match("/'nonce-([^']+)'/", $policy, $nonce);
    $response->assertSee('<script nonce="'.$nonce[1].'">', escape: false);
});

test('the dev server is left out of the full policy, which would report all of its assets', function () {
    $hotFile = storage_path('framework/testing/hot');
    File::ensureDirectoryExists(dirname($hotFile));
    File::put($hotFile, 'http://localhost:5173');
    Vite::useHotFile($hotFile);

    try {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
    } finally {
        File::delete($hotFile);
    }
});

test('over https browsers are told to keep to https', function () {
    $this->get('https://localhost/login')
        ->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('files keep the browser viewer working, without the page policy but never sniffed', function () {
    Storage::fake(LeaseDocument::DISK);
    $user = User::factory()->for(Account::factory(), 'account')->create();
    $document = LeaseDocument::factory()->for(Lease::factory()->for($user->account, 'account'))->create();
    Storage::disk(LeaseDocument::DISK)->put($document->path, '%PDF-1.4');

    $this->actingAs($user)->get(route('lease-documents.show', $document))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeaderMissing('Content-Security-Policy');
});

test('a response that sets its own policy keeps it', function () {
    // The underscore keeps the address out of the showcase routes, which take any slug right after the domain.
    Route::get('/_test/own-policy', fn () => response('ok')->header('Content-Security-Policy', "default-src 'none'"));

    $this->get('/_test/own-policy')->assertHeader('Content-Security-Policy', "default-src 'none'");
});
