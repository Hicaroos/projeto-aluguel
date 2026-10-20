<?php

use App\Models\Account;
use App\Models\Lease;
use App\Models\LeaseDocument;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('pages keep to the content policy, which lets in the inline scripts with its nonce', function () {
    // As with the built assets, even while the Vite dev server runs.
    Vite::useHotFile(storage_path('framework/testing/no-hot-file'));

    $response = $this->get(route('login'))->assertOk()->assertHeaderMissing('Content-Security-Policy-Report-Only');
    $policy = (string) $response->headers->get('Content-Security-Policy');

    expect($policy)
        ->toContain("default-src 'self'")
        ->toContain("connect-src 'self' https://viacep.com.br")
        ->toContain("object-src 'none'")
        ->toContain('report-uri /csp-report')
        ->toMatch("/script-src 'self' 'nonce-([^']+)'/");

    preg_match("/'nonce-([^']+)'/", $policy, $nonce);
    $response->assertSee('<script nonce="'.$nonce[1].'">', escape: false);
});

test('what the policy blocks is logged from the reports browsers send, which carry no csrf token', function () {
    Log::spy();

    $this->call('POST', '/csp-report', server: ['CONTENT_TYPE' => 'application/csp-report'], content: json_encode(['csp-report' => [
        'document-uri' => 'http://localhost/dashboard',
        'violated-directive' => 'script-src-elem',
        'blocked-uri' => 'https://cdn.example/x.js',
        'original-policy' => 'irrelevante',
    ]]))->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->with('Content security policy violation', [
        'document-uri' => 'http://localhost/dashboard',
        'violated-directive' => 'script-src-elem',
        'blocked-uri' => 'https://cdn.example/x.js',
    ]);
});

test('while the dev server runs, its assets load and only the rules that leave them alone apply', function () {
    $hotFile = storage_path('framework/testing/hot');
    File::ensureDirectoryExists(dirname($hotFile));
    File::put($hotFile, 'http://localhost:5173');
    Vite::useHotFile($hotFile);

    try {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
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
