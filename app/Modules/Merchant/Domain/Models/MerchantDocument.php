<?php

namespace App\Modules\Merchant\Domain\Models;

use App\Modules\Merchant\Database\Factories\MerchantDocumentFactory;
use App\Modules\Merchant\Domain\Enums\MerchantDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('merchant.merchant_documents')]
#[UseFactory(MerchantDocumentFactory::class)]
#[Fillable(['merchant_id', 'document_type', 'file_name', 'object_key', 'mime_type', 'file_size'])]
class MerchantDocument extends Model
{
    /** @use HasFactory<MerchantDocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => MerchantDocumentType::class,
            'file_size' => 'integer',
        ];
    }
}
