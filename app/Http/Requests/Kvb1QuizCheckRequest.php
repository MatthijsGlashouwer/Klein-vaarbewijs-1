<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Kvb1QuizCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
        ];
    }
}
