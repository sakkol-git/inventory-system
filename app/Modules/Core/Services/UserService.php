<?php

declare(strict_types=1);

namespace App\Modules\Core\Services;


use App\Modules\Core\Concerns\EscapesSearchTerm;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Modules\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserService
{
    use EscapesSearchTerm;
    /**
     * Create a new user and assign the matching Spatie role.
     * The `role` field in $data determines which Spatie role to assign.
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data){
           $rolename = $data['role'] ?? 'student';
            $user = User::create($data);

            // Assign Spatie role based on the `role` field
            // Create the Spatie role if it doesn't exist
            $spatieRole = Role::firstOrCreate([
                'name' => $rolename,
                'guard_name' => 'api',
            ]);
            $user->assignRole($spatieRole);
            return $user;
        });
    }

    /**
     * Update a user and re-sync Spatie role if the role changed.
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->update($data);

            if (isset($data['role'])) {
                $rolename = $data['role'];
                // Create the Spatie role if it doesn't exist
                $spatieRole = Role::firstOrCreate([
                    'name' => $rolename,
                    'guard_name' => 'api',
                ]);
                // Sync the user's roles to only have the new role
                $user->syncRoles($spatieRole);
            }

            return $user;
        });
    }

    /**
     * Delete a user.
     */
    public function delete(User $user): void
    {
        DB::transaction(fn () => $user->delete());
    }

     /**
     * Search and Filter Users.
     */

     public function getPaginatedUsers(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = User::query()->latest();

        // 1. Apply Search Filter
        if (!empty($filters['search'])) {
            $term = $this->escapeLike($filters['search']);

            // Note the nested "where" closure to preserve SQL AND/OR logic
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        // 2. Apply Role Filter
        if (!empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->paginate($perPage);
    }
}
