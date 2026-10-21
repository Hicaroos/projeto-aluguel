<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OwnerPayoutReceiptRequest extends FormRequest
{
    /**
     * The rules of the proof of transfer, also used when it comes along with the payout.
     *
     * @var list<string>
     */
    public const array RECEIPT_RULES = ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receipt' => ['required', ...self::RECEIPT_RULES],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::receiptAttributes();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::receiptMessages();
    }

    /**
     * @return array<string, string>
     */
    public static function receiptAttributes(): array
    {
        return ['receipt' => __('comprovante')];
    }

    /**
     * @return array<string, string>
     */
    public static function receiptMessages(): array
    {
        return [
            'receipt.mimes' => __('Envie um PDF ou uma imagem (JPG, PNG ou WebP).'),
            'receipt.uploaded' => __('O arquivo não pôde ser enviado. Verifique se ele tem até 10 MB.'),
        ];
    }
}
