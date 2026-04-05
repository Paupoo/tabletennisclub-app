<?php

declare(strict_types=1);

namespace App\Http\Requests\ClubAdmin\Contact;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Support\Captcha;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    public function __construct(private Captcha $captchaService = new Captcha()){}

    /**
     * Determine if the user is authorized to make this request.
     * This endpoint is public, so authorization is always true.
     * CSRF protection is handled by the 'web' middleware group automatically.
     */
    public function authorize(): bool
    {
        return true;
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
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
            'captcha' => 'required|integer',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $captcha = session('captcha');

            if (!$captcha || !session('captcha_created_at')) {
                $validator->errors()->add('captcha', 'Captcha expiré.');
                return;
            }

            if (time() - session('captcha_created_at') > 300) {
                $validator->errors()->add('captcha', 'Captcha expiré.');
                return;
            }

            $valid = $this->captchaService->validate(
                $captcha,
                (int) $this->input('captcha')
            );

            if (!$valid) {
                $validator->errors()->add('captcha', 'Captcha incorrect.');
            }
        });
    }

    protected function getRedirectUrl(): string
    {
        return route('home') . '#contact';
    }
}
