<?php

// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\model\system;

use plugin\saiadmin\basic\eloquent\BaseModel;

/**
 * 操作日志模型
 *
 * sa_system_oper_log 操作日志表
 *
 * @property  $id 主键
 * @property  $username 用户名
 * @property  $app 应用名称
 * @property  $method 请求方式
 * @property string $router 请求路由
 * @property  $service_name 业务名称
 * @property  $ip 请求IP地址
 * @property  $ip_location IP所属地
 * @property string $request_data 请求数据
 * @property  $remark 备注
 * @property  $created_by 创建者
 * @property  $updated_by 更新者
 * @property  $create_time 创建时间
 * @property  $update_time 更新时间
 */
class SystemOperLog extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $primaryKey = 'id';

    protected $table = 'sa_system_oper_log';

    /** Redact legacy rows on read while deployment operators arrange historical cleanup. */
    public function getRequestDataAttribute($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $data = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($data)) {
            return '[REDACTED]';
        }
        return json_encode(\App\Common\Support\AdminLogRedactor::redact($data), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public function getRouterAttribute($value): string
    {
        return explode('?', (string) $value, 2)[0];
    }

}
