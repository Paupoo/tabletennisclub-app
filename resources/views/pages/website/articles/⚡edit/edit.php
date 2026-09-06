<?php

declare(strict_types=1);

namespace Resources\views\Pages\Website\Articles\Edit;

use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
    use HasBreadcrumbs, Toast, WithFileUploads;

    public string $category = '';

    public string $content = '';

    public ?string $existingImage = null;

    public mixed $image = null;

    public int $imageFocalX = 50;

    public int $imageFocalY = 50;

    #[Locked]
    public ?int $newsPostId = null;

    public string $slug = '';

    public string $status = 'draft';

    public string $title = '';

    public function mount(?NewsPost $newsPost = null): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        if ($newsPost && $newsPost->exists) {
            $this->newsPostId = $newsPost->id;
            $this->title = $newsPost->title;
            $this->slug = $newsPost->slug;
            $this->content = $newsPost->content ?? '';
            $this->category = $newsPost->category?->value ?? '';
            $this->status = $newsPost->status?->value ?? 'draft';
            $this->existingImage = $newsPost->image;
            $this->imageFocalX = $newsPost->image_focal_x;
            $this->imageFocalY = $newsPost->image_focal_y;
        }
    }

    /**
     * Drop the featured image, and the framing that went with it.
     *
     * A focal point set for the old photo would otherwise be applied silently
     * to whatever replaces it, cropping the new image around a subject that
     * is no longer there.
     */
    public function removeImage(): void
    {
        if ($this->existingImage) {
            Storage::disk('public')->delete($this->existingImage);
            $this->existingImage = null;
        }

        $this->imageFocalX = 50;
        $this->imageFocalY = 50;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        Gate::authorize(Permission::NewsPostsManage->value);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', Rule::unique('news_posts', 'slug')->ignore($this->newsPostId)],
            'content' => ['required', 'string'],
            'category' => ['required', Rule::in(NewsPostCategoryEnum::values())],
            'status' => ['required', Rule::in(NewsPostStatusEnum::values())],
            'image' => ['nullable', 'image', 'max:4096'],
            'imageFocalX' => ['required', 'integer', 'between:0,100'],
            'imageFocalY' => ['required', 'integer', 'between:0,100'],
        ]);

        $imagePath = $this->existingImage;

        if ($this->image) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $this->image->store('clubPosts', 'public');
        }

        $data = [
            'title' => $this->title,
            'slug' => Str::slug($this->slug),
            'content' => $this->content,
            'category' => $this->category,
            'status' => NewsPostStatusEnum::from($this->status),
            'image' => $imagePath,
            'image_focal_x' => $this->imageFocalX,
            'image_focal_y' => $this->imageFocalY,
            'user_id' => Auth::id(),
        ];

        if ($this->newsPostId) {
            NewsPost::findOrFail($this->newsPostId)->update($data);
        } else {
            $post = NewsPost::create($data);
            $this->newsPostId = $post->id;
        }

        $label = match ($this->status) {
            'published' => __('Article published.'),
            'archived' => __('Article archived.'),
            default => __('Draft saved.'),
        };

        $this->success($label, redirectTo: route('admin.website.articles.index'));
    }

    public function updatedTitle(): void
    {
        if (! $this->newsPostId) {
            $this->slug = Str::slug($this->title);
        }
    }

    public function with(): array
    {
        $categoryOptions = collect(NewsPostCategoryEnum::cases())
            ->map(fn ($c): array => ['id' => $c->value, 'name' => $c->getLabel()]);

        $statusOptions = collect(NewsPostStatusEnum::cases())
            ->map(fn ($s): array => ['id' => $s->value, 'name' => $s->getLabel()]);

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Website', '#')
                ->websiteArticles()
                ->current($this->newsPostId ? 'Modifier' : 'Nouvel article')
                ->toArray(),
            'categoryOptions' => $categoryOptions,
            'statusOptions' => $statusOptions,
            'markdownPreview' => Markdown::safe($this->content ?: ''),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current($this->article?->exists ? __('Edit') : __('Create'));
    }
};
