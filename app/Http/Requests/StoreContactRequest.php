<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'interest' => 'required|string',
            'message' => 'required|string|max:2000',
            'consent' => 'required|accepted',
            
            // Champs optionnels pour l'adhésion
            'membership_family_members' => 'nullable|integer|min:1|max:10',
            'membership_competitors' => 'nullable|integer|min:0',
            'membership_training_sessions' => 'nullable|integer|min:0|max:10',
            'membership_total_cost' => 'nullable|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'interest.required' => 'Veuillez sélectionner votre intérêt.',
            'message.required' => 'Le message est obligatoire.',
            'message.max' => 'Le message ne peut pas dépasser 2000 caractères.',
            'consent.required' => 'Vous devez accepter les conditions.',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('home') . '#contact';
    }
}
