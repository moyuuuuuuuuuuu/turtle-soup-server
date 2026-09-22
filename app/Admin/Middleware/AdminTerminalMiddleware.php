<?php

declare(strict_types=1);

namespace App\Admin\Middleware;

use App\Admin\Services\AdminSessionService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class AdminTerminalMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AdminSessionService $sessions = new AdminSessionService())
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        if ($request->method() !== 'POST') {
            return new Response(405, ['Allow' => 'POST']);
        }
        // The old token-in-query/body protocol is deliberately unsupported.
        $authorization = (string) $request->header('authorization', '');
        if (!preg_match('/\ABearer ([^\s]+)\z/i', $authorization, $matches)) {
            return new Response(401);
        }
        try {
            $payload = $this->sessions->authenticate($matches[1]);
        } catch (\Throwable) {
            return new Response(401);
        }
        if ($payload['extend']['id'] !== 1) {
            return new Response(403);
        }
        return $handler($request);
    }
}
