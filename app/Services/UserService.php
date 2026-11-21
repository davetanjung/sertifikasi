<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\User;
use App\Utilities\Constants\Const_Role;
use App\Utilities\Constants\Const_Status;

final class UserService
{
    /**
     * Register a tenant. Role and status are forced, whatever the input says.
     *
     * @param  array<string, mixed>  $data
     */
    public static function register(array $data = []): User
    {
        $user = new User;
        $user->fill([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'nik' => $data['nik'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => Const_Role::TENANT,
            'registration_status' => Const_Status::PENDING,
        ]);
        $user->save();

        return $user;
    }

    public static function approve(User $user): User
    {
        return self::changeStatus($user, Const_Status::APPROVED);
    }

    public static function reject(User $user): User
    {
        return self::changeStatus($user, Const_Status::REJECTED);
    }

    /**
     * Move a pending tenant registration to approved or rejected.
     *
     * @throws GeneralException
     */
    public static function changeStatus(User $user, string $status): User
    {
        if (! in_array($status, [Const_Status::APPROVED, Const_Status::REJECTED], true)) {
            throw new GeneralException('Status must be approved or rejected.');
        }

        $user->registration_status = $status;
        $user->save();

        return $user->fresh();
    }
}
