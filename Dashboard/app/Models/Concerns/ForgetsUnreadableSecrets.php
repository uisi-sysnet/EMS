<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Encryption\DecryptException;

/**
 * For models with an 'encrypted' cast (Telegram bot token, Sentinel key,
 * camera password). When APP_KEY changes, the saved value can't be
 * decrypted, and saving a NEW value fails too: Eloquent decrypts the old
 * value to check whether the attribute changed, throwing "The MAC is
 * invalid". Call forgetUnreadable() before assigning the new value.
 *
 * It only forgets the old value in memory (nothing is written; the column
 * may be NOT NULL), and only if it really can't be decrypted with APP_KEY
 * or APP_PREVIOUS_KEYS. The next save then simply writes the new value.
 */
trait ForgetsUnreadableSecrets
{
    public function forgetUnreadable(string $attribute): bool
    {
        $raw = $this->getRawOriginal($attribute);
        if (blank($raw)) {
            return false;
        }

        try {
            static::currentEncrypter()->decrypt($raw, false);
            return false;
        } catch (DecryptException $e) {
            // Unreadable: treat the stored value as empty for the dirty check.
        }

        $this->original[$attribute] = null;
        if (($this->attributes[$attribute] ?? null) === $raw) {
            $this->attributes[$attribute] = null;
        }

        return true;
    }
}
