<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Validators;

use Illuminate\Support\Facades\Validator;

class ArticleValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'content' => 'required|string|min:10|max:10000',
            'category' => 'nullable|in:news,results,testimonial,event',
            'featured_image' => 'nullable|url',
            'status' => 'required|in:draft,published',
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'title' => 'sometimes|required|string|min:3|max:255',
            'content' => 'sometimes|required|string|min:10|max:10000',
            'category' => 'nullable|in:news,results,testimonial,event',
            'featured_image' => 'nullable|url',
            'status' => 'sometimes|required|in:draft,published,archived',
        ])->validate();
    }
}
