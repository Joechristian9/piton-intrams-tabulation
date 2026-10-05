<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;

/**
 * Creates an event's judge accounts: "Judge n", username "{code}-judge{n}", and a
 * random readable password. An encrypted copy of the password is kept so the
 * admin can reveal or reprint credential slips (cleared if the judge changes it).
 */
class JudgeAccounts
{
    /** No look-alikes (0/O, 1/l/I). */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';

    public static function password(): string
    {
        $password = '';
        for ($i = 0; $i < 8; $i++) {
            $password .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $password;
    }

    /** @return Collection<int, User> */
    public static function create(Event $event, int $count): Collection
    {
        $prefix = "{$event->code}-judge";
        $highest = $event->judges()->pluck('username')
            ->map(fn ($u) => str_starts_with((string) $u, $prefix) ? (int) substr($u, strlen($prefix)) : 0)
            ->max() ?? 0;

        return collect(range($highest + 1, $highest + $count))->map(function (int $n) use ($event, $prefix) {
            $judge = User::create([
                'name' => "Judge {$n}",
                'username' => "{$prefix}{$n}",
                'email' => "{$prefix}{$n}@judges.local",
                'password' => $plain = self::password(),
                'role' => 'judge',
                'event_id' => $event->id,
            ]);
            $judge->forceFill([
                'email_verified_at' => now(),
                'password_plain_encrypted' => Crypt::encryptString($plain),
            ])->save();

            return $judge;
        });
    }

    /** Gives the judge a new password; returns it. */
    public static function resetPassword(User $judge): string
    {
        $plain = self::password();
        $judge->forceFill([
            'password' => $plain,
            'password_plain_encrypted' => Crypt::encryptString($plain),
        ])->save();

        return $plain;
    }

    public static function revealPassword(User $judge): ?string
    {
        try {
            return $judge->password_plain_encrypted ? Crypt::decryptString($judge->password_plain_encrypted) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
