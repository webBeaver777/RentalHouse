<?php

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Data\RegisterUserData;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function execute(RegisterUserData $data): User
    {
        return User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
            'phone' => $data->phone,
            'preferred_locale' => $data->preferred_locale,
        ]);
    }
}
