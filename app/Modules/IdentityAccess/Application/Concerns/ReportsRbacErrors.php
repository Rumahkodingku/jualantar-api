<?php

namespace App\Modules\IdentityAccess\Application\Concerns;

use App\Shared\Result\Result;
use App\Shared\Result\ResultError;

trait ReportsRbacErrors
{
    /**
     * @param  list<string>  $names
     */
    private function invalidRole(array $names): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_role',
            message: 'The following roles do not exist: '.implode(', ', $names).'.',
            status: 422,
            title: 'Unprocessable Entity',
            fields: ['roles' => ['One or more roles are invalid.']],
        ));
    }

    /**
     * @param  list<string>  $names
     */
    private function invalidPermission(array $names): Result
    {
        return Result::err(new ResultError(
            code: 'invalid_permission',
            message: 'The following permissions do not exist: '.implode(', ', $names).'.',
            status: 422,
            title: 'Unprocessable Entity',
            fields: ['permissions' => ['One or more permissions are invalid.']],
        ));
    }

    private function protectedRole(string $name): Result
    {
        return Result::err(new ResultError(
            code: 'protected_role',
            message: "The role [{$name}] is protected and cannot be modified or deleted.",
            status: 409,
            title: 'Conflict',
        ));
    }

    private function roleInUse(string $name): Result
    {
        return Result::err(new ResultError(
            code: 'role_in_use',
            message: "The role [{$name}] is still assigned to one or more users.",
            status: 409,
            title: 'Conflict',
        ));
    }

    private function permissionInUse(string $name): Result
    {
        return Result::err(new ResultError(
            code: 'permission_in_use',
            message: "The permission [{$name}] is still assigned to one or more roles or users.",
            status: 409,
            title: 'Conflict',
        ));
    }

    private function roleAlreadyExists(string $name): Result
    {
        return Result::err(new ResultError(
            code: 'role_already_exists',
            message: "A role named [{$name}] already exists.",
            status: 409,
            title: 'Conflict',
        ));
    }

    private function permissionAlreadyExists(string $name): Result
    {
        return Result::err(new ResultError(
            code: 'permission_already_exists',
            message: "A permission named [{$name}] already exists.",
            status: 409,
            title: 'Conflict',
        ));
    }

    private function privilegeEscalation(): Result
    {
        return Result::err(new ResultError(
            code: 'privilege_escalation',
            message: 'You are not allowed to grant or revoke the super-admin role.',
            status: 403,
            title: 'Forbidden',
        ));
    }
}
