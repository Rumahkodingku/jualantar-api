<?php

namespace App\Modules\Merchant\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The operating-hours schedule keyed by day name.
 *
 * @mixin array<string, mixed>
 */
class OperatingHoursResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return is_array($this->resource) ? $this->resource : [];
    }
}
