# 玩家账号接入说明

玩家账号与 SaiAdmin 管理员账号完全隔离。玩家接口位于 `/api/v1`，使用 15 分钟 JWT Access Token 和 30 天不透明 Refresh Token；数据库仅保存 Refresh Token 摘要。

登录方式：邮箱 + 密码、邮箱 + 验证码、微信小程序、微信公众号 OAuth、抖音小程序。用户名仅作为公开显示名称，不参与登录匹配；注册时可留空，系统会从邮箱前缀生成一个可修改的显示名称。

## 环境配置

复制 `.env.example` 中 `PLAYER_*`、`MAIL_*`、`SMTP_*`、`WECHAT_MINI_PROGRAM_*`、`DOUYIN_MINI_PROGRAM_*` 与开放平台占位配置到本地 `.env`。JWT、令牌摘要、验证码 HMAC 密钥和 SMTP 密码必须使用独立随机值，不得提交。发件地址使用 `SMTP_FROM_ADDRESS`（兼容旧的 `MAIL_FROM_ADDRESS`）；修改配置后重启 Webman 与 `player_email` Redis Queue 消费进程。

默认头像使用邮箱首字母生成 SVG，并由后端上传到百度 BOS。必须配置 `BOS_ACCESS_KEY`、`BOS_SECRET_KEY`、`BOS_ENDPOINT`、`BOS_BUCKET` 和公开访问基址 `BOS_PUBLIC_BASE_URL`。对象键为 `avatars/default/{sha256(玩家 ID 前两位/玩家公开 ID)}.svg`，不包含邮箱，也不直接暴露玩家 ID。

登录用户可通过 `POST /api/v1/me/avatar` 上传字段名为 `avatar` 的头像。接口接受 PNG、JPEG 或 WebP，文件最大 5MB；上传完成后返回更新后的用户资料。自定义头像保存于 `avatars/custom/{散列用户目录}/{文件内容散列}.{扩展名}`。

## 第三方身份与账号策略

产品登录矩阵：

| 平台 | 登录方式 | provider | 后端入口 |
| --- | --- | --- | --- |
| 微信 | 小程序 `tt`/`wx.login` code | `wechat_mini_program` | `POST /api/v1/auth/login/mini-program`（`platform=wechat`） |
| 微信 | 公众号网页 OAuth | `wechat_official_account` | `GET /api/v1/auth/wechat-official/authorize-url` + `POST /api/v1/auth/login/wechat-official` |
| 抖音 | 小程序 code | `douyin_mini_program` | `POST /api/v1/auth/login/mini-program`（`platform=douyin`） |

说明：小程序 openid 与公众号 openid **不是**同一个 subject，即使 UnionID 相同，冷登录也按各自 provider 建号/复用；需要同一账号时请在登录后显式绑定。

身份表 `turtle_user_identities` 以 `(provider, provider_subject)` 唯一标识一条第三方身份；`users` 表不落微信/抖音字段。

冷登录解析顺序：

1. 按 `provider + provider_subject` 查找已有身份；命中则登录该用户，**不会**再建一条用户。
2. 查不到则新建一个玩家用户，并写入对应 identity 行。
3. **不**自动把邮箱账号、小程序账号、公众号账号合并。

已登录用户可显式绑定 / 解绑第三方身份：

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/api/v1/me/identities` | 当前账号已绑定身份列表（不返回 openid） |
| POST | `/api/v1/me/identities/mini-program` | body：`platform` + `code`（+ 可选 `anonymous_code`） |
| POST | `/api/v1/me/identities/wechat-official` | body：公众号 OAuth `code` |
| POST | `/api/v1/me/identities/open-platform` | 占位：开放平台 OAuth，尚未开放 |
| DELETE | `/api/v1/me/identities?provider=...` | 解绑指定 provider |

绑定规则：

- 该第三方身份尚未被占用 → 挂到当前用户。
- 已挂在当前用户 → 幂等成功，刷新 union/metadata。
- 已挂在其他用户 → `auth.identity_bound`（HTTP 409）。
- 无邮箱且解绑后将没有任何登录方式 → `auth.identity_last_login_method`（HTTP 409）。

### 微信公众号 OAuth

1. 前端先 `GET /api/v1/auth/wechat-official/authorize-url`（可选 `redirect_uri` / `state` / `scope`）拿到微信授权链接，或自行拼 open.weixin.qq.com 链接。
2. 用户在微信内授权后，回调带 `code`。
3. 冷登录：`POST /api/v1/auth/login/wechat-official`，body `{"code":"..."}`。
4. 已登录绑定：`POST /api/v1/me/identities/wechat-official`。

配置：`WECHAT_OFFICIAL_ACCOUNT_APP_ID` / `APP_SECRET` / `REDIRECT_URI` / `OAUTH_SCOPE`（默认 `snsapi_base`）。未配置返回 `auth.third_party_not_configured`。

开放平台 OAuth 仍保留占位路由 `/api/v1/auth/login/open-platform`，当前产品路径不走这里。

## 账号与匿名合并

注册、密码登录、邮箱验证码登录、小程序登录均可携带 `X-Anonymous-Token`。认证成功后，服务端在事务中把该匿名会话的游戏归属转移至玩家账号、记录合并审计并撤销匿名令牌。重复合并不会复制游戏、消息、提示、猜测或 AI 审计记录。

## 会话安全

- 同一玩家最多保留三个有效设备会话，第四台设备不会自动挤掉旧设备。
- Refresh Token 每次使用后轮换；复用旧令牌会撤销整个令牌族。
- 修改或找回密码会撤销全部旧会话并为当前设备重新签发会话。
- 换绑邮箱需要当前密码和新邮箱验证码，成功后撤销其他设备并异步通知旧邮箱。
- 玩家被后台禁用后，HTTP、刷新与 WebSocket 鉴权均拒绝继续使用。

## 迁移状态

迁移 `20260826010004_create_player_accounts.php` 与 `20260826010005_add_player_management_menu.php` 已于 2026-08-27 获授权后在本地 `turtle_soup` 数据库执行完成。

## 本地真实链路验收

2026-08-27 已完成邮箱验证码注册、邮箱密码/验证码登录、匿名游戏合并、BOS 默认头像、三设备限制、刷新令牌轮换与复用检测、指定会话撤销、全部退出、换绑邮箱、修改/找回密码及玩家 WebSocket 鉴权。测试账号保留；其最终密码为本地临时随机值，使用者应通过“忘记密码”页面设置自己的密码。
