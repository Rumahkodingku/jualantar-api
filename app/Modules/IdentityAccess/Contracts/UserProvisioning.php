<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Contracts\DataTransferObjects\UserData;

interface UserProvisioning
{
    /**
     * Provision a new identity user for the customer role.
     *
     * The returned DTO is the only representation other modules may hold;
     * the Eloquent model and the RBAC assignment stay inside IdentityAccess.
     *
     * @param  array{email: string, phone?: string|null, password: string}  $attributes
     */
    public function provisionCustomer(array $attributes): UserData;

    /**
     * Provision a new identity user for the merchant role.
     *
     * The returned DTO is the only representation other modules may hold;
     * the Eloquent model and the RBAC assignment stay inside IdentityAccess.
     *
     * @param  array{email: string, phone?: string|null, password: string}  $attributes
     */
    public function provisionMerchant(array $attributes): UserData;
}
