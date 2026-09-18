# 后管前端如何配合实现友链功能

对照后端仓库 `turtle-soup-server`（`system-manage`）已落地的友链模块，以及后管仓库 [turtle-soup-admin](https://github.com/moyuuuuuuuuuuu/turtle-soup-admin)（SaiAdmin / Vue3 / Element Plus）的既有约定，说明管理端页面应如何实现「仅后台手工添加」的友链能力。

| 项 | 说明 |
| --- | --- |
| 后端 | `system-manage` / `turtle-soup-server` |
| 前端 | `turtle-soup-admin` |
| 模式 | 仅后台维护 |
| 用户端 | 无申请、无审批 |

## 1. 目标与边界

**产品策略：** 友链只能由管理员在后管后台手工录入。系统**不提供**用户端申请入口，也**不提供**申请后的审核流。站外收到的申请模板由人工筛选后，再在后台录入。

| 要做 | 不做 |
| --- | --- |
| 后管「游戏运营 → 友链管理」列表页：查询、新增、编辑、删除、启停、排序 | 不要写用户端申请页、不要写「待审核」状态、不要暴露回链地址/联系邮箱到公开 API |

## 2. 申请模板字段映射

站外申请模板仍可继续使用；后管表单字段与之对齐：

```text
【墨鱼海龟汤 · 友链申请】
站点名称：
站点地址：
Logo：
一句话简介：
回链地址：
联系邮箱：
```

| 申请模板 | 后管字段 | 数据库字段 | 必填 | 公开 API | 说明 |
| --- | --- | --- | --- | --- | --- |
| 站点名称 | `name` | `name` | 是 | 是 | ≤ 80 字 |
| 站点地址 | `url` | `url` | 是 | 是 | http/https，≤ 500 |
| Logo | `logo_url` | `logo_url` | 否 | 是 | 若填则必须 http/https |
| 一句话简介 | `description` | `description` | 否 | 是 | ≤ 255 字 |
| 回链地址 | `reciprocal_url` | `reciprocal_url` | 否 | 否 | 仅管理员核对回链用 |
| 联系邮箱 | `contact_email` | `contact_email` | 否 | 否 | 格式校验，存库转小写 |
| — | `status` | `status` | 否（默认显示） | 否 | `true` 显示 / `false` 隐藏 |
| — | `sort` | `sort` | 否（默认 0） | 否 | 数值越大越靠前 |

## 3. 后端现状（已提交）

提交：`1297736 feat: add admin-managed friend links`，分支 `main` → `origin/main`。

- 模块：`app/FriendLink/`（Model / Repository / Business / Format / Controllers）
- 迁移：`database/migrations/20260828030000_create_friend_links.php`（表 `turtle_friend_links` + 菜单 900220–900224）
- 公开只读：`GET /api/v1/friend-links`
- 管理端 CRUD：`/core/friend-link/index|save|update|destroy`
- 错误码：`friend_link.not_found`、`friend_link.url_invalid`

> 迁移文件已入库，但数据库尚未执行。前端联调前请先在后端环境跑 Phinx 迁移，并确认角色已拥有友链菜单权限。

## 4. 前端要做什么

请在 `turtle-soup-admin` 仓库（Vue3 + TS + Element Plus + Art Design Pro / SaiAdmin）中，按既有 **捐赠管理** 模式新增友链页，不要另起一套框架设施。

1. 在 `src/api/operations.ts`（或独立 `src/api/friend-link.ts`）增加 `friendLinkAdminApi` 与类型。
2. 新增页面 `src/views/friend-link/index.vue`，与捐赠页同构：搜索条 + 表格 + 分页 + 编辑弹窗。
3. 菜单由后端迁移写入：路径 `friend-link/index`，组件 `friend-link/index`，挂在「游戏运营」下。
4. 按钮使用权限指令，例如 `v-permission="'friend_link:create'"`；最终以后端权限为准。
5. **不要**在用户端（uni-app `ui` 分支）做申请表单。

## 5. 管理端 API 契约

管理接口走 SaiAdmin 约定。开发环境通常通过：

- `VITE_API_URL=/api`
- `VITE_API_PROXY_URL=http://hgt.test`

Vite 代理重写后命中后端 `/core/friend-link/*`。**不要**套用用户 API `/api/v1/*` 的信封。

### 5.1 列表

`GET /core/friend-link/index`

| 参数 | 类型 | 说明 |
| --- | --- | --- |
| `page` | number | 默认 1 |
| `pageSize` | number | 默认 20，最大 100 |
| `keyword` | string | 模糊匹配名称 / 站点地址 |
| `status` | boolean | 可选；空串表示不过滤 |

响应示例：

```json
{
  "items": [
    {
      "id": 12,
      "public_id": "000000000000000000000000AB",
      "name": "示例站点",
      "url": "https://example.com",
      "logo_url": "https://example.com/logo.png",
      "description": "伙伴站点",
      "reciprocal_url": "https://example.com/backlink",
      "contact_email": "owner@example.com",
      "status": true,
      "sort": 10,
      "create_time": "2026-08-28 12:00:00",
      "update_time": "2026-08-28 12:00:00"
    }
  ],
  "total": 1,
  "page": 1,
  "pageSize": 20
}
```

### 5.2 新增

`POST /core/friend-link/save`，body 为 JSON：

```json
{
  "name": "示例站点",
  "url": "https://example.com",
  "logo_url": "https://example.com/logo.png",
  "description": "伙伴站点",
  "reciprocal_url": "https://example.com/backlink",
  "contact_email": "owner@example.com",
  "status": true,
  "sort": 10
}
```

### 5.3 编辑

`PUT /core/friend-link/update`，body 必须带 `id`，其余字段同新增。

### 5.4 删除

`DELETE /core/friend-link/destroy`，body：

```json
{ "ids": [12, 13] }
```

### 5.5 稳定错误码（可编程处理）

| code | HTTP | 含义 | 前端建议提示 |
| --- | --- | --- | --- |
| `friend_link.not_found` | 404 | 记录不存在 | 刷新列表 |
| `friend_link.url_invalid` | 422 | 站点/Logo/回链地址不合法 | 提示检查 URL |
| `request.param_error` | 422 | 名称缺失、邮箱格式错误等 | 按 message 提示 |

错误分支请读稳定英文 `code`，不要根据中文 message 做逻辑判断。

### 5.6 用户端公开接口（仅参考，后管不调用）

`GET /api/v1/friend-links` 返回用户端信封：

```json
{
  "code": "success",
  "message": "success",
  "data": {
    "items": [
      {
        "id": "PUBLIC_ID",
        "name": "示例站点",
        "url": "https://example.com",
        "logo_url": "https://example.com/logo.png",
        "description": "伙伴站点"
      }
    ]
  },
  "request_id": "...",
  "timestamp": 0
}
```

`data.items` 仅含 `id`（public_id）、`name`、`url`、`logo_url`、`description`，**不含**回链与邮箱。

## 6. 建议文件结构

与捐赠模块对齐，推荐：

```text
turtle-soup-admin/
├── src/
│   ├── api/
│   │   └── operations.ts          # 追加 friendLinkAdminApi + 类型
│   │                              # 或新建 src/api/friend-link.ts
│   └── views/
│       └── friend-link/
│           └── index.vue          # 友链管理主页面（列表 + 编辑弹窗）
```

菜单由后端 `sa_system_menu.component = friend-link/index` 动态下发，前端只需保证视图路径可被路由解析为 `src/views/friend-link/index.vue`。

## 7. 页面交互规格

| 区域 | 交互 | 字段 / 行为 |
| --- | --- | --- |
| 搜索条 | keyword + 查询 | 可选 status 筛选（显示/隐藏） |
| 操作按钮 | 新增友链 | `v-permission="'friend_link:create'"` |
| 表格列 | 名称、站点地址、Logo 预览、简介、回链、邮箱、状态、排序 | 地址可点击新窗口打开；Logo 缩略图 |
| 状态列 | Tag | `true`=显示 / `false`=隐藏 |
| 行操作 | 编辑 / 删除 | 权限 `friend_link:update` / `friend_link:delete` |
| 编辑弹窗 | 表单校验 | 见下方规则 |
| 成功反馈 | Message + 重载列表 | 与捐赠页一致 |

### 表单校验（与后端一致）

- `name`：必填，长度 1–80
- `url`：必填，http/https
- `logo_url` / `reciprocal_url`：选填；非空则 http/https
- `contact_email`：选填；非空则邮箱格式
- `description`：选填，≤ 255
- `status`：开关，默认 `true`
- `sort`：整数，默认 `0`

## 8. 可直接粘贴的代码

### 8.1 API 模块（追加到 `src/api/operations.ts`）

```ts
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
  create_time: string
  update_time: string
}

export interface FriendLinkForm {
  id?: number
  name: string
  url: string
  logo_url?: string
  description?: string
  reciprocal_url?: string
  contact_email?: string
  status: boolean
  sort: number
}

export const friendLinkAdminApi = {
  list: (params: Record<string, unknown>) =>
    request.get<{ items: FriendLinkRow[]; total: number }>({
      url: '/core/friend-link/index',
      params
    }),
  save: (data: FriendLinkForm) =>
    request.post({ url: '/core/friend-link/save', data }),
  update: (data: FriendLinkForm) =>
    request.put({ url: '/core/friend-link/update', data }),
  destroy: (ids: number[]) =>
    request.del({ url: '/core/friend-link/destroy', data: { ids } })
}
```

### 8.2 页面骨架 `src/views/friend-link/index.vue`

```vue
<template>
  <div class="page-content">
    <ElCard shadow="never">
      <div class="mb-4 flex gap-3">
        <ElInput v-model="query.keyword" clearable placeholder="站点名称 / 地址" class="w-60" />
        <ElSelect v-model="query.status" clearable placeholder="状态" class="w-36">
          <ElOption label="显示" :value="true" />
          <ElOption label="隐藏" :value="false" />
        </ElSelect>
        <ElButton type="primary" @click="load">查询</ElButton>
        <ElButton v-permission="'friend_link:create'" @click="open()">新增友链</ElButton>
      </div>

      <ElTable :data="rows">
        <ElTableColumn prop="name" label="站点名称" min-width="140" />
        <ElTableColumn label="站点地址" min-width="220">
          <template #default="{ row }">
            <a :href="row.url" target="_blank" rel="noopener noreferrer">{{ row.url }}</a>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Logo" width="100">
          <template #default="{ row }">
            <img v-if="row.logo_url" :src="row.logo_url" class="h-8 max-w-16 object-contain" alt="" />
          </template>
        </ElTableColumn>
        <ElTableColumn prop="description" label="简介" min-width="160" show-overflow-tooltip />
        <ElTableColumn prop="reciprocal_url" label="回链地址" min-width="180" show-overflow-tooltip />
        <ElTableColumn prop="contact_email" label="联系邮箱" min-width="160" />
        <ElTableColumn label="状态" width="80">
          <template #default="{ row }">
            <ElTag :type="row.status ? 'success' : 'info'">
              {{ row.status ? '显示' : '隐藏' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="sort" label="排序" width="80" />
        <ElTableColumn label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <ElButton link @click="open(row)">编辑</ElButton>
            <ElButton link type="danger" @click="remove(row)">删除</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>

      <ElPagination
        class="mt-4 justify-end"
        layout="total, prev, pager, next"
        :total="total"
        :page-size="query.pageSize"
        @current-change="(page: number) => { query.page = page; load() }"
      />
    </ElCard>

    <ElDialog v-model="editVisible" :title="form.id ? '编辑友链' : '新增友链'" width="560px">
      <ElForm ref="formRef" :model="form" :rules="rules" label-width="100px">
        <ElFormItem label="站点名称" prop="name">
          <ElInput v-model="form.name" maxlength="80" show-word-limit />
        </ElFormItem>
        <ElFormItem label="站点地址" prop="url">
          <ElInput v-model="form.url" placeholder="https://" />
        </ElFormItem>
        <ElFormItem label="Logo" prop="logo_url">
          <ElInput v-model="form.logo_url" placeholder="https://（选填）" />
        </ElFormItem>
        <ElFormItem label="一句话简介" prop="description">
          <ElInput v-model="form.description" maxlength="255" show-word-limit />
        </ElFormItem>
        <ElFormItem label="回链地址" prop="reciprocal_url">
          <ElInput v-model="form.reciprocal_url" placeholder="对方站点上的回链（选填）" />
        </ElFormItem>
        <ElFormItem label="联系邮箱" prop="contact_email">
          <ElInput v-model="form.contact_email" placeholder="选填" />
        </ElFormItem>
        <ElFormItem label="前台显示">
          <ElSwitch v-model="form.status" />
        </ElFormItem>
        <ElFormItem label="排序">
          <ElInputNumber v-model="form.sort" :min="0" />
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
  import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
  import {
    friendLinkAdminApi,
    type FriendLinkForm,
    type FriendLinkRow
  } from '@/api/operations'

  const rows = ref<FriendLinkRow[]>([])
  const total = ref(0)
  const editVisible = ref(false)
  const formRef = ref<FormInstance>()
  const query = reactive({
    keyword: '',
    status: undefined as boolean | undefined,
    page: 1,
    pageSize: 20
  })

  const emptyForm = (): FriendLinkForm => ({
    name: '',
    url: '',
    logo_url: '',
    description: '',
    reciprocal_url: '',
    contact_email: '',
    status: true,
    sort: 0
  })
  const form = reactive<FriendLinkForm>(emptyForm())

  const isHttpUrl = (value: string) =>
    !value || /^https?:\/\/.+/i.test(value)

  const rules: FormRules = {
    name: [{ required: true, message: '请输入站点名称', trigger: 'blur' }],
    url: [
      { required: true, message: '请输入站点地址', trigger: 'blur' },
      {
        validator: (_r, value, cb) =>
          isHttpUrl(String(value || '')) ? cb() : cb(new Error('必须是 http/https 地址')),
        trigger: 'blur'
      }
    ],
    logo_url: [
      {
        validator: (_r, value, cb) =>
          isHttpUrl(String(value || '')) ? cb() : cb(new Error('Logo 地址必须是 http/https')),
        trigger: 'blur'
      }
    ],
    reciprocal_url: [
      {
        validator: (_r, value, cb) =>
          isHttpUrl(String(value || '')) ? cb() : cb(new Error('回链地址必须是 http/https')),
        trigger: 'blur'
      }
    ],
    contact_email: [
      {
        validator: (_r, value, cb) => {
          const v = String(value || '')
          cb(
            !v || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
              ? undefined
              : new Error('联系邮箱格式不正确')
          )
        },
        trigger: 'blur'
      }
    ]
  }

  async function load() {
    const params: Record<string, unknown> = {
      keyword: query.keyword,
      page: query.page,
      pageSize: query.pageSize
    }
    if (query.status !== undefined) params.status = query.status
    const data = await friendLinkAdminApi.list(params)
    rows.value = data.items
    total.value = data.total
  }

  function open(row?: FriendLinkRow) {
    Object.assign(
      form,
      emptyForm(),
      row
        ? {
            ...row,
            logo_url: row.logo_url || '',
            description: row.description || '',
            reciprocal_url: row.reciprocal_url || '',
            contact_email: row.contact_email || ''
          }
        : {}
    )
    editVisible.value = true
  }

  async function save() {
    await formRef.value?.validate()
    const payload = { ...form }
    if (!payload.logo_url) payload.logo_url = ''
    if (!payload.description) payload.description = ''
    if (!payload.reciprocal_url) payload.reciprocal_url = ''
    if (!payload.contact_email) payload.contact_email = ''
    if (form.id) {
      await friendLinkAdminApi.update(payload)
    } else {
      await friendLinkAdminApi.save(payload)
    }
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

## 9. 菜单与权限

后端迁移已预留菜单（父级为「游戏运营」`900200`）：

| ID | 名称 | type | path / slug | component |
| --- | --- | --- | --- | --- |
| 900220 | 友链管理 | 菜单 | `friend-link/index` | `friend-link/index` |
| 900221 | 友链列表 | 权限 | `friend_link:index` | — |
| 900222 | 新增友链 | 权限 | `friend_link:create` | — |
| 900223 | 编辑友链 | 权限 | `friend_link:update` | — |
| 900224 | 删除友链 | 权限 | `friend_link:delete` | — |

Super Admin 角色由 `ProjectAdminMenuSeeder` 自动补齐上述菜单。若使用其他角色，需在后管「角色管理」中勾选友链菜单与按钮权限。

前端 `v-permission` 只是展示层辅助；真正拦截在后端 `#[Permission(...)]` 与 `CheckAuth` 中间件。

## 10. 联调与验收

### 前置

1. 后端迁移已执行，表 `turtle_friend_links` 可用。
2. 后管登录账号拥有友链菜单权限。
3. `pnpm dev` 代理指向本地/开发后端（如 `http://hgt.test`）。
4. Webman 进程已加载新路由（必要时 reload）。

### 验收清单

1. 侧边栏「游戏运营 → 友链管理」可打开列表页。
2. 按申请模板六字段 + 状态/排序新增一条友链，列表可见。
3. 编辑可改全部字段；删除二次确认后消失。
4. 非 http/https 地址、空名称、非法邮箱会提示错误。
5. 用户端（或 curl）访问 `GET /api/v1/friend-links`：仅返回启用中记录，且不含 `reciprocal_url`、`contact_email`。
6. 隐藏（`status=false`）后，公开接口不再返回该友链。
7. 排序大的友链在公开列表中更靠前。
8. 无权限账号看不到友链菜单，直接调管理接口返回鉴权失败。

## 11. 注意事项

- **无申请流：** 不要给后管加「待审核友链」页面，也不要在 `ui` 用户端做申请表单。
- **字段边界：** 回链、邮箱只服务管理员核对，公开 API 已屏蔽；前端表格可展示，但不要透传到用户端模块。
- **响应信封：** 管理接口沿用 SaiAdmin 现有 request 封装；公开接口才是 `code/message/data/request_id/timestamp`。
- **错误处理：** 分支判断用稳定英文 `code`，不要匹配中文文案。
- **仓库边界：** 本功能 PHP 与迁移只在 `turtle-soup-server`；Vue 页面与 API 类型只在 `turtle-soup-admin`。
- **Upstream：** 保持 SaiAdmin MIT 与既有权限/菜单设施，不要批量重写上游文件。

---

文档对应后端提交 `1297736` · 后管仓库 [moyuuuuuuuuuuu/turtle-soup-admin](https://github.com/moyuuuuuuuuuuu/turtle-soup-admin) · 请结合实际鉴权中间件与 request 封装微调示例代码。
