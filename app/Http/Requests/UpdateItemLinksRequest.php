<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajuste se precisar
    }

    public function rules(): array
    {
        return [
            'merge'       => ['sometimes','boolean'],
            'cascade_up'  => ['sometimes','boolean'],

            // Aceita objetos {line_id} OU inteiros
            'lines'          => ['sometimes','array'],
            'lines.*'        => ['nullable'], // normalizamos no controller

            // Aceita objetos {guide_id} OU inteiros
            'guides'         => ['sometimes','array'],
            'guides.*'       => ['nullable'],
        ];
    }

    /**
     * Helpers para o controller
     */
    public function mergeMode(): bool
    {
        return (bool) $this->boolean('merge', true);
    }

    public function cascadeUp(): bool
    {
        return (bool) $this->boolean('cascade_up', true);
    }

    /** @return int[] */
    public function lineIds(): array
    {
        $raw = (array) $this->input('lines', []);
        $ids = [];
        foreach ($raw as $v) {
            if (is_array($v) && isset($v['line_id'])) {
                $ids[] = (int) $v['line_id'];
            } elseif (is_numeric($v)) {
                $ids[] = (int) $v;
            }
        }
        return array_values(array_unique(array_filter($ids, fn($n) => $n > 0)));
    }

    /** @return int[] */
    public function guideIds(): array
    {
        $raw = (array) $this->input('guides', []);
        $ids = [];
        foreach ($raw as $v) {
            if (is_array($v) && isset($v['guide_id'])) {
                $ids[] = (int) $v['guide_id'];
            } elseif (is_numeric($v)) {
                $ids[] = (int) $v;
            }
        }
        return array_values(array_unique(array_filter($ids, fn($n) => $n > 0)));
    }
}
