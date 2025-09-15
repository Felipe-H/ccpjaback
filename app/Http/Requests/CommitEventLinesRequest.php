<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CommitEventLinesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required','array','min:1'],
            'lines.*.line_id' => ['required','integer','distinct'],

            'options.replace_existing' => ['nullable','boolean'],
            'options.create_event_items' => ['nullable','boolean'],
            'options.only_required' => ['nullable','boolean'],
            'options.skip_conflicts' => ['nullable','boolean'],
            'options.persist_sources_in_notes' => ['nullable','boolean'],

            'override_quantities' => ['nullable','array'],
            'override_quantities.*.item_id' => ['required','integer','distinct'],
            'override_quantities.*.qty' => ['required','numeric','gt:0'],
            'override_quantities.*.unit' => ['nullable','string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function replaceExisting(): bool
    {
        return (bool)$this->input('options.replace_existing', true);
    }
    public function createEventItems(): bool
    {
        return (bool)$this->input('options.create_event_items', true);
    }
    public function onlyRequired(): bool
    {
        return (bool)$this->input('options.only_required', false);
    }
    public function skipConflicts(): bool
    {
        return (bool)$this->input('options.skip_conflicts', true);
    }
    public function persistSourcesInNotes(): bool
    {
        return (bool)$this->input('options.persist_sources_in_notes', true);
    }

    /** @return array<int> */
    public function lineIds(): array
    {
        return collect($this->input('lines', []))->pluck('line_id')->filter()->unique()->values()->all();
    }

    /** @return array<int,array{item_id:int,qty:float,unit:?string}> */
    public function overrides(): array
    {
        return collect($this->input('override_quantities', []))
            ->map(fn($o) => [
                'item_id' => (int)($o['item_id'] ?? 0),
                'qty' => (float)($o['qty'] ?? 0),
                'unit' => $o['unit'] ?? null,
            ])->keyBy('item_id')->all();
    }
}
