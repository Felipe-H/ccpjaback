<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajuste se quiser gate/policy
    }

    public function rules(): array
    {
        return [
            'merge'        => ['sometimes','boolean'],
            'cascade_up'   => ['sometimes','boolean'],

            'lines'        => ['sometimes','array'],
            'lines.*.line_id'   => ['required','integer','min:1'],
            'lines.*.default_qty' => ['sometimes','numeric','min:0'],
            'lines.*.unit'       => ['sometimes','nullable','string','max:50'],
            'lines.*.required'   => ['sometimes','boolean'],
            'lines.*.purpose'    => ['sometimes','nullable','string','max:100'],
            'lines.*.notes'      => ['sometimes','nullable','string','max:500'],

            'guides'       => ['sometimes','array'],
            'guides.*.guide_id'  => ['required','integer','min:1'],
            'guides.*.default_qty' => ['sometimes','numeric','min:0'],
            'guides.*.unit'       => ['sometimes','nullable','string','max:50'],
            'guides.*.required'   => ['sometimes','boolean'],
            'guides.*.purpose'    => ['sometimes','nullable','string','max:100'],
            'guides.*.notes'      => ['sometimes','nullable','string','max:500'],

            'defaults'     => ['sometimes','array'],
            'defaults.default_qty' => ['sometimes','numeric','min:0'],
            'defaults.unit'       => ['sometimes','nullable','string','max:50'],
            'defaults.required'   => ['sometimes','boolean'],
            'defaults.purpose'    => ['sometimes','nullable','string','max:100'],
            'defaults.notes'      => ['sometimes','nullable','string','max:500'],
        ];
    }

    public function wantsMerge(): bool
    {
        return (bool)$this->boolean('merge', true);
    }

    public function cascadeUp(): bool
    {
        return (bool)$this->boolean('cascade_up', false);
    }

    public function lines(): array
    {
        return $this->input('lines', []);
    }

    public function guides(): array
    {
        return $this->input('guides', []);
    }

    public function defaults(): array
    {
        return $this->input('defaults', []);
    }
}
