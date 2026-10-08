<?php

namespace App\Http\Requests;

use App\Models\Property;
use App\Models\PropertyPhoto;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PropertyPhotoRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * The browser resizes the photo and builds its thumbnail before uploading both.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->property()->photos()->count() >= PropertyPhoto::MAX_PER_PROPERTY) {
                        $fail(__('Cada imóvel pode ter no máximo :max fotos.', ['max' => PropertyPhoto::MAX_PER_PROPERTY]));
                    }
                },
            ],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'photo' => __('foto'),
            'thumbnail' => __('miniatura'),
        ];
    }

    private function property(): Property
    {
        /** @var Property $property */
        $property = $this->route('property');

        return $property;
    }
}
