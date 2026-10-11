<script setup lang="ts">
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    describeLateChargesShare,
    lateChargesDestinationLabels,
    payoutModeDescriptions,
    payoutModeLabels,
    payoutScheduleLabels,
} from '@/lib/payout-labels';
import type { LateChargesDestination } from '@/lib/payout-labels';
import type {
    PayoutMode,
    PayoutRuleOverrides,
    PayoutRules,
    PayoutSchedule,
} from '@/types';

const props = defineProps<{
    /** The values saved so far. */
    values: PayoutRules | Partial<PayoutRuleOverrides> | null;
    /**
     * What applies when a field is left blank. When given, every field is optional and blank
     * follows it; without it, every field is required, as for the agency defaults.
     */
    inherited?: PayoutRules | null;
    errors: Partial<Record<string, string>>;
}>();

/** The value of a select that follows the level above. */
const INHERIT = 'inherit';

const isOptional = computed(() => !!props.inherited);

function initialText(value: string | number | null | undefined): string {
    return value === null || value === undefined ? '' : String(Number(value));
}

function initialChoice(value: string | boolean | null | undefined): string {
    if (value === null || value === undefined) {
        return isOptional.value ? INHERIT : '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    return value;
}

const adminFee = ref(initialText(props.values?.admin_fee_percent));
const intermediationFee = ref(
    initialText(props.values?.intermediation_fee_percent),
);
const renewalFee = ref(initialText(props.values?.renewal_fee_percent));
const adminFeeWithIntermediation = ref(
    initialChoice(props.values?.admin_fee_with_intermediation),
);
const extraChargesToOwner = ref(
    initialChoice(props.values?.extra_charges_to_owner),
);
const adminFeeOnExtraCharges = ref(
    initialChoice(props.values?.admin_fee_on_extra_charges),
);
const payoutMode = ref(initialChoice(props.values?.payout_mode));
const payoutSchedule = ref(initialChoice(props.values?.payout_schedule));
const payoutDays = ref(initialText(props.values?.payout_days));
const payoutDay = ref(initialText(props.values?.payout_day));

const savedLateShare = props.values?.late_charges_owner_percent;
const lateChargesDestination = ref<LateChargesDestination | typeof INHERIT>(
    savedLateShare === null || savedLateShare === undefined
        ? isOptional.value
            ? INHERIT
            : 'owner'
        : Number(savedLateShare) >= 100
          ? 'owner'
          : Number(savedLateShare) <= 0
            ? 'agency'
            : 'split',
);
const lateChargesOwnerShare = ref(
    savedLateShare !== null &&
        savedLateShare !== undefined &&
        Number(savedLateShare) > 0 &&
        Number(savedLateShare) < 100
        ? String(Number(savedLateShare))
        : '50',
);

const lateChargesOwnerPercent = computed(() => {
    switch (lateChargesDestination.value) {
        case 'owner':
            return '100';
        case 'agency':
            return '0';
        case 'split':
            return lateChargesOwnerShare.value;
        default:
            return '';
    }
});

/** The mode and schedule in effect, whether chosen here or followed from above. */
const effectiveMode = computed<PayoutMode>(() =>
    payoutMode.value === INHERIT || payoutMode.value === ''
        ? (props.inherited?.payout_mode ?? 'received')
        : (payoutMode.value as PayoutMode),
);
const effectiveSchedule = computed<PayoutSchedule>(() =>
    payoutSchedule.value === INHERIT || payoutSchedule.value === ''
        ? (props.inherited?.payout_schedule ?? 'days_after')
        : (payoutSchedule.value as PayoutSchedule),
);

/** Send the value of a select, blank when it follows the level above. */
function submitted(choice: string): string {
    return choice === INHERIT ? '' : choice;
}

function yesNo(value: boolean): string {
    return value ? 'Sim' : 'Não';
}

function inheritedPercent(value: string | undefined): string {
    return value === undefined ? '' : `Padrão: ${Number(value)}%`;
}

const percentFields = computed(() => [
    {
        name: 'admin_fee_percent',
        label: 'Taxa de administração',
        hint: 'Descontada de cada aluguel repassado.',
        model: adminFee,
        inherited: props.inherited?.admin_fee_percent,
    },
    {
        name: 'intermediation_fee_percent',
        label: 'Intermediação',
        hint: 'Parte do 1º aluguel. Use 0 para não cobrar.',
        model: intermediationFee,
        inherited: props.inherited?.intermediation_fee_percent,
    },
    {
        name: 'renewal_fee_percent',
        label: 'Renovação',
        hint: 'Parte do 1º aluguel após renovar. Use 0 para não cobrar.',
        model: renewalFee,
        inherited: props.inherited?.renewal_fee_percent,
    },
]);

const sectionTitleClass =
    'text-xs font-semibold tracking-wide text-muted-foreground uppercase';
</script>

<template>
    <div class="grid gap-6">
        <section class="grid gap-4">
            <h3 :class="sectionTitleClass">Taxas da imobiliária</h3>
            <div class="grid items-start gap-4 sm:grid-cols-3">
                <div
                    v-for="field in percentFields"
                    :key="field.name"
                    class="grid gap-2"
                >
                    <Label :for="field.name">{{ field.label }}</Label>
                    <div class="relative">
                        <Input
                            :id="field.name"
                            v-model="field.model.value"
                            :name="field.name"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            :placeholder="inheritedPercent(field.inherited)"
                            class="pr-8 tabular-nums"
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ field.hint }}
                    </p>
                    <InputError :message="errors[field.name]" />
                </div>
            </div>
            <div class="grid gap-2 sm:max-w-md">
                <Label for="admin_fee_with_intermediation">
                    Cobrar a administração no mês da intermediação ou renovação?
                </Label>
                <input
                    type="hidden"
                    name="admin_fee_with_intermediation"
                    :value="submitted(adminFeeWithIntermediation)"
                />
                <Select v-model="adminFeeWithIntermediation">
                    <SelectTrigger
                        id="admin_fee_with_intermediation"
                        class="w-full"
                    >
                        <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="inherited" :value="INHERIT">
                            Padrão ({{
                                yesNo(inherited.admin_fee_with_intermediation)
                            }})
                        </SelectItem>
                        <SelectItem value="0">
                            Não, só a intermediação ou renovação
                        </SelectItem>
                        <SelectItem value="1">Sim, as duas</SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.admin_fee_with_intermediation" />
            </div>
        </section>

        <section class="grid gap-4">
            <h3 :class="sectionTitleClass">Cobranças extras do inquilino</h3>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="extra_charges_to_owner">
                        Repassar ao proprietário?
                    </Label>
                    <input
                        type="hidden"
                        name="extra_charges_to_owner"
                        :value="submitted(extraChargesToOwner)"
                    />
                    <Select v-model="extraChargesToOwner">
                        <SelectTrigger
                            id="extra_charges_to_owner"
                            class="w-full"
                        >
                            <SelectValue placeholder="Selecione" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="inherited" :value="INHERIT">
                                Padrão ({{
                                    yesNo(inherited.extra_charges_to_owner)
                                }})
                            </SelectItem>
                            <SelectItem value="1">Sim</SelectItem>
                            <SelectItem value="0">
                                Não, ficam com a imobiliária
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Reparos, multas contratuais e outras cobranças avulsas.
                        Pode ser mudado em cada cobrança.
                    </p>
                    <InputError :message="errors.extra_charges_to_owner" />
                </div>
                <div class="grid gap-2">
                    <Label for="admin_fee_on_extra_charges">
                        Cobrar administração sobre elas?
                    </Label>
                    <input
                        type="hidden"
                        name="admin_fee_on_extra_charges"
                        :value="submitted(adminFeeOnExtraCharges)"
                    />
                    <Select v-model="adminFeeOnExtraCharges">
                        <SelectTrigger
                            id="admin_fee_on_extra_charges"
                            class="w-full"
                        >
                            <SelectValue placeholder="Selecione" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="inherited" :value="INHERIT">
                                Padrão ({{
                                    yesNo(inherited.admin_fee_on_extra_charges)
                                }})
                            </SelectItem>
                            <SelectItem value="0">Não</SelectItem>
                            <SelectItem value="1">Sim</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.admin_fee_on_extra_charges" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <h3 :class="sectionTitleClass">Multa e juros por atraso</h3>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="late_charges_destination">Ficam com</Label>
                    <input
                        type="hidden"
                        name="late_charges_owner_percent"
                        :value="lateChargesOwnerPercent"
                    />
                    <Select v-model="lateChargesDestination">
                        <SelectTrigger
                            id="late_charges_destination"
                            class="w-full"
                        >
                            <SelectValue placeholder="Selecione" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="inherited" :value="INHERIT">
                                Padrão ({{
                                    describeLateChargesShare(
                                        Number(
                                            inherited.late_charges_owner_percent,
                                        ),
                                    )
                                }})
                            </SelectItem>
                            <SelectItem
                                v-for="(
                                    label, key
                                ) in lateChargesDestinationLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Pode ser mudado em cada pagamento recebido.
                    </p>
                </div>
                <div
                    v-if="lateChargesDestination === 'split'"
                    class="grid gap-2"
                >
                    <Label for="late_charges_owner_share">
                        Parte do proprietário
                    </Label>
                    <div class="relative">
                        <Input
                            id="late_charges_owner_share"
                            v-model="lateChargesOwnerShare"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="pr-8 tabular-nums"
                        />
                        <span
                            class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >%</span
                        >
                    </div>
                    <p class="text-xs text-muted-foreground">
                        O resto fica com a imobiliária.
                    </p>
                </div>
            </div>
            <InputError :message="errors.late_charges_owner_percent" />
        </section>

        <section class="grid gap-4">
            <h3 :class="sectionTitleClass">Repasse</h3>
            <div class="grid gap-2">
                <Label for="payout_mode">Modalidade</Label>
                <input
                    type="hidden"
                    name="payout_mode"
                    :value="submitted(payoutMode)"
                />
                <Select v-model="payoutMode">
                    <SelectTrigger id="payout_mode" class="w-full">
                        <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-if="inherited" :value="INHERIT">
                            Padrão ({{
                                payoutModeLabels[inherited.payout_mode]
                            }})
                        </SelectItem>
                        <SelectItem
                            v-for="(label, key) in payoutModeLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="text-xs text-muted-foreground">
                    {{ payoutModeDescriptions[effectiveMode] }}
                </p>
                <InputError :message="errors.payout_mode" />
            </div>
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="payout_schedule">Prazo</Label>
                    <input
                        type="hidden"
                        name="payout_schedule"
                        :value="submitted(payoutSchedule)"
                    />
                    <Select v-model="payoutSchedule">
                        <SelectTrigger id="payout_schedule" class="w-full">
                            <SelectValue placeholder="Selecione" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="inherited" :value="INHERIT">
                                Padrão ({{
                                    payoutScheduleLabels[
                                        inherited.payout_schedule
                                    ]
                                }})
                            </SelectItem>
                            <SelectItem
                                v-for="(label, key) in payoutScheduleLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.payout_schedule" />
                </div>
                <div
                    v-show="effectiveSchedule === 'days_after'"
                    class="grid gap-2"
                >
                    <Label for="payout_days">
                        Dias depois do
                        {{
                            effectiveMode === 'guaranteed'
                                ? 'vencimento'
                                : 'recebimento'
                        }}
                    </Label>
                    <Input
                        id="payout_days"
                        v-model="payoutDays"
                        name="payout_days"
                        type="number"
                        min="0"
                        max="90"
                        :placeholder="
                            inherited ? `Padrão: ${inherited.payout_days}` : ''
                        "
                        class="tabular-nums"
                    />
                    <InputError :message="errors.payout_days" />
                </div>
                <div
                    v-show="effectiveSchedule === 'monthly_day'"
                    class="grid gap-2"
                >
                    <Label for="payout_day">Dia do mês</Label>
                    <Input
                        id="payout_day"
                        v-model="payoutDay"
                        name="payout_day"
                        type="number"
                        min="1"
                        max="31"
                        :placeholder="
                            inherited
                                ? `Padrão: dia ${inherited.payout_day}`
                                : ''
                        "
                        class="tabular-nums"
                    />
                    <InputError :message="errors.payout_day" />
                </div>
            </div>
        </section>
    </div>
</template>
