<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\SpiritualLine;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class ContentController extends Controller
{
    /*
     |--------------------------------------------------------------------------
     | Helpers
     |--------------------------------------------------------------------------
     */

    /**
     * Normaliza links externos (especialmente Google Drive) para uma forma canônica.
     */
    private function normalizeExternalUrl(?string $url): ?string
    {
        if (!$url) return null;

        // Pasta do Drive → mantém original
        if (preg_match('~drive\.google\.com/drive/folders/([a-zA-Z0-9_-]+)~', $url)) {
            return $url;
        }

        // /file/d/{id}
        if (preg_match('~drive\.google\.com/file/d/([a-zA-Z0-9_-]+)~', $url, $m)) {
            return "https://drive.google.com/file/d/{$m[1]}/view";
        }

        // open?id={id}  ou  uc?id={id}
        if (preg_match('~[?&]id=([a-zA-Z0-9_-]+)~', $url, $m)) {
            return "https://drive.google.com/file/d/{$m[1]}/view";
        }

        // Outros http/https válidos
        return $url;
    }

    /**
     * Persiste a capa (se enviada) e retorna a URL pública.
     * Prioridade: arquivo "cover" (multipart) > cover_url (string) > mantém atual
     */
    private function persistCoverAndGetUrl(Request $request, ?string $currentUrl = null): ?string
    {
        // 1) upload de arquivo
        if ($request->hasFile('cover')) {
            $file = $request->file('cover');
            $name = Str::uuid()->toString() . 'Controllers' . $file->getClientOriginalExtension();
            // Disk public -> storage/app/public/covers/{uuid}.{ext}
            $path = $file->storeAs('covers', $name, ['disk' => 'public']);

            // Retorna URL pública (APP_URL + /storage/...)
            return asset(Storage::disk('public')->url($path));
        }

        // 2) URL já pronta
        if ($request->filled('cover_url')) {
            return (string) $request->string('cover_url');
        }

        // 3) mantém atual
        return $currentUrl;
    }

    /**
     * Presenter básico (pode virar Resource futuramente).
     */
    private function present(ContentItem $c): array
    {
        return [
            'id'          => $c->id,
            'type'        => $c->type,
            'status'      => $c->status,
            'visibility'  => $c->visibility,
            'title'       => $c->title,
            'slug'        => $c->slug,
            'description' => $c->description,
            'source_url'  => $c->source_url,
            'cover_url'   => $c->cover_url,
            'duration_sec'=> $c->duration_sec,
            'created_by'  => $c->created_by,
            'created_at'  => $c->created_at,
            'updated_at'  => $c->updated_at,
        ];
    }

    /*
     |--------------------------------------------------------------------------
     | Actions
     |--------------------------------------------------------------------------
     */

    /**
     * GET /content
     * Filtros: q, status, visibility, type, per_page
     */
    public function index(Request $request)
    {
        // sanitiza entradas
        $q          = trim((string) $request->input('q', ''));
        $status     = trim((string) $request->input('status', ''));
        $visibility = trim((string) $request->input('visibility', ''));
        $type       = trim((string) $request->input('type', ''));
        $perPage    = max(1, min(100, (int) $request->input('per_page', 20)));

        $query = ContentItem::query();

        // 🔎 Busca case-insensitive compatível com SQLite/Postgres
        if ($q !== '') {
            $needle = mb_strtolower($q, 'UTF-8');
            $query->where(function ($w) use ($needle) {
                $w->whereRaw('LOWER(title) LIKE ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $needle . '%']);
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($visibility !== '') {
            $query->where('visibility', $visibility);
        }
        if ($type !== '') {
            $query->where('type', $type);
        }

        $query->orderByDesc('id');

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(function (ContentItem $c) {
            return [
                'id'          => $c->id,
                'type'        => $c->type,
                'status'      => $c->status,
                'visibility'  => $c->visibility,
                'title'       => $c->title,
                'slug'        => $c->slug,
                'description' => $c->description,
                'source_url'  => $c->source_url,
                'cover_url'   => $c->cover_url,
                'duration_sec'=> $c->duration_sec,
                'created_by'  => $c->created_by,
                'created_at'  => $c->created_at,
                'updated_at'  => $c->updated_at,
            ];
        });

        return response()->json($paginator);
    }


    /**
     * GET /content/{content}
     */
    public function show(ContentItem $content)
    {
        return response()->json($this->present($content));
    }

    /**
     * POST /content
     * Aceita: cover (file) OU cover_url (string), e source_url (externo/Drive).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => 'required|string|',
            'status'      => 'required|string|in:draft,published,archived',
            'visibility'  => 'required|string|in:public,students,private',
            'title'       => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'source_url'  => 'nullable|url|max:2000|starts_with:http://,https://',
            'cover'       => 'nullable|image|max:4096',
            'cover_url'   => 'nullable|url|max:2000|starts_with:http://,https://',
            'duration_sec'=> 'nullable|integer|min:0',
        ]);

        // Normaliza link externo
        $data['source_url'] = $this->normalizeExternalUrl($data['source_url'] ?? null);

        // Slug opcional
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // Capa (arquivo ou URL)
        $coverUrl = $this->persistCoverAndGetUrl($request);

        /** @var ContentItem $content */
        $content = ContentItem::create([
            'type'         => $data['type'],
            'status'       => $data['status'],
            'visibility'   => $data['visibility'],
            'title'        => $data['title'],
            'slug'         => $data['slug'],
            'description'  => $data['description'] ?? null,
            'source_url'   => $data['source_url'] ?? null,
            'cover_url'    => $coverUrl,
            'duration_sec' => $data['duration_sec'] ?? null,
            'created_by'   => optional($request->user())->id,
        ]);

        return response()->json($this->present($content), 201);
    }

    /**
     * PUT/PATCH /content/{content}
     */
    public function update(Request $request, ContentItem $content)
    {
        $data = $request->validate([
            'type'        => 'sometimes|string|in:pdf,video,link,article',
            'status'      => 'sometimes|string|in:draft,published,archived',
            'visibility'  => 'sometimes|string|in:public,students,private',
            'title'       => 'sometimes|string|max:255',
            'slug'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'source_url'  => 'nullable|url|max:2000|starts_with:http://,https://',
            'cover'       => 'nullable|image|max:4096',
            'cover_url'   => 'nullable|url|max:2000|starts_with:http://,https://',
            'duration_sec'=> 'nullable|integer|min:0',
        ]);

        if (array_key_exists('source_url', $data)) {
            $data['source_url'] = $this->normalizeExternalUrl($data['source_url']);
        }

        // Capa (se enviada agora, substitui)
        if ($request->hasFile('cover') || $request->filled('cover_url')) {
            $data['cover_url'] = $this->persistCoverAndGetUrl($request, $content->cover_url);
        }

        $content->fill($data)->save();

        return response()->json($this->present($content));
    }

    /**
     * DELETE /content/{content}
     */
    public function destroy(ContentItem $content)
    {
        $content->delete();
        return response()->json(['ok' => true]);
    }
}
