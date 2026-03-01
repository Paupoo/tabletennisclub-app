<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Actions\ClubAdmin\Contact\StoreContactAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClubAdmin\Contact\StoreContactRequest;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class ContactController extends Controller
{
    public function __construct(
        private readonly StoreContactAction $storeContactAction
    ) {}

    public function store(StoreContactRequest $request): RedirectResponse
    {
        try {
            $this->storeContactAction->execute($request->validated());

            return redirect()->to(route('home') . '#contact')
                ->with('success', __('Your message was successfully sent! We will return to you shortly'));

        } catch (Exception $e) {
            Log::error('Contact submission failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()->to(route('home') . '#contact')
                ->with('error', __('Something went wrong while sending your message. Please try again later'))
                ->withInput();
        }
    }
}
