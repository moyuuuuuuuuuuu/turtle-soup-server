<?php

declare(strict_types=1);

namespace App\FriendLink\Business;

use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use App\FriendLink\Formats\FriendLinkFormat;
use App\FriendLink\Models\FriendLink;
use App\FriendLink\Repositories\FriendLinkRepository;

final class FriendLinkBusiness
{
    public function __construct(private readonly FriendLinkRepository $repository = new FriendLinkRepository())
    {
    }

    /** @return array{items: array<int, array<string, mixed>>} */
    public function publicList(): array
    {
        return [
            'items' => FriendLinkFormat::publicList($this->repository->enabledLinks()),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    public function page(array $filters, int $page, int $size): array
    {
        return [
            'items' => FriendLinkFormat::adminItems($this->repository->page($filters, $page, $size)),
            'total' => $this->repository->count($filters),
            'page' => $page,
            'pageSize' => $size,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $data = $this->validatedLink($input);
        $data['public_id'] = PublicId::make();

        return FriendLinkFormat::adminItem($this->repository->create($data));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $id, array $input): array
    {
        $link = $this->repository->find($id);
        if (!$link instanceof FriendLink) {
            ErrorCode::FRIEND_LINK_NOT_FOUND->throw();
        }
        $link->update($this->validatedLink($input));

        return FriendLinkFormat::adminItem($link->fresh() ?? $link);
    }

    /** @param array<int, int|string> $ids */
    public function destroy(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $this->repository->deleteByIds($ids);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validatedLink(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $url = trim((string) ($input['url'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            ErrorCode::PARAM_ERROR->throw('站点名称必填且不超过 80 字');
        }
        $this->assertHttpUrl($url, '站点地址');
        $logoUrl = trim((string) ($input['logo_url'] ?? ''));
        if ($logoUrl !== '') {
            $this->assertHttpUrl($logoUrl, 'Logo 地址');
        }
        $reciprocalUrl = trim((string) ($input['reciprocal_url'] ?? ''));
        if ($reciprocalUrl !== '') {
            $this->assertHttpUrl($reciprocalUrl, '回链地址');
        }
        $contactEmail = trim((string) ($input['contact_email'] ?? ''));
        if ($contactEmail !== '') {
            if (mb_strlen($contactEmail) > 255 || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
                ErrorCode::PARAM_ERROR->throw('联系邮箱格式不正确');
            }
        }
        $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 255);

        return [
            'name' => $name,
            'url' => $url,
            'logo_url' => $logoUrl !== '' ? $logoUrl : null,
            'description' => $description !== '' ? $description : null,
            'reciprocal_url' => $reciprocalUrl !== '' ? $reciprocalUrl : null,
            'contact_email' => $contactEmail !== '' ? strtolower($contactEmail) : null,
            'status' => filter_var($input['status'] ?? true, FILTER_VALIDATE_BOOL),
            'sort' => (int) ($input['sort'] ?? 0),
        ];
    }

    private function assertHttpUrl(string $url, string $field): void
    {
        if ($url === '' || mb_strlen($url) > 500) {
            ErrorCode::FRIEND_LINK_URL_INVALID->throw($field . '无效');
        }
        $parts = parse_url($url);
        if ($parts === false
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            ErrorCode::FRIEND_LINK_URL_INVALID->throw($field . '必须是 http/https 地址');
        }
    }
}
