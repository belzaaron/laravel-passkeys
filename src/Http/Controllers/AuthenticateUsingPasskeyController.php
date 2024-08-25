<?php

namespace Spatie\LaravelPasskeys\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\FindPasskeyAction;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;
use Spatie\LaravelPasskeys\Http\Requests\AuthenticateUsingPasskeysRequest;
use Spatie\LaravelPasskeys\Support\Config;

class AuthenticateUsingPasskeyController
{
    public function __invoke(AuthenticateUsingPasskeysRequest $request)
    {
        /**
         * @var FindPasskeyAction $findAuthenticatableUsingPasskey
         */
        $findAuthenticatableUsingPasskey = Config::getAction(
            'find_passkey',
            FindPasskeyAction::class
        );

        $passkey = $findAuthenticatableUsingPasskey->execute(
            $request->get('answer'),
            Session::get('passkey-authentication-options'),
        );

        if (! $passkey) {
            return $this->invalidPasskeyResponse();
        }

        $authenticatable = $passkey->authenticatable;

        if (! $authenticatable) {
            return $this->invalidPasskeyResponse();
        }

        $this->logInAuthenticatable($authenticatable);

        event(new PasskeyUsedToAuthenticateEvent($passkey));

        return $this->validPasskeyResponse($request);
    }

    public function logInAuthenticatable(Authenticatable $authenticatable): self
    {
        auth()->login($authenticatable);

        Session::regenerate();

        return $this;
    }

    public function validPasskeyResponse(Request $request): RedirectResponse
    {
        $url = $request->has('redirect')
            ? $request->get('redirect')
            : config('passkeys.redirect_to_after_login');

        return redirect($url);
    }

    protected function invalidPasskeyResponse(): RedirectResponse
    {
        session()->flash('authenticatePasskey::message', __('passkeys::passkeys.invalid'));

        return back();
    }
}