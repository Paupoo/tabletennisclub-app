<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Articles\Edit;

use App\Enums\NewsPostCategoryEnum;
use App\Enums\NewsPostStatusEnum;
use App\Models\ClubPosts\NewsPost;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads;

    #[Locked]
    public ?int $newsPostId = null;

    public string $title          = '';
    public string $slug           = '';
    public string $content        = '';
    public string $category       = '';
    public string $status         = 'draft';
    public bool $isPublic         = true;
    public ?string $existingImage = null;
    public mixed $image            = null;

    public function mount(?NewsPost $newsPost = null): void
    {
        if ($newsPost && $newsPost->exists) {
            $this->newsPostId    = $newsPost->id;
            $this->title         = $newsPost->title;
            $this->slug          = $newsPost->slug;
            $this->content       = $newsPost->content ?? '';
            $this->category      = $newsPost->category?->value ?? '';
            $this->status        = $newsPost->status?->value ?? 'draft';
            $this->isPublic      = (bool) $newsPost->is_public;
            $this->existingImage = $newsPost->image;
        }
    }

    public function updatedTitle(): void
    {
        if (! $this->newsPostId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function removeImage(): void
    {
        if ($this->existingImage) {
            Storage::disk('public')->delete($this->existingImage);
            $this->existingImage = null;
        }
    }

    public function save(): void
    {
        $this->validate([
            'title'    => ['required', 'string', 'max:255'],
            'slug'     => ['required', 'string', Rule::unique('news_posts', 'slug')->ignore($this->newsPostId)],
            'content'  => ['required', 'string'],
            'category' => ['required', Rule::in(NewsPostCategoryEnum::values())],
            'status'   => ['required', Rule::in(NewsPostStatusEnum::values())],
            'image'    => ['nullable', 'image', 'max:4096'],
        ]);

        $imagePath = $this->existingImage;

        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->image->store('clubPosts', 'public');
        }

        $data = [
            'title'     => $this->title,
            'slug'      => Str::slug($this->slug),
            'content'   => $this->content,
            'category'  => $this->category,
            'status'    => NewsPostStatusEnum::from($this->status),
            'is_public' => $this->isPublic,
            'image'     => $imagePath,
            'user_id'   => Auth::id(),
        ];

        if ($this->newsPostId) {
            NewsPost::findOrFail($this->newsPostId)->update($data);
        } else {
            $post             = NewsPost::create($data);
            $this->newsPostId = $post->id;
        }

        $label = match ($this->status) {
            'published' => 'Article publié.',
            'archived'  => 'Article archivé.',
            default     => 'Brouillon enregistré.',
        };

        $this->success($label, redirectTo: route('admin.website.articles.index'));
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $categoryOptions = collect(NewsPostCategoryEnum::cases())
            ->map(fn ($c) => ['id' => $c->value, 'name' => $c->getLabel()]);

        $statusOptions = collect(NewsPostStatusEnum::cases())
            ->map(fn ($s) => ['id' => $s->value, 'name' => $s->getLabel()]);

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Website', '#')
                ->websiteArticles()
                ->current($this->newsPostId ? 'Modifier' : 'Nouvel article')
                ->toArray(),
            'categoryOptions' => $categoryOptions,
            'statusOptions'   => $statusOptions,
            'markdownPreview' => Str::markdown($this->content ?: ''),
        ];
    }
};
