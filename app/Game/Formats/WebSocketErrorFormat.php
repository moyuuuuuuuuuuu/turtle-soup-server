<?php

declare(strict_types=1);

namespace App\Game\Formats;

use App\Common\Enums\ErrorCode;
use App\Common\Exceptions\BaseException;
use JsonException;
use Throwable;

final class WebSocketErrorFormat
{
    /** @return array{code: string, retryable: bool} */
    public static function format(Throwable $exception): array
    {
        if ($exception instanceof BaseException) {
            $code = $exception->errorCode->code();
        } elseif ($exception instanceof JsonException) {
            $code = ErrorCode::PARAM_ERROR->value;
        } else {
            // Legacy protocol errors use exact codes; never return arbitrary exception text.
            $code = (ErrorCode::tryFrom($exception->getMessage()) ?? ErrorCode::SYSTEM_ERROR)->value;
            if ($exception->getMessage() === 'room.member_muted') {
                $code = 'room.member_muted';
            }
        }

        return ['code' => $code, 'retryable' => str_starts_with($code, 'ai.')];
    }
}
