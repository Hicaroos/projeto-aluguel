<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { HandCoins } from '@lucide/vue';
import { ref } from 'vue';
import PayoutController from '@/actions/App/Http/Controllers/PayoutController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { formatCurrency } from '@/lib/currency';
import { todayIsoDate } from '@/lib/formatters';
import { manualPaymentMethodLabels } from '@/lib/payment-labels';
import type { StatementOwner } from '@/types';

const props = defineProps<{
    open: boolean;
    owner: StatementOwner;
    /** What may be paid out today. */
    available: number;
}>();

const emit = defineEmits<{
    close: [];
}>();

const paymentMethod = ref('pix');

const amount = ref(props.available > 0 ? props.available.toFixed(2) : '');
</script>

<template>
    <Dialog :open="open" @update:open="(isOpen) => !isOpen && emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <HandCoins class="size-5" />
                </div>
                <div class="min-w-0 space-y-1">
                    <DialogTitle>Registrar repasse</DialogTitle>
                    <DialogDescription class="truncate">
                        {{ owner.name }} · disponível
                        {{ formatCurrency(available) }}
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Form
                v-bind="PayoutController.store.form(owner.id)"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
                :options="{ preserveScroll: true }"
                @success="emit('close')"
            >
                <input
                    type="hidden"
                    name="payment_method"
                    :value="paymentMethod"
                />

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="payout_amount">Valor</Label>
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                                >R$</span
                            >
                            <Input
                                id="payout_amount"
                                v-model="amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="available"
                                class="pl-10 tabular-nums"
                            />
                        </div>
                        <InputError :message="errors.amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="paid_on">Data</Label>
                        <Input
                            id="paid_on"
                            name="paid_on"
                            type="date"
                            :max="todayIsoDate()"
                            :default-value="todayIsoDate()"
                        />
                        <InputError :message="errors.paid_on" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="payout_method">Forma de pagamento</Label>
                    <Select v-model="paymentMethod">
                        <SelectTrigger id="payout_method" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(
                                    label, key
                                ) in manualPaymentMethodLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p
                        v-if="paymentMethod === 'pix' && owner.pix_key"
                        class="text-xs text-muted-foreground"
                    >
                        Chave Pix do proprietário: {{ owner.pix_key }}
                    </p>
                    <InputError :message="errors.payment_method" />
                </div>

                <div class="grid gap-2">
                    <Label for="payout_notes">
                        Observação
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="payout_notes"
                        name="notes"
                        placeholder="Ex.: Pix agendado pelo app do banco"
                    />
                    <InputError :message="errors.notes" />
                </div>

                <div class="grid gap-2">
                    <Label for="payout_receipt">
                        Comprovante
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="payout_receipt"
                        name="receipt"
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                    />
                    <p class="text-xs text-muted-foreground">
                        PDF ou imagem de até 10 MB. Dá para anexar depois, no
                        extrato.
                    </p>
                    <InputError :message="errors.receipt" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button type="submit" :disabled="processing">
                        <Spinner v-if="processing" />
                        Registrar repasse
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
