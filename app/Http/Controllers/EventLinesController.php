<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewEventLinesRequest;
use App\Http\Requests\CommitEventLinesRequest;
use App\Models\Event;
use App\Models\EventLine;
use App\Models\EventItem;
use App\Services\LineResolver;
use App\Services\GuideResolver;
use App\Services\SuggestionEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventLinesController extends Controller
{
    public function __construct(
        private LineResolver $lineResolver,
        private GuideResolver $guideResolver,
        private SuggestionEngine $suggestionEngine,
    ) {}

    public function preview(PreviewEventLinesRequest $req, Event $event)
    {
        $includeInactive = $req->includeInactive();
        $onlyRequired = $req->onlyRequired();
        $lineIds = $req->lineIds();

        $resolved = $this->lineResolver->resolve($lineIds, $includeInactive);
        $resolvedIds = array_keys($resolved['resolved']);

        $guides = $this->guideResolver->listByLines($resolvedIds, $includeInactive);

        $built = $this->suggestionEngine->build($resolvedIds, $guides, $onlyRequired);

        return response()->json([
            'event' => [
                'id' => $event->id, 'title'=>$event->title, 'status'=>$event->status,
                'start_date'=>$event->start_date, 'end_date'=>$event->end_date,
                'location'=>$event->location, 'created_by'=>$event->created_by,
            ],
            'lines_selected' => $resolved['selected'],
            'lines_resolved' => array_values($resolved['resolved']),
            'guides' => array_values($guides),
            'items_suggested' => array_values($built['suggestions']),
            'kpis' => array_merge([
                'lines_selected_count' => count($resolved['selected']),
                'lines_resolved_count' => count($resolved['resolved']),
                'guides_count' => count($guides),
            ], $built['kpis']),
            'meta' => [
                'expand_hierarchy' => 'auto',
                'include_inactive' => $includeInactive,
                'dedup_strategy' => 'sum',
                'only_required' => $onlyRequired,
                'generated_at' => now()->toIso8601String(),
                'source' => 'preview',
            ],
        ]);
    }

    public function commit(CommitEventLinesRequest $req, Event $event)
    {
        if (in_array($event->status, ['canceled','done'])) {
            return response()->json([
                'error' => ['code'=>'VALIDATION_FAILED','message'=>'Evento nao permite commit neste status.']
            ], 422);
        }

        $lineIds = $req->lineIds();
        $replace = $req->replaceExisting();
        $createEventItems = $req->createEventItems();
        $onlyRequired = $req->onlyRequired();
        $skipConflicts = $req->skipConflicts();
        $persistNotes = $req->persistSourcesInNotes();
        $overrides = $req->overrides(); // keyed by item_id

        $outChanges = [
            'created'=>0,'updated'=>0,'skipped_conflicts'=>0,'skipped_missing_inventory'=>0,'overrides_applied'=>0,'list'=>[],
        ];

        $eventLinesSummary = null;

        try {
            DB::transaction(function () use (
            $event, $lineIds, $replace, $createEventItems, $onlyRequired, $skipConflicts, $persistNotes, $overrides,
            &$outChanges, &$eventLinesSummary
        ) {

            if ($replace) {
                $deleteQuery = EventLine::where('event_id', $event->id);
                if (!empty($lineIds)) {
                    $deleteQuery->whereNotIn('line_id', $lineIds);
                }
                $deleteQuery->delete();
            }

            if (!empty($lineIds)) {
                $now = now();
                $rows = array_map(fn ($lid) => [
                    'event_id' => $event->id,
                    'line_id' => $lid,
                    'updated_at' => $now,
                    'created_at' => $now,
                ], $lineIds);

                DB::table('event_lines')->upsert($rows, ['event_id', 'line_id'], ['updated_at']);
            }

            $resolved = app(LineResolver::class)->resolve($lineIds, false);
            $resolvedIds = array_keys($resolved['resolved']);
            $guides = app(GuideResolver::class)->listByLines($resolvedIds, false);
            $built = app(SuggestionEngine::class)->build($resolvedIds, $guides, $onlyRequired);

            $eventLinesSummary = [
                'selected' => $resolved['selected'],
                'resolved' => array_values($resolved['resolved']),
                'replaced_existing' => $replace,
                'count' => count($resolved['selected']),
            ];

            if (!$createEventItems) {
                return;
            }

            foreach ($built['suggestions'] as $sug) {
                $itemId = (int)$sug['item_id'];

                if ($skipConflicts && !empty($sug['conflicts'])) {
                    $outChanges['skipped_conflicts']++;
                    $outChanges['list'][] = [
                        'item_id'=>$itemId,'action'=>'skipped','reason'=>'conflicts','conflicts'=>$sug['conflicts']
                    ];
                    continue;
                }

                if (empty($sug['name'])) {
                    $outChanges['skipped_missing_inventory']++;
                    $outChanges['list'][] = [
                        'item_id'=>$itemId,'action'=>'skipped','reason'=>'missing_inventory','conflicts'=>$sug['conflicts']
                    ];
                    continue;
                }

                $finalQty = $sug['qty_suggested'];
                $finalUnit = $sug['unit'];
                if (isset($overrides[$itemId])) {
                    $ov = $overrides[$itemId];
                    if (!empty($ov['unit']) && $finalUnit && $ov['unit'] !== $finalUnit) {
                        $sug['conflicts'][] = ['type'=>'unit_mismatch_override','details'=>['override_unit'=>$ov['unit'],'unit'=>$finalUnit]];
                    }
                    $finalQty = $ov['qty'];
                    $outChanges['overrides_applied']++;
                }

                if ($finalQty === null || $finalQty <= 0) {
                    $outChanges['list'][] = [
                        'item_id'=>$itemId,'action'=>'skipped','reason'=>'no_quantity','conflicts'=>$sug['conflicts']
                    ];
                    continue;
                }

                $payload = [
                    'quantity_required' => $finalQty,
                ];
                if ($persistNotes && !empty($sug['notes_proposed'])) {
                    $payload['notes'] = $sug['notes_proposed'];
                }

                $existing = EventItem::query()
                    ->where('event_id',$event->id)
                    ->where('inventory_item_id',$itemId)
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    $outChanges['updated']++;
                    $outChanges['list'][] = [
                        'item_id'=>$itemId,'action'=>'upsert','quantity_required'=>$finalQty,'required'=>$sug['required'],
                        'conflicts'=>$sug['conflicts'] ?? [], 'notes'=>$payload['notes'] ?? null
                    ];
                } else {
                    EventItem::create(array_merge($payload, [
                        'event_id' => $event->id,
                        'inventory_item_id' => $itemId,
                    ]));
                    $outChanges['created']++;
                    $outChanges['list'][] = [
                        'item_id'=>$itemId,'action'=>'upsert','quantity_required'=>$finalQty,'required'=>$sug['required'],
                        'conflicts'=>$sug['conflicts'] ?? [], 'notes'=>$payload['notes'] ?? null
                    ];
                }
            }
        });
        } catch (\Throwable $e) {
            $errorId = (string) Str::uuid();

            report($e);
            logger()->error('Event lines commit failed', [
                'error_id' => $errorId,
                'event_id' => $event->id,
                'line_ids' => $lineIds,
                'options' => [
                    'replace_existing' => $replace,
                    'create_event_items' => $createEventItems,
                    'only_required' => $onlyRequired,
                    'skip_conflicts' => $skipConflicts,
                    'persist_sources_in_notes' => $persistNotes,
                ],
            ]);

            $payload = [
                'message' => 'Server Error',
                'error_id' => $errorId,
            ];

            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            return response()->json($payload, 500);
        }
        return response()->json([
            'persisted' => true,
            'event_lines' => $eventLinesSummary,
            'event_items_changes' => $outChanges,
            'snapshot' => [
                'event' => ['id'=>$event->id,'title'=>$event->title,'status'=>$event->status],
            ],
            'meta' => [
                'create_event_items' => $createEventItems,
                'only_required' => $onlyRequired,
                'skip_conflicts' => $skipConflicts,
                'persist_sources_in_notes' => $persistNotes,
                'source' => 'commit',
                'committed_at' => now()->toIso8601String()
            ],
        ]);
    }

    public function lines(Event $event)
    {
        $selected = EventLine::where('event_id',$event->id)->pluck('line_id')->all();
        $resolved = app(LineResolver::class)->resolve($selected, false);

        return response()->json([
            'event' => ['id'=>$event->id,'title'=>$event->title,'status'=>$event->status],
            'lines_selected' => $resolved['selected'],
            'lines_resolved' => array_values($resolved['resolved']),
            'kpis' => [
                'lines_selected_count' => count($resolved['selected']),
                'lines_resolved_count' => count($resolved['resolved']),
            ],
        ]);
    }

    public function guides(Event $event)
    {
        $selected = EventLine::where('event_id',$event->id)->pluck('line_id')->all();
        $resolved = app(LineResolver::class)->resolve($selected, false);
        $guides = app(GuideResolver::class)->listByLines(array_keys($resolved['resolved']), false);

        return response()->json([
            'event' => ['id'=>$event->id,'title'=>$event->title],
            'guides' => $guides,
            'kpis' => ['guides_count' => count($guides)],
        ]);
    }

    public function suggestions(Request $request, Event $event)
    {
        $onlyRequired = (bool)$request->query('only_required', false);
        $includeInactive = (bool)$request->query('include_inactive', false);

        $selected = EventLine::where('event_id',$event->id)->pluck('line_id')->all();
        $resolved = app(LineResolver::class)->resolve($selected, $includeInactive);
        $guides = app(GuideResolver::class)->listByLines(array_keys($resolved['resolved']), $includeInactive);
        $built = app(SuggestionEngine::class)->build(array_keys($resolved['resolved']), $guides, $onlyRequired);

        return response()->json([
            'event' => ['id'=>$event->id,'title'=>$event->title,'status'=>$event->status],
            'lines_selected' => $resolved['selected'],
            'lines_resolved' => array_values($resolved['resolved']),
            'guides' => $guides,
            'items_suggested' => array_values($built['suggestions']),
            'kpis' => array_merge([
                'lines_selected_count' => count($resolved['selected']),
                'lines_resolved_count' => count($resolved['resolved']),
                'guides_count' => count($guides),
            ], $built['kpis']),
            'meta' => [
                'expand_hierarchy' => 'auto',
                'include_inactive' => $includeInactive,
                'dedup_strategy' => 'sum',
                'only_required' => $onlyRequired,
                'generated_at' => now()->toIso8601String(),
                'source' => 'event',
            ],
        ]);
    }
}
