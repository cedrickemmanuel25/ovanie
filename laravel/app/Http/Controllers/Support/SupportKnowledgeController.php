<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportKnowledgeArticle;
use App\Services\SupportAi\SupportAiAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportKnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $query = SupportKnowledgeArticle::with(['creator', 'updater', 'approver'])->latest();

        if ($request->filled('q')) {
            $search = trim((string) $request->query('q'));
            $query->where(fn ($builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('content', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return view('support.knowledge.index', [
            'articles' => $query->paginate(25)->withQueryString(),
            'stats' => [
                'published_for_ai' => SupportKnowledgeArticle::availableToAi()->count(),
                'draft' => SupportKnowledgeArticle::where('status', 'draft')->count(),
                'archived' => SupportKnowledgeArticle::where('status', 'archived')->count(),
                'expired' => SupportKnowledgeArticle::published()->whereNotNull('expires_at')->where('expires_at', '<=', now())->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('support.knowledge.form', ['article' => new SupportKnowledgeArticle()]);
    }

    public function store(Request $request, SupportAiAuditLogger $audit)
    {
        $data = $this->validated($request);
        $user = $request->user('admin');
        $data['slug'] = $this->uniqueSlug($data['title']);
        $data['created_by'] = $user->id;
        $data['updated_by'] = $user->id;
        $data['version'] = 1;
        $this->applyPublicationState($data, $user->id);

        $article = SupportKnowledgeArticle::create($data);

        $audit->log([
            'actor_user_id' => $user->id,
            'action' => 'knowledge_article_created',
            'decision' => $article->status,
            'risk_level' => 'low',
            'output' => ['article_id' => $article->id, 'slug' => $article->slug, 'version' => $article->version],
        ]);

        return redirect()->route('support.knowledge.index')->with('success', 'Article ajouté à la base de connaissances.');
    }

    public function edit(SupportKnowledgeArticle $article)
    {
        return view('support.knowledge.form', compact('article'));
    }

    public function update(Request $request, SupportKnowledgeArticle $article, SupportAiAuditLogger $audit)
    {
        $data = $this->validated($request);
        $user = $request->user('admin');
        $contentChanged = $article->title !== $data['title'] || $article->content !== $data['content'];

        if ($article->title !== $data['title']) {
            $data['slug'] = $this->uniqueSlug($data['title'], $article->id);
        }

        $data['updated_by'] = $user->id;
        $data['version'] = $contentChanged ? ((int) $article->version + 1) : (int) $article->version;
        $this->applyPublicationState($data, $user->id, $article);
        $article->update($data);

        $audit->log([
            'actor_user_id' => $user->id,
            'action' => 'knowledge_article_updated',
            'decision' => $article->status,
            'risk_level' => 'low',
            'input' => ['content_changed' => $contentChanged],
            'output' => ['article_id' => $article->id, 'slug' => $article->slug, 'version' => $article->version],
        ]);

        return redirect()->route('support.knowledge.index')->with('success', 'Article mis à jour. Seule une version publiée et non expirée est utilisée par l’IA.');
    }

    public function destroy(Request $request, SupportKnowledgeArticle $article, SupportAiAuditLogger $audit)
    {
        if ($article->status === 'published') {
            throw ValidationException::withMessages([
                'article' => 'Un article publié ne peut pas être supprimé. Archivez-le afin de préserver la traçabilité.',
            ]);
        }

        $id = $article->id;
        $slug = $article->slug;
        $article->delete();

        $audit->log([
            'actor_user_id' => $request->user('admin')->id,
            'action' => 'knowledge_article_deleted',
            'decision' => 'deleted_draft',
            'risk_level' => 'low',
            'input' => ['article_id' => $id, 'slug' => $slug],
        ]);

        return back()->with('success', 'Article non publié supprimé.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'content' => ['required', 'string', 'max:50000'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'source_type' => ['required', Rule::in(['manual', 'policy', 'faq', 'procedure'])],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
    }

    private function applyPublicationState(array &$data, int $userId, ?SupportKnowledgeArticle $article = null): void
    {
        if ($data['status'] === 'published') {
            $data['published_at'] = $article?->published_at ?: now();
            $data['approved_by'] = $userId;
            $data['reviewed_at'] = now();
            return;
        }

        $data['published_at'] = null;
        if ($data['status'] === 'draft') {
            $data['approved_by'] = null;
            $data['reviewed_at'] = null;
        }
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $counter = 2;

        while (SupportKnowledgeArticle::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
