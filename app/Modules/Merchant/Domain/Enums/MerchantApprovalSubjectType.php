<?php

namespace App\Modules\Merchant\Domain\Enums;

enum MerchantApprovalSubjectType: string
{
    case Merchant = 'merchant';
    case MerchantIdentity = 'merchant_identity';
    case LegalEntity = 'legal_entity';
    case MerchantCategory = 'merchant_category';
    case MerchantOutlet = 'merchant_outlet';
    case MerchantDocument = 'merchant_document';
    case Service = 'service';
    case PayoutAccount = 'payout_account';

    /**
     * The review component this subject belongs to.
     */
    public function component(): MerchantApprovalComponent
    {
        return match ($this) {
            self::Merchant => MerchantApprovalComponent::Business,
            self::MerchantIdentity => MerchantApprovalComponent::Identity,
            self::LegalEntity => MerchantApprovalComponent::LegalEntity,
            self::MerchantCategory => MerchantApprovalComponent::Category,
            self::MerchantOutlet => MerchantApprovalComponent::Outlet,
            self::MerchantDocument => MerchantApprovalComponent::Document,
            self::Service => MerchantApprovalComponent::Service,
            self::PayoutAccount => MerchantApprovalComponent::Payout,
        };
    }

    public static function forComponent(MerchantApprovalComponent $component): self
    {
        return match ($component) {
            MerchantApprovalComponent::Business => self::Merchant,
            MerchantApprovalComponent::Identity => self::MerchantIdentity,
            MerchantApprovalComponent::LegalEntity => self::LegalEntity,
            MerchantApprovalComponent::Category => self::MerchantCategory,
            MerchantApprovalComponent::Outlet => self::MerchantOutlet,
            MerchantApprovalComponent::Document => self::MerchantDocument,
            MerchantApprovalComponent::Service => self::Service,
            MerchantApprovalComponent::Payout => self::PayoutAccount,
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
