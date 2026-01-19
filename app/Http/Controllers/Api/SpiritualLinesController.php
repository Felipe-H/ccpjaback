<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSpiritualLineRequest;
use App\Http\Requests\UpdateSpiritualLineRequest;
use App\Models\SpiritualLine;
use Illuminate\Http\Request;

class SpiritualLinesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertManager($request);

        $q = SpiritualLine::query();

        if ($request->has('active')) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN);
            $q->where('status', $active ? 'ativo' : 'inativo');
        }

        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }

        if ($parentId = $request->query('parent_id')) {
            $q->where('parent_id', $parentId);
        }

        if ($search = $request->query('q')) {
            $q->where(function ($where) use ($search) {
                $like = "%{$search}%";
                $where->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));

        return $q->orderBy('sort_order')->orderBy('name')->paginate($perPage);
    }

    public function store(StoreSpiritualLineRequest $request)
    {
        $this->assertManager($request);
        $data = $request->validated();

        return SpiritualLine::create($data);
    }

    public function update(UpdateSpiritualLineRequest $request, SpiritualLine $line)
    {
        $this->assertManager($request);
        $data = $request->validated();

        $line->fill($data)->save();

        return $line->fresh();
    }

    public function destroy(SpiritualLine $line)
    {
        $this->assertManager(request());
        $line->delete();
        return response()->noContent();
    }

    private function assertManager(Request $request): void
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['gerente', 'desenvolvedor'], true)) {
            abort(403, 'Access denied.');
        }
    }
}
