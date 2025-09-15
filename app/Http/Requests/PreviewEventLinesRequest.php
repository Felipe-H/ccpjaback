<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewEventLinesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required','array','min:1'],
            'lines.*.line_id' => ['required','integer','distinct'],
            'options.expand_hierarchy' => ['nullable','in:auto'],
            'options.include_inactive' => ['nullable','boolean'],
            'options.dedup_strategy' => ['nullable','in:sum'],
            'options.only_required' => ['nullable','boolean'],
        ];
    }

    public function authorize(): bool
    {
        return true; // permissões ficam para depois
    }

    public function includeInactive(): bool
    {
        return (bool)($this->input('options.include_inactive', false));
    }

    public function onlyRequired(): bool
    {
        return (bool)($this->input('options.only_required', false));
    }

    /** @return array<int> */
    public function lineIds(): array
    {
        return collect($this->input('lines', []))
            ->pluck('line_id')->filter()->unique()->values()->all();
    }
}
