<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PaymentController from '@/actions/App/Http/Controllers/PaymentController';
import InputError from '@/components/InputError.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import type { SearchableSelectOption } from '@/components/SearchableSelect.vue';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { todayIsoDate } from '@/lib/formatters';
import { leaseStatusLabels } from '@/lib/lease-labels';
import type { PaymentLeaseOption } from '@/types';

const props = defineProps<{
    leases: PaymentLeaseOption[];
    initialLeaseId?: number | null;
}>();

const emit = defineEmits<{
    success: [];
}>();

const leaseId = ref(props.initialLeaseId ? String(props.initialLeaseId) : '');

const page = usePage();

const isAgency = computed(
    () => page.props.auth.user.account?.type === 'agency',
);

/** Whether the charge goes to the owner when paid; "rules" follows the payout rules. */
const passToOwner = ref<'rules' | '1' | '0'>('rules');

const leaseOptions = computed<SearchableSelectOption[]>(() =>
    props.leases.map((lease) => ({
        value: String(lease.id),
        label: lease.tenant.name,
        description: `${lease.property.street}, ${lease.property.number} · ${lease.property.neighborhood}${
            lease.status === 'active'
                ? ''
                : ` · Contrato ${leaseStatusLabels[lease.status].toLowerCase()}`
        }`,
    })),
);
</script>

<template>
    <Form
        v-bind="PaymentController.store.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        :options="{ preserveScroll: true }"
        @success="emit('success')"
    >
        <div class="grid gap-2">
            <Label for="lease_id">Inquilino / contrato</Label>
            <SearchableSelect
                id="lease_id"
                v-model="leaseId"
                name="lease_id"
                :options="leaseOptions"
                placeholder="Digite o nome do inquilino…"
                empty-text="Nenhum contrato encontrado."
            />
            <p class="text-xs text-muted-foreground">
                Contratos encerrados também aparecem, para cobrar pendências de
                ex-inquilinos.
            </p>
            <InputError :message="errors.lease_id" />
        </div>

        <div class="grid gap-2">
            <Label for="charge_description">Descrição</Label>
            <Input
                id="charge_description"
                name="description"
                placeholder="Ex.: Reparo da pintura, multa rescisória, conta de água…"
            />
            <InputError :message="errors.description" />
        </div>

        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="charge_amount">Valor</Label>
                <div class="relative">
                    <span
                        class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                        >R$</span
                    >
                    <Input
                        id="charge_amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                        placeholder="0,00"
                        class="pl-10 tabular-nums"
                    />
                </div>
                <InputError :message="errors.amount" />
            </div>

            <div class="grid gap-2">
                <Label for="charge_due_date">Vencimento</Label>
                <Input
                    id="charge_due_date"
                    name="due_date"
                    type="date"
                    :default-value="todayIsoDate()"
                />
                <InputError :message="errors.due_date" />
            </div>
        </div>

        <div v-if="isAgency" class="grid gap-2">
            <Label for="charge_pass_to_owner">Repassar ao proprietário?</Label>
            <input
                type="hidden"
                name="pass_to_owner"
                :value="passToOwner === 'rules' ? '' : passToOwner"
            />
            <Select v-model="passToOwner">
                <SelectTrigger id="charge_pass_to_owner" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="rules">Conforme o contrato</SelectItem>
                    <SelectItem value="1"
                        >Sim, vai para o proprietário</SelectItem
                    >
                    <SelectItem value="0"
                        >Não, fica com a imobiliária</SelectItem
                    >
                </SelectContent>
            </Select>
            <InputError :message="errors.pass_to_owner" />
        </div>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                Cadastrar cobrança
            </Button>
        </DialogFooter>
    </Form>
</template>
