<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSpiritualLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $lineId = $this->route('line')?->id;

        return [
            'name'       => 'sometimes|required|string|max:255',
            'slug'       => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('spiritual_lines', 'slug')->ignore($lineId),
            ],
            'type'       => ['sometimes', 'required', Rule::in(['orixa', 'linha', 'falange'])],
            'parent_id'  => 'sometimes|nullable|exists:spiritual_lines,id',
            'icon'       => 'nullable|string|max:255',
            'color_hex'  => 'nullable|string|max:20',
            'status'     => ['sometimes', 'required', Rule::in(['ativo', 'inativo'])],
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
