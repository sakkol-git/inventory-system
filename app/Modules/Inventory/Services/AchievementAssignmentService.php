<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Services;

use App\Modules\Core\Models\User;
use App\Modules\Core\Contracts\ICrudService;
use App\Modules\Inventory\Models\Achievement;
use Illuminate\Support\Facades\DB;

class AchievementAssignmentService
{
    public function __construct(
        private readonly ICrudService $crudService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createAchievement(array $data, User $actor): Achievement
    {
        return DB::transaction(function () use ($data, $actor): Achievement {
            $userIds = $this->extractUserIds($data);

            /** @var Achievement $achievement */
            $achievement = $this->crudService->create(
                modelClass: Achievement::class,
                data: $data,
                user: $actor,
            );

            $this->syncAssignedUsers($achievement, $userIds);

            return $achievement->load('users');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAchievement(Achievement $achievement, array $data, User $actor): Achievement
    {
        return DB::transaction(function () use ($achievement, $data, $actor): Achievement {
            $userIds = $this->extractUserIds($data, false);

            /** @var Achievement $updatedAchievement */
            $updatedAchievement = $this->crudService->update(
                instance: $achievement,
                data: $data,
                user: $actor,
            );

            if ($userIds !== null) {
                $this->syncAssignedUsers($updatedAchievement, $userIds);
            }

            return $updatedAchievement->load('users');
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return list<int>|null
     */
    private function extractUserIds(array &$data, bool $defaultToEmpty = true): ?array
    {
        if (!array_key_exists('user_ids', $data)) {
            return $defaultToEmpty ? [] : null;
        }

        $userIds = array_map('intval', (array) $data['user_ids']);
        unset($data['user_ids']);

        return $userIds;
    }

    /**
     * @param list<int> $userIds
     */
    private function syncAssignedUsers(Achievement $achievement, array $userIds): void
    {
        $existingEarnedAt = $achievement->users()
            ->pluck('user_achievements.earned_at', 'users.id')
            ->all();

        $now = now();
        $syncData = [];

        foreach ($userIds as $userId) {
            $syncData[$userId] = [
                'earned_at' => $existingEarnedAt[$userId] ?? $now,
            ];
        }

        $achievement->users()->sync($syncData);
    }
}
