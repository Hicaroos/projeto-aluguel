<?php

namespace App\Enums;

use Illuminate\Validation\Rule;

/**
 * The rules that decide what an owner is paid out of what a lease brings in. The agency sets the
 * default of each one, and may set it otherwise for an owner and again for a lease; a blank value
 * at the owner or lease means "same as above".
 */
enum PayoutRule: string
{
    /** The share of the rent the agency keeps every month. */
    case AdminFeePercent = 'admin_fee_percent';

    /** The share of the first rent the agency keeps for finding the tenant; zero charges nothing. */
    case IntermediationFeePercent = 'intermediation_fee_percent';

    /** The share of the first rent after a renewal the agency keeps; zero charges nothing. */
    case RenewalFeePercent = 'renewal_fee_percent';

    /** Whether the monthly admin fee is also charged in the month of the intermediation or renewal fee. */
    case AdminFeeWithIntermediation = 'admin_fee_with_intermediation';

    /** Whether the admin fee is charged on the extra charges passed on to the owner. */
    case AdminFeeOnExtraCharges = 'admin_fee_on_extra_charges';

    /** Whether extra charges paid by the tenant (repairs, fines) go to the owner by default. */
    case ExtraChargesToOwner = 'extra_charges_to_owner';

    /** The share of the late fee and interest that goes to the owner; the agency keeps the rest. */
    case LateChargesOwnerPercent = 'late_charges_owner_percent';

    /** Whether the owner gets only what was received, or the rent guaranteed by the agency. */
    case PayoutMode = 'payout_mode';

    /** Whether the owner is paid some days later or on a fixed day of the month. */
    case PayoutSchedule = 'payout_schedule';

    /** The days after the rent is received (or due, when guaranteed) until the owner is paid. */
    case PayoutDays = 'payout_days';

    /** The day of the month the owner is paid. */
    case PayoutDay = 'payout_day';

    /**
     * Get the cast of the rule's column.
     */
    public function cast(): string
    {
        return match ($this) {
            self::AdminFeePercent, self::IntermediationFeePercent, self::RenewalFeePercent, self::LateChargesOwnerPercent => 'decimal:2',
            self::AdminFeeWithIntermediation, self::AdminFeeOnExtraCharges, self::ExtraChargesToOwner => 'boolean',
            self::PayoutMode => PayoutMode::class,
            self::PayoutSchedule => PayoutSchedule::class,
            self::PayoutDays, self::PayoutDay => 'integer',
        };
    }

    /**
     * Get the validation rules of the rule's value, whether it is required or not.
     *
     * @return array<int, mixed>
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::AdminFeePercent, self::IntermediationFeePercent, self::RenewalFeePercent, self::LateChargesOwnerPercent => ['numeric', 'between:0,100'],
            self::AdminFeeWithIntermediation, self::AdminFeeOnExtraCharges, self::ExtraChargesToOwner => ['boolean'],
            self::PayoutMode => [Rule::enum(PayoutMode::class)],
            self::PayoutSchedule => [Rule::enum(PayoutSchedule::class)],
            self::PayoutDays => ['integer', 'between:0,90'],
            self::PayoutDay => ['integer', 'between:1,31'],
        };
    }

    /**
     * Get the name of the rule shown in validation messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::AdminFeePercent => __('taxa de administração'),
            self::IntermediationFeePercent => __('taxa de intermediação'),
            self::RenewalFeePercent => __('taxa de renovação'),
            self::AdminFeeWithIntermediation => __('administração no mês da intermediação'),
            self::AdminFeeOnExtraCharges => __('administração sobre cobranças extras'),
            self::ExtraChargesToOwner => __('repasse das cobranças extras'),
            self::LateChargesOwnerPercent => __('parte da multa e dos juros do proprietário'),
            self::PayoutMode => __('modalidade de repasse'),
            self::PayoutSchedule => __('prazo do repasse'),
            self::PayoutDays => __('dias para o repasse'),
            self::PayoutDay => __('dia do repasse'),
        };
    }

    /**
     * Get the columns of every rule.
     *
     * @return list<string>
     */
    public static function columns(): array
    {
        return array_map(fn (self $rule): string => $rule->value, self::cases());
    }

    /**
     * Get the casts of every rule's column.
     *
     * @return array<string, string>
     */
    public static function casts(): array
    {
        return array_combine(self::columns(), array_map(fn (self $rule): string => $rule->cast(), self::cases()));
    }

    /**
     * Get the validation rules of every rule: required for the agency defaults, or optional where
     * a blank value falls back to the default.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(bool $required): array
    {
        return array_combine(
            self::columns(),
            array_map(fn (self $rule): array => [$required ? 'required' : 'nullable', ...$rule->validationRules()], self::cases()),
        );
    }

    /**
     * Get the names of every rule shown in validation messages.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_combine(self::columns(), array_map(fn (self $rule): string => $rule->label(), self::cases()));
    }
}
