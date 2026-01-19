<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSpiritualLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'slug'       => 'required|string|max:255|unique:spiritual_lines,slug',
            'type'       => ['required', Rule::in(['orixa', 'linha', 'falange'])],
            'parent_id'  => 'nullable|exists:spiritual_lines,id',
            'icon'       => 'nullable|string|max:255',
            'color_hex'  => 'nullable|string|max:20',
            'status'     => ['required', Rule::in(['ativo', 'inativo'])],
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
