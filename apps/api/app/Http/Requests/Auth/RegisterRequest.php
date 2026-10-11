<?php

namespace App\Http\Requests\Auth;

use App\Rules\BcryptPassword;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends LoginRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', new BcryptPassword, 'confirmed', Password::min(12)],
        ];
    }
}
