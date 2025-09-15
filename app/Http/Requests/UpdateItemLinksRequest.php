<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    public function rules(): array
    {
        return [
            'detach_missing' => ['sometimes','boolean'],

            'lines'   => ['sometimes','array'],
            'lines.*.line_id'       => ['required','integer','exists:spiritual_lines,id'],
            'lines.*.purpose'       => ['nullable','string','max:64'],
            'lines.*.suggested_qty' => ['nullable','numeric','min:0'],
            'lines.*.unit'          => ['nullable','string','max:16'],
            'lines.*.required'      => ['nullable','boolean'],
            'lines.*.notes'         => ['nullable','string','max:1000'],

            'guides'  => ['sometimes','array'],
            'guides.*.guide_id'     => ['required','integer','exists:guides,id'],
            'guides.*.purpose'      => ['nullable','string','max:64'],
            'guides.*.default_qty'  => ['nullable','numeric','min:0'],
            'guides.*.unit'         => ['nullable','string','max:16'],
            'guides.*.required'     => ['nullable','boolean'],
            'guides.*.notes'        => ['nullable','string','max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.*.line_id.exists'   => 'Linha inválida.',
            'guides.*.guide_id.exists' => 'Guia inválido.',
        ];
    }

    public function detachMissing(): bool
    {
        return (bool) ($this->input('detach_missing', true));
    }

    public function lines(): array
    {
        $rows = $this->input('lines', []);
        if (!is_array($rows)) return [];

        return array_values(array_map(function ($r) {
            return [
                'line_id'       => (int)($r['line_id'] ?? 0),
                'purpose'       => $r['purpose']       ?? null,
                'suggested_qty' => isset($r['suggested_qty']) ? (float)$r['suggested_qty'] : null,
                'unit'          => $r['unit']          ?? null,
                'required'      => isset($r['required']) ? (bool)$r['required'] : false,
                'notes'         => $r['notes']         ?? null,
            ];
        }, $rows));
    }

    public function guides(): array
    {
        $rows = $this->input('guides', []);
        if (!is_array($rows)) return [];

        return array_values(array_map(function ($r) {
            return [
                'guide_id'     => (int)($r['guide_id'] ?? 0),
                'purpose'      => $r['purpose']      ?? null,
                'default_qty'  => isset($r['default_qty']) ? (float)$r['default_qty'] : null,
                'unit'         => $r['unit']         ?? null,
                'required'     => isset($r['required']) ? (bool)$r['required'] : false,
                'notes'        => $r['notes']        ?? null,
            ];
        }, $rows));
    }
}
