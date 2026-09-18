# 友链管理 · 后管前端对接说明

面向仓库：[`moyuuuuuuuuuuu/turtle-soup-admin`](https://github.com/moyuuuuuuuuuuu/turtle-soup-admin)（SaiAdmin 6.1.1 / Vue3 + TS + Element Plus）

对应后端：`turtle-soup-server` · 提交 `feat: add admin-managed friend links`

---

## 1. 产品策略（必须遵守）

| 能力 | 是否实现 | 说明 |
|---|---|---|
| 后台手工新增 / 编辑 / 删除 | **是** | 唯一录入入口 |
| 用户端提交友链申请 | **否** | 不要做表单、弹窗、申请页 |
| 审批流（待审 / 通过 / 驳回） | **否** | 数据只有「显示 / 隐藏」 |
| 站外收集申请 | 线下 | 继续用申请模板，由管理员手工录入后台 |

站外申请模板（管理员收到后照着填后台表单）：

```
【墨鱼海龟汤 · 友链申请】
站点名称：
站点地址：
Logo：
一句话简介：
回链地址：
联系邮箱：
```

**前端不要**创建任何 `apply` / `approve` / `reject` 相关页面或 API。

---

## 2. 现有页面怎么改

当前仓库已有可直接复用的范式：

| 参考模块 | 路径 | 可复用点 |
|---|---|---|
| 捐赠管理 | `src/views/donation/index.vue` | 列表 + 新增/编辑弹窗 + 删除确认 + 分页 + `v-permission` |
| 运营 API | `src/api/operations.ts` | `donationAdminApi` 的 list/save/update/destroy 写法 |
| 房间管理 | `src/views/room/index.vue` | 关键字 + 状态筛选列表 |
| 组件加载 | `src/router/core/ComponentLoader.ts` | 菜单 `component` 自动映射到 `src/views/**/*.vue` |

建议只做两件事：

1. 在 `src/api/operations.ts`（或新建 `src/api/friendLink.ts`）增加友链 API 模块与类型。
2. 新增页面 `src/views/friend-link/index.vue`（对齐菜单 component：`friend-link/index`）。

菜单路由来自后端菜单表，**不需要**在 `src/router/modules` 里手写静态路由。

---

## 3. 管理端 API（SaiAdmin 约定，非 `/api/v1`）

BaseURL：走现有 `VITE_API_URL`（SaiAdmin 管理前缀，与捐赠/房间一致）。

认证：`Authorization: Bearer <accessToken>`，沿用 `src/utils/http` 拦截器。

响应：SaiAdmin 管理接口包装；`src/utils/http` 在 `code === ApiStatus.success` 时把 `data` 交给业务层。列表直接读 `items` / `total`。

### 3.1 接口一览

| 方法 | 路径 | 权限 slug | 用途 |
|---|---|---|---|
| GET | `/core/friend-link/index` | `friend_link:index` | 分页列表 |
| POST | `/core/friend-link/save` | `friend_link:create` | 新增 |
| PUT | `/core/friend-link/update` | `friend_link:update` | 编辑 |
| DELETE | `/core/friend-link/destroy` | `friend_link:delete` | 批量删除 |

### 3.2 列表

```http
GET /core/friend-link/index?page=1&pageSize=20&keyword=&status=
```

| 参数 | 类型 | 说明 |
|---|---|---|
| `page` | number | 页码，默认 1 |
| `pageSize` | number | 每页条数，默认 20，最大 100 |
| `keyword` | string | 模糊匹配站点名称 / 站点地址 |
| `status` | boolean \| `''` | `true` 显示，`false` 隐藏，空串不过滤 |

`data`：

```json
{
  "items": [
    {
      "id": 1,
      "public_id": "00000000000000000000000001",
      "name": "墨鱼博客",
      "url": "https://example.com",
      "logo_url": "https://example.com/logo.png",
      "description": "一个海龟汤爱好者站点",
      "reciprocal_url": "https://your-game.example/friend-links",
      "contact_email": "owner@example.com",
      "status": true,
      "sort": 0,
      "create_time": "2026-08-28 12:00:00",
      "update_time": "2026-08-28 12:00:00"
    }
  ],
  "total": 1,
  "page": 1,
  "pageSize": 20
}
```

### 3.3 新增 / 编辑

```http
POST /core/friend-link/save
PUT  /core/friend-link/update
Content-Type: application/json
```

| 字段 | 必填 | 对应申请模板 | 校验 |
|---|---|---|---|
| `id` | 编辑时必填 | — | 仅 `update` 需要 |
| `name` | 是 | 站点名称 | 1–80 字 |
| `url` | 是 | 站点地址 | 必须 http/https，≤500 |
| `logo_url` | 否 | Logo | 若填则 http/https，≤500 |
| `description` | 否 | 一句话简介 | ≤255 |
| `reciprocal_url` | 否 | 回链地址 | 若填则 http/https，≤500 |
| `contact_email` | 否 | 联系邮箱 | 若填则合法邮箱，≤255；后端会存小写 |
| `status` | 否 | — | 默认 `true`（前台显示） |
| `sort` | 否 | — | 整数，越大越靠前 |

示例请求体：

```json
{
  "name": "墨鱼博客",
  "url": "https://example.com",
  "logo_url": "https://example.com/logo.png",
  "description": "一个海龟汤爱好者站点",
  "reciprocal_url": "https://example.com/links",
  "contact_email": "Owner@Example.com",
  "status": true,
  "sort": 10
}
```

### 3.4 删除

```http
DELETE /core/friend-link/destroy
Content-Type: application/json

{ "ids": [1, 2] }
```

`ids` 为空数组时后端返回 `request.param_error`。

### 3.5 错误码

| code | HTTP | 含义 | 前端建议 |
|---|---|---|---|
| `friend_link.not_found` | 404 | 记录不存在 | 提示后刷新列表 |
| `friend_link.url_invalid` | 422 | 站点/Logo/回链地址非法 | 提示检查 URL，可依赖后端 message |
| `request.param_error` | 422 | 名称/邮箱等校验失败 | 表单校验尽量前置 |

不要按中文文案分支，统一使用 `code`。

---

## 4. 用户端公开 API（后管不调用，仅知晓）

```http
GET /api/v1/friend-links
```

无需登录。响应信封为用户 API 格式：

```json
{
  "code": "success",
  "message": "success",
  "data": {
    "items": [
      {
        "id": "00000000000000000000000001",
        "name": "墨鱼博客",
        "url": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "description": "一个海龟汤爱好者站点"
      }
    ]
  },
  "request_id": "...",
  "timestamp": 1780000000
}
```

公开字段**只有**：`id`（public_id）、`name`、`url`、`logo_url`、`description`。

后管表单里的 `reciprocal_url`、`contact_email`、`status`、`sort`、内部自增 `id` **不会**下发到用户端。后管页面可以展示它们，但不要做成「用户可见预览」以外的公开契约。

---

## 5. 菜单与权限（后端已写好）

迁移：`database/migrations/20260828030000_create_friend_links.php`

执行迁移后菜单 ID：

| ID | 父级 | 名称 | type | component / path | slug |
|---|---|---|---|---|---|
| 900220 | 900200 游戏运营 | 友链管理 | 2 | `friend-link/index` / path `friend-links` | `friend_link:index` |
| 900221 | 900220 | 友链列表 | 3 | `/core/friend-link/index` | `friend_link:index` |
| 900222 | 900220 | 新增友链 | 3 | `/core/friend-link/save` | `friend_link:create` |
| 900223 | 900220 | 编辑友链 | 3 | `/core/friend-link/update` | `friend_link:update` |
| 900224 | 900220 | 删除友链 | 3 | `/core/friend-link/destroy` | `friend_link:delete` |

ComponentLoader 会把菜单 `component: 'friend-link/index'` 解析为：

```text
src/views/friend-link/index.vue
```

前端 `v-permission` 使用上表 slug，例如：

```vue
<ElButton v-permission="'friend_link:create'" @click="open()">新增友链</ElButton>
```

权限指令只是展示层；**真正鉴权在后端**。

---

## 6. 推荐实现代码

### 6.1 API 模块（追加到 `src/api/operations.ts` 或独立文件）

```ts
import request from '@/utils/http'

export interface FriendLinkRow {
  id: number
  public_id: string
  name: string
  url: string
  logo_url?: string | null
  description?: string | null
  reciprocal_url?: string | null
  contact_email?: string | null
  status: boolean
  sort: number
  create_time?: string
  update_time?: string
}

export interface FriendLinkPayload {
  id?: number
  name: string
  url: string
  logo_url?: string
  description?: string
  reciprocal_url?: string
  contact_email?: string
  status?: boolean
  sort?: number
}

export const friendLinkAdminApi = {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: FriendLinkRow[]; total: number; page: number; pageSize: number }>({
      url: '/core/friend-link/index',
      params
    }),
  save: (data: FriendLinkPayload) => request.post({ url: '/core/friend-link/save', data }),
  update: (data: FriendLinkPayload) => request.put({ url: '/core/friend-link/update', data }),
  destroy: (ids: number[]) => request.del({ url: '/core/friend-link/destroy', data: { ids } })
}
```

### 6.2 页面骨架 `src/views/friend-link/index.vue`

结构对齐 `src/views/donation/index.vue`：搜索栏 + 表格 + 分页 + 编辑弹窗。

```vue
<template>
  <div class="page-content">
    <ElCard shadow="never">
      <div class="mb-4 flex gap-3">
        <ElInput
          v-model="query.keyword"
          clearable
          placeholder="站点名称或地址"
          class="w-64"
        />
        <ElSelect v-model="query.status" clearable placeholder="显示状态" class="w-36">
          <ElOption label="显示" :value="true" />
          <ElOption label="隐藏" :value="false" />
        </ElSelect>
        <ElButton type="primary" @click="load">查询</ElButton>
        <ElButton v-permission="'friend_link:create'" @click="open()">新增友链</ElButton>
      </div>

      <ElTable v-loading="loading" :data="rows">
        <ElTableColumn prop="name" label="站点名称" min-width="140" />
        <ElTableColumn label="站点地址" min-width="200">
          <template #default="{ row }">
            <ElLink :href="row.url" target="_blank" type="primary">{{ row.url }}</ElLink>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Logo" width="90">
          <template #default="{ row }">
            <ElImage
              v-if="row.logo_url"
              :src="row.logo_url"
              style="width: 32px; height: 32px"
              fit="contain"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn prop="description" label="一句话简介" min-width="160" show-overflow-tooltip />
        <ElTableColumn prop="reciprocal_url" label="回链地址" min-width="180" show-overflow-tooltip />
        <ElTableColumn prop="contact_email" label="联系邮箱" min-width="160" />
        <ElTableColumn prop="sort" label="排序" width="80" />
        <ElTableColumn label="状态" width="90">
          <template #default="{ row }">
            <ElTag :type="row.status ? 'success' : 'info'">
              {{ row.status ? '显示' : '隐藏' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="create_time" label="创建时间" min-width="170" />
        <ElTableColumn label="操作" width="140">
          <template #default="{ row }">
            <ElButton v-permission="'friend_link:update'" link @click="open(row)">编辑</ElButton>
            <ElButton v-permission="'friend_link:delete'" link type="danger" @click="remove(row)">
              删除
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>

      <ElPagination
        class="mt-4 justify-end"
        layout="total, prev, pager, next"
        :total="total"
        :page-size="query.pageSize"
        @current-change="
          (page: number) => {
            query.page = page
            load()
          }
        "
      />
    </ElCard>

    <ElDialog v-model="editVisible" :title="form.id ? '编辑友链' : '新增友链'" width="560px">
      <ElForm label-width="100px">
        <ElFormItem label="站点名称" required>
          <ElInput v-model="form.name" maxlength="80" placeholder="对方站点名称" />
        </ElFormItem>
        <ElFormItem label="站点地址" required>
          <ElInput v-model="form.url" placeholder="https://" />
        </ElFormItem>
        <ElFormItem label="Logo">
          <ElInput v-model="form.logo_url" placeholder="https://（选填）" />
        </ElFormItem>
        <ElFormItem label="一句话简介">
          <ElInput v-model="form.description" maxlength="255" placeholder="选填" />
        </ElFormItem>
        <ElFormItem label="回链地址">
          <ElInput v-model="form.reciprocal_url" placeholder="对方回链本站的地址（选填）" />
        </ElFormItem>
        <ElFormItem label="联系邮箱">
          <ElInput v-model="form.contact_email" placeholder="选填" />
        </ElFormItem>
        <ElFormItem label="排序">
          <ElInputNumber v-model="form.sort" :min="0" />
        </ElFormItem>
        <ElFormItem label="前台显示">
          <ElSwitch v-model="form.status" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="editVisible = false">取消</ElButton>
        <ElButton type="primary" @click="save">保存</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    friendLinkAdminApi,
    type FriendLinkPayload,
    type FriendLinkRow
  } from '@/api/operations'

  const rows = ref<FriendLinkRow[]>([])
  const total = ref(0)
  const loading = ref(false)
  const editVisible = ref(false)
  const query = reactive({ keyword: '', status: '' as boolean | '', page: 1, pageSize: 20 })
  const form = reactive<FriendLinkPayload>({
    id: undefined,
    name: '',
    url: '',
    logo_url: '',
    description: '',
    reciprocal_url: '',
    contact_email: '',
    status: true,
    sort: 0
  })

  function emptyForm(): FriendLinkPayload {
    return {
      id: undefined,
      name: '',
      url: '',
      logo_url: '',
      description: '',
      reciprocal_url: '',
      contact_email: '',
      status: true,
      sort: 0
    }
  }

  function normalizePayload(): FriendLinkPayload {
    return {
      ...(form.id ? { id: form.id } : {}),
      name: form.name.trim(),
      url: form.url.trim(),
      logo_url: form.logo_url?.trim() || '',
      description: form.description?.trim() || '',
      reciprocal_url: form.reciprocal_url?.trim() || '',
      contact_email: form.contact_email?.trim() || '',
      status: form.status ?? true,
      sort: Number(form.sort) || 0
    }
  }

  function isHttpUrl(value: string): boolean {
    if (!value) return true
    try {
      const url = new URL(value)
      return url.protocol === 'http:' || url.protocol === 'https:'
    } catch {
      return false
    }
  }

  function validate(): boolean {
    if (!form.name.trim() || form.name.trim().length > 80) {
      ElMessage.warning('站点名称必填且不超过 80 字')
      return false
    }
    if (!isHttpUrl(form.url.trim()) || !form.url.trim()) {
      ElMessage.warning('站点地址必须是 http/https 地址')
      return false
    }
    if (!isHttpUrl(form.logo_url?.trim() || '')) {
      ElMessage.warning('Logo 地址必须是 http/https 地址')
      return false
    }
    if (!isHttpUrl(form.reciprocal_url?.trim() || '')) {
      ElMessage.warning('回链地址必须是 http/https 地址')
      return false
    }
    const email = form.contact_email?.trim() || ''
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      ElMessage.warning('联系邮箱格式不正确')
      return false
    }
    return true
  }

  async function load() {
    loading.value = true
    try {
      const data = await friendLinkAdminApi.list({
        keyword: query.keyword,
        status: query.status === '' ? '' : query.status,
        page: query.page,
        pageSize: query.pageSize
      })
      rows.value = data.items
      total.value = data.total
    } finally {
      loading.value = false
    }
  }

  function open(row?: FriendLinkRow) {
    Object.assign(
      form,
      row
        ? {
            id: row.id,
            name: row.name,
            url: row.url,
            logo_url: row.logo_url || '',
            description: row.description || '',
            reciprocal_url: row.reciprocal_url || '',
            contact_email: row.contact_email || '',
            status: row.status,
            sort: row.sort
          }
        : emptyForm()
    )
    editVisible.value = true
  }

  async function save() {
    if (!validate()) return
    const payload = normalizePayload()
    if (payload.id) await friendLinkAdminApi.update(payload)
    else await friendLinkAdminApi.save(payload)
    editVisible.value = false
    ElMessage.success('保存成功')
    await load()
  }

  async function remove(row: FriendLinkRow) {
    await ElMessageBox.confirm(`确定删除友链「${row.name}」？`)
    await friendLinkAdminApi.destroy([row.id])
    ElMessage.success('删除成功')
    await load()
  }

  onMounted(load)
</script>
```

实现时可按仓库现有 prettier / eslint 风格微调模板写法（捐赠页把标签写得更紧凑）。

---

## 7. 前端验收清单

- [ ] `pnpm install && pnpm dev` 能启动。
- [ ] 登录超管后，侧边栏出现「游戏运营 → 友链管理」。
- [ ] 打开页面对应 `src/views/friend-link/index.vue`，无 `ComponentLoader Missing component`。
- [ ] 列表接口：`GET /core/friend-link/index` 返回 `items` + `total`。
- [ ] 新增：按申请模板填六项字段 + 状态/排序，保存后列表可见。
- [ ] 编辑：能回显 `reciprocal_url`、`contact_email`，保存后更新。
- [ ] 非法 URL / 空名称：前端提示或后端 `friend_link.url_invalid` / `request.param_error`。
- [ ] 删除：二次确认后调用 `DELETE /core/friend-link/destroy`。
- [ ] 按钮权限：无 `friend_link:create` 等权限的账号不显示对应操作（仍以后端鉴权为准）。
- [ ] **不存在**任何用户端申请、审批相关页面或接口调用。
- [ ] 管理端列表里能看到回链与邮箱；公开用户 API（若联调）不含这两项。

联调前提：

1. 后端已执行迁移 `20260828030000_create_friend_links.php`（含菜单 900220–900224）。
2. `ProjectAdminMenuSeeder` 已把菜单授权给目标角色（超管通常自动拥有）。
3. 后管 `.env` 的 `VITE_API_URL` 指向本地/测试 Webman 管理接口。

---

## 8. 边界与注意

- 管理接口用 SaiAdmin 信封，**不要**按 `/api/v1` 的 `code/request_id/timestamp` 写解析逻辑。
- `public_id` 仅作对外标识；管理端删除/更新使用数字 `id`。
- `status=false` 表示前台不展示，记录仍保留，可再次打开，不必删除。
- Logo 目前是 URL 字符串；若后续要对象存储上传，可参照捐赠「收款码」的 `FormData` + BOS 上传，但**当前后端友链未提供上传接口**，请先填 URL。
- 前端校验只是体验优化，后端仍会校验 URL/邮箱。
- 变更已合入后端 `main`（`1297736 feat: add admin-managed friend links`）。迁移未跑库前，管理接口会因表不存在而失败。

---

## 9. 相关文件索引

**后端（turtle-soup-server）**

- `app/FriendLink/**`
- `database/migrations/20260828030000_create_friend_links.php`
- `config/route.php` → `GET /api/v1/friend-links`
- `plugin/saiadmin/config/route.php` → `/core/friend-link/*`
- `app/Common/Enums/ErrorCode.php` → `friend_link.*`
- `tests/FriendLink/FriendLinkContractTest.php`

**前端（turtle-soup-admin）待新增**

- `src/api/operations.ts`（或 `src/api/friendLink.ts`）
- `src/views/friend-link/index.vue`

**前端可参照**

- `src/views/donation/index.vue`
- `src/api/operations.ts` 中 `donationAdminApi`
- `src/views/room/index.vue`
