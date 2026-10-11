<?php

namespace App\Http\Requests;

use App\Enums\ExpenseDocumentType;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ExpenseDocumentType::class)],
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($this->expense()->documents()->count() >= ExpenseDocument::MAX_PER_EXPENSE) {
                        $fail(__('Cada despesa pode ter no máximo :max documentos.', ['max' => ExpenseDocument::MAX_PER_EXPENSE]));
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

    private function expense(): Expense
    {
        /** @var Expense $expense */
        $expense = $this->route('expense');

        return $expense;
    }
}
