<?php

namespace App\Services;

use App\Enums\UserQueryStatus;
use App\Models\UserQuery;

class UserQueryService
{
    public function submitQuery(array $data): UserQuery
    {
        return UserQuery::create([
            'query_number' => $this->generateQueryNumber(),
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'dial_code' => $data['dial_code'] ?? null,
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => UserQueryStatus::Pending->value,
        ]);
    }

    public function updateStatus(UserQuery $query, string $status): void
    {
        $query->update(['status' => $status]);
    }

    public function deleteQuery(UserQuery $query): void
    {
        $query->delete();
    }

    private function generateQueryNumber(): string
    {
        $last = UserQuery::query()
            ->withTrashed()
            ->orderByDesc('id')
            ->value('query_number');

        $next = $last ? ((int) $last) + 1 : 1;

        return str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
