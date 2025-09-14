<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_committee_member;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'description.required' => 'La description est obligatoire.',
            'event_date.after_or_equal' => 'La date de l\'événement ne peut pas être dans le passé.',
            'end_time.after' => 'L\'heure de fin doit être après l\'heure de début.',
            'max_participants.min' => 'Le nombre de participants doit être au moins 1.',
            'max_participants.max' => 'Le nombre de participants ne peut pas dépasser 1000.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Si pas d'icône fournie, utiliser l'icône par défaut de la catégorie
        if (empty($this->icon) && $this->category) {
            $this->merge([
                'icon' => Event::ICONS[$this->category] ?? '📅',
            ]);
        }

        // Convertir les checkbox en boolean
        $this->merge([
            'featured' => $this->boolean('featured'),
        ]);

        // Fusion date + heures en datetime
        if ($this->event_date && $this->start_time) {
            $this->merge([
                'start_at' => Carbon::parse($this->event_date . ' ' . $this->start_time)->format('Y-m-d H:i'),
            ]);
        }

        if ($this->event_date && $this->end_time) {
            $this->merge([
                'end_at' => Carbon::parse($this->event_date . ' ' . $this->end_time)->format('Y-m-d H:i'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'category' => 'required|string',
            'status' => 'required|string',
            'start_at' => 'required|date|after_or_equal:today',
            'end_at' => 'nullable|date|after:start_time',
            'address' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'notes' => 'nullable|string|max:1000',
            'featured' => 'boolean',
        ];
    }
}
