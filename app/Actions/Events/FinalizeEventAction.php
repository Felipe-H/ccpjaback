<?php

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class FinalizeEventAction
{
    public function handle(Event $event): Event
    {
        DB::transaction(function () use ($event) {
            $links = $event->eventItems()->with('inventoryItem')->lockForUpdate()->get();

            foreach ($links as $link) {
                $used = (int) $link->quantity_used;
                $required = (int) $link->quantity_required;

                if ($used > $required) {
                    abort(422, "Consumo ($used) maior que requerido ($required) para o item ID {$link->inventory_item_id}.");
                }

                if ($link->is_from_stock) {
                    $item = $link->inventoryItem;
                    $newQty = max(0, (int) $item->quantity - $used);

                    $item->quantity = $newQty;
                    $item->status = $this->computeStatus($newQty, (int) $item->ideal_quantity);
                    $item->save();
                }
            }

            $event->update(['status' => 'done']);
        });

        return $event->fresh(['eventItems.inventoryItem']);
    }

    private function computeStatus(int $quantity, int $idealQuantity): string
    {
        if ($quantity <= 0) {
            return 'to_buy';
        }

        if ($idealQuantity > 0 && $quantity < $idealQuantity) {
            return 'low_stock';
        }

        return 'available';
    }
}
