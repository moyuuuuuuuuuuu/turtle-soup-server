<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use plugin\saiadmin\app\cache\ReflectionCache;
use plugin\saiadmin\exception\ApiException;

/**
 * 登录检查中间件
 */
class CheckLogin implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        // 通过反射获取控制器哪些方法不需要登录
        $noNeedLogin = ReflectionCache::getNoNeedLogin($request->controller);
        // 访问的方法需要登录
        if (!in_array($request->action, $noNeedLogin)) {
            try {
                $token = (new \App\Admin\Services\AdminSessionService())->authenticate()['extend'];
            } catch (\Tinywan\Jwt\Exception\JwtTokenException | \Tinywan\Jwt\Exception\JwtTokenExpiredException | ApiException $e) {
                throw new ApiException('您的登录凭证错误或者已过期，请重新登录', 401);
            } catch (\Throwable $e) {
                throw new ApiException('登录校验服务暂时不可用，请稍后重试', 503);
            }
            if ($token['plat'] !== 'saiadmin') {
                throw new ApiException('登录凭证校验失败');
            }
            $request->setHeader('check_login', true);
            $request->setHeader('check_admin', $token);
        }
        return $handler($request);
    }
}
