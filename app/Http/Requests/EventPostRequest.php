<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ClubPosts\EventPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_committee_member;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:' . implode(',', array_keys(EventPost::CATEGORIES)),
            'status' => 'required|in:' . implode(',', array_keys(EventPost::STATUSES)),
            'event_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'featured' => 'boolean',
        ];
    }
}

