<?php
// app/Http/Requests/StoreEventRequest.php

namespace App\Http\Requests;

use App\Enums\EventStatusEnum;
use App\Enums\EventTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    /**
     * Règles de validation communes
     */
    public function rules(): array
    {
        return [
            // Champs communs
            'type' => ['required', Rule::in(EventTypeEnum::values())],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'status' => ['required', Rule::in(EventStatusEnum::values())],
            'event_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'location' => 'required|string|max:255',
            'price' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
            'max_participants' => 'nullable|integer|min:1',
            'notes' => 'nullable|string',
            'featured' => 'boolean',
            'action' => 'nullable|in:draft,publish',

            // Règles spécifiques selon le type
            ...$this->getTypeSpecificRules(),
        ];
    }

    /**
     * Retourne les règles spécifiques selon le type
     */
    protected function getTypeSpecificRules(): array
    {
        $type = $this->input('type');

        return match ($type) {
            EventTypeEnum::TRAINING->value => [
                'training_level' => 'required|string',
                'training_type' => 'required|string',
                'room_id' => 'required|exists:rooms,id',
                'trainer_id' => 'nullable|exists:users,id',
                'season_id' => 'required|exists:seasons,id',
            ],

            EventTypeEnum::INTERCLUB->value => [
                'is_home' => 'boolean',
                'interclub_room_id' => 'required_if:is_home,1|nullable|exists:rooms,id',
                'interclub_address' => 'required_if:is_home,0|nullable|string|max:150',
                'visited_team_id' => 'required|exists:teams,id',
                'opposite_club_id' => 'required|exists:clubs,id',
                'visiting_team_id' => 'nullable|exists:teams,id',
                'opposite_team_name' => 'nullable|string|size:1|regex:/^[a-zA-Z]$/',
                'total_players' => 'required|integer|min:1|max:20',
                'week_number' => 'nullable|integer|min:1|max:52',
                'league_id' => 'nullable|exists:leagues,id',
                'interclub_season_id' => 'required|exists:seasons,id',
            ],

            EventTypeEnum::TOURNAMENT->value => [
                'tournament_start_date' => 'nullable|date|after_or_equal:event_date',
                'tournament_end_date' => 'nullable|date|after_or_equal:tournament_start_date',
                'tournament_max_users' => 'required|integer|min:2',
                'tournament_price' => 'required|numeric|min:0',
                'tournament_status' => 'required|string',
                'has_handicap_points' => 'boolean',
            ],

            default => [],
        };
    }

    /**
     * Messages de validation personnalisés
     */
    public function messages(): array
    {
        return [
            'type.required' => __('Please select an event type.'),
            'title.required' => __('The title is required.'),
            'description.required' => __('The description is required.'),
            'event_date.required' => __('The event date is required.'),
            'event_date.after_or_equal' => __('The event date must be today or in the future.'),
            'start_time.required' => __('The start time is required.'),
            'end_time.after' => __('The end time must be after the start time.'),
            'location.required' => __('The location is required.'),
            
            // Training
            'training_level.required' => __('Please select a training level.'),
            'training_type.required' => __('Please select a training type.'),
            'room_id.required' => __('Please select a room.'),
            'season_id.required' => __('Please select a season.'),
            
            // Interclub
            'interclub_room_id.required_if' => __('Please select a room for home matches.'),
            'interclub_address.required_if' => __('Please provide an address for away matches.'),
            'visited_team_id.required' => __('Please select your team.'),
            'opposite_club_id.required' => __('Please select the opposing club.'),
            'total_players.required' => __('Please specify the number of players.'),
            'opposite_team_name.regex' => __('The team name must be a single letter (A, B, C...).'),
            'interclub_season_id.required' => __('Please select a season.'),
            
            // Tournament
            'tournament_max_users.required' => __('Please specify the maximum number of participants.'),
            'tournament_price.required' => __('Please specify the registration price (0 if free).'),
            'tournament_status.required' => __('Please select a tournament status.'),
        ];
    }

    /**
     * Prépare les données pour la validation
     */
    protected function prepareForValidation(): void
    {
        // Convertit les checkboxes en boolean
        $this->merge([
            'featured' => $this->boolean('featured'),
            'is_home' => $this->boolean('is_home'),
            'has_handicap_points' => $this->boolean('has_handicap_points'),
        ]);
    }
}
