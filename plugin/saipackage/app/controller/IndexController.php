<?php

namespace plugin\saipackage\app\controller;

use plugin\saiadmin\basic\OpenController;
use Saithink\Saipackage\service\Terminal;
use support\Request;
use support\Response;
use Throwable;
use Workerman\Protocols\Http\ServerSentEvents;
use App\Admin\Middleware\AdminTerminalMiddleware;
use support\annotation\Middleware;

#[Middleware(AdminTerminalMiddleware::class)]
class IndexController extends OpenController
{
    /**
     * 执行终端
     * @param Request $request
     * @return void
     * @throws Throwable
     */
    public function terminal(Request $request): void
    {
        // SSE 消息
        $connection = $request->connection;
        $headers = [
            'Content-Type'                     => 'text/event-stream',
            'Cache-Control'                    => 'no-cache',
            'Connection'                       => 'keep-alive',
            'X-Accel-Buffering'                => 'no',
        ];
        $origin = (string) $request->header('origin', '');
        if ($origin !== '' && in_array($origin, (array) config('cors.allowed_origins', []), true)) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Vary'] = 'Origin';
        }
        $connection->send(new Response(200, $headers, "\r\n"));

        // 消息开始
        $connection->send(new ServerSentEvents([
            'event' => 'message', 'data' => 'start'
        ]));

        // 生成器
        // Authentication, session revocation and super-admin checks run before streaming.
        $generator = (new Terminal())->exec(false);
        foreach ($generator as $chunk) {
            $connection->send(new ServerSentEvents([
                'event' => 'message', 'data' => $chunk
            ]));
        }

        // 关闭链接
        $connection->close();
    }

}
