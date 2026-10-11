<?php

namespace App\Http\Requests;

use App\Enums\SaleDocumentType;
use App\Models\Sale;
use App\Models\SaleDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SaleDocumentType::class)],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->sale()->documents()->count() >= SaleDocument::MAX_PER_SALE) {
                        $fail(__('Cada venda pode ter no máximo :max documentos.', ['max' => SaleDocument::MAX_PER_SALE]));
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

    private function sale(): Sale
    {
        /** @var Sale $sale */
        $sale = $this->route('sale');

        return $sale;
    }
}
