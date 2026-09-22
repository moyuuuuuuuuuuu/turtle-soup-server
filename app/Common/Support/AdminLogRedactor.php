<?php

declare(strict_types=1);

namespace App\Common\Support;

final class AdminLogRedactor
{
    /** @param array<array-key, mixed> $values
     *  @return array<array-key, mixed>
     */
    public static function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            $normalized = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $key));
            if (preg_match('/password|passwd|secret|token|authorization|cookie|credential|apikey|verificationcode|emailcode/', $normalized)
                || in_array($normalized, ['code', 'answer', 'solution', 'content', 'messages', 'email', 'phone', 'mobile', 'contactemail'], true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = self::redact($value);
            } elseif (is_object($value)) {
                $values[$key] = '[OBJECT]';
            }
        }
        return $values;
    }
}
