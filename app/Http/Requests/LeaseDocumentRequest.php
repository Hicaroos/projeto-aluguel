<?php

namespace App\Http\Requests;

use App\Enums\LeaseDocumentType;
use App\Models\Lease;
use App\Models\LeaseDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaseDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(LeaseDocumentType::class)],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->lease()->documents()->count() >= LeaseDocument::MAX_PER_LEASE) {
                        $fail(__('Cada contrato pode ter no máximo :max documentos.', ['max' => LeaseDocument::MAX_PER_LEASE]));
                    }
                },
            ],
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
            'type' => __('tipo do documento'),
            'file' => __('arquivo'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => __('Envie um PDF ou uma imagem (JPG, PNG ou WebP).'),
            'file.uploaded' => __('O arquivo não pôde ser enviado. Verifique se ele tem até 10 MB.'),
        ];
    }

    private function lease(): Lease
    {
        /** @var Lease $lease */
        $lease = $this->route('lease');

        return $lease;
    }
}
