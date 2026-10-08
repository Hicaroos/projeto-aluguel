<?php

namespace App\Http\Controllers;

use App\Http\Requests\PropertyPhotoRequest;
use App\Models\Property;
use App\Models\PropertyPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyPhotoController extends Controller
{
    /**
     * Serve the photo in full size.
     */
    public function show(PropertyPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $photo);

        return $this->serve($photo->path);
    }

    /**
     * Serve the photo thumbnail, falling back to the full size photo.
     */
    public function thumbnail(PropertyPhoto $photo): StreamedResponse
    {
        Gate::authorize('view', $photo);

        return $this->serve($photo->thumbnail_path ?? $photo->path);
    }

    /**
     * Add a photo to the given property, after the existing ones.
     */
    public function store(PropertyPhotoRequest $request, Property $property): RedirectResponse
    {
        Gate::authorize('update', $property);

        $directory = "properties/{$property->id}";

        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        $thumbnail = $request->file('thumbnail');

        $property->photos()->create([
            'account_id' => $property->account_id,
            'path' => $photo->store($directory, PropertyPhoto::DISK),
            'thumbnail_path' => $thumbnail instanceof UploadedFile ? $thumbnail->store($directory, PropertyPhoto::DISK) : null,
            'sort_order' => (int) $property->photos()->max('sort_order') + 1,
        ]);

        return $this->backToProperty($property);
    }

    /**
     * Make the given photo the property's cover, moving it to the front.
     */
    public function makeCover(PropertyPhoto $photo): RedirectResponse
    {
        Gate::authorize('update', $photo);

        DB::transaction(function () use ($photo): void {
            $photo->property->photos()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->sortBy(fn (PropertyPhoto $candidate): int => $candidate->is($photo) ? 0 : 1)
                ->values()
                ->each(fn (PropertyPhoto $candidate, int $index) => $candidate->update(['sort_order' => $index]));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foto de capa atualizada.')]);

        return $this->backToProperty($photo->property);
    }

    /**
     * Remove the given photo and its files.
     */
    public function destroy(PropertyPhoto $photo): RedirectResponse
    {
        Gate::authorize('delete', $photo);

        $photo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foto removida.')]);

        return $this->backToProperty($photo->property);
    }

    private function serve(string $path): StreamedResponse
    {
        abort_unless(Storage::disk(PropertyPhoto::DISK)->exists($path), 404);

        return Storage::disk(PropertyPhoto::DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }

    /**
     * Go back to the previous page with the property details open, so they show the
     * updated photos even when the property is not on the current page of the list.
     */
    private function backToProperty(Property $property): RedirectResponse
    {
        return redirect()->to((string) Uri::of(url()->previous())->withQuery(['show' => $property->id]));
    }
}
