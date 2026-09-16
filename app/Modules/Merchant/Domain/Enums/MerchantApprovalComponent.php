<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApprovalComponent: string
{
    case Business = 'business';
    case Identity = 'identity';
    case LegalEntity = 'legal_entity';
    case Service = 'service';
    case Category = 'category';
    case Outlet = 'outlet';
    case Document = 'document';
    case Payout = 'payout';

    /**
     * The subject type that backs this component.
     */
    public function subjectType(): MerchantApprovalSubjectType
    {
        return MerchantApprovalSubjectType::forComponent($this);
    }

    /**
     * Whether this component must be verified before approval for the given
     * merchant type. `legal_entity` only applies to companies.
     */
    public function isRequiredFor(MerchantType $type): bool
    {
        return match ($this) {
            self::LegalEntity => $type === MerchantType::Company,
            default => true,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
