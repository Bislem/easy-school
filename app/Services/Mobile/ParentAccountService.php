<?php

namespace App\Services\Mobile;

use App\Mail\ParentTemporaryPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ParentAccountService
{
    public function issueTemporaryPassword(User $user, string $reason): void
    {
        $password = Str::password(20, true, true, true, false);
        $user->forceFill([
            'password' => Hash::make($password),
            'temporary_password_expires_at' => now()->addMinutes(30),
        ])->save();
        $user->tokens()->delete();
        Mail::to($user->email)->send(new ParentTemporaryPasswordMail($user, $password, $reason));
    }
}
