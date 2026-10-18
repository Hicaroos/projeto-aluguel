<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LeadController from '@/actions/App/Http/Controllers/LeadController';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
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
import { propertyTypeLabels } from '@/lib/property-labels';
import {
    leadInterestLabels,
    leadSourceLabels,
    paymentPlanLabels,
} from '@/lib/sales-labels';
import type { Lead, LeadInterest, TeamMemberOption } from '@/types';

const props = defineProps<{
    lead?: Lead | null;
    users: TeamMemberOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const NONE = 'none';

const formAction = computed(() =>
    props.lead
        ? LeadController.update.form(props.lead)
        : LeadController.store.form(),
);

const interest = ref<LeadInterest>(props.lead?.interest ?? 'buy');
const source = ref<string>(props.lead?.source ?? NONE);
const propertyType = ref<string>(props.lead?.property_type ?? NONE);
const paymentPlan = ref<string>(props.lead?.payment_plan ?? NONE);
const userId = ref<string>(
    props.lead?.user_id ? String(props.lead.user_id) : NONE,
);

/** The value posted for an optional select: empty when nothing was chosen. */
const optional = (value: string): string => (value === NONE ? '' : value);

const textareaClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-8"
        @success="emit('success')"
    >
        <input type="hidden" name="interest" :value="interest" />
        <input type="hidden" name="source" :value="optional(source)" />
        <input
            type="hidden"
            name="property_type"
            :value="optional(propertyType)"
        />
        <input
            type="hidden"
            name="payment_plan"
            :value="optional(paymentPlan)"
        />
        <input type="hidden" name="user_id" :value="optional(userId)" />

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Contato
            </h3>

            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    placeholder="Ex.: Carlos Souza"
                    :default-value="lead?.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="phone">Celular</Label>
                    <MaskedInput
                        id="phone"
                        mask="phone"
                        name="phone"
                        type="tel"
                        placeholder="(00) 00000-0000"
                        :default-value="lead?.phone"
                    />
                    <InputError :message="errors.phone" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">
                        E-mail
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="email"
                        name="email"
                        type="email"
                        placeholder="nome@exemplo.com"
                        :default-value="lead?.email ?? ''"
                    />
                    <InputError :message="errors.email" />
                </div>
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="source">Como chegou</Label>
                    <Select v-model="source">
                        <SelectTrigger id="source" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Não informado</SelectItem>
                            <SelectItem
                                v-for="(label, key) in leadSourceLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.source" />
                </div>

                <div v-if="users.length > 0" class="grid gap-2">
                    <Label for="user_id">Responsável</Label>
                    <Select v-model="userId">
                        <SelectTrigger id="user_id" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">
                                {{ lead ? 'Ninguém' : 'Eu mesmo' }}
                            </SelectItem>
                            <SelectItem
                                v-for="user in users"
                                :key="user.id"
                                :value="String(user.id)"
                            >
                                {{ user.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.user_id" />
                </div>
            </div>
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                O que procura
            </h3>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="interest">Quer</Label>
                    <Select v-model="interest">
                        <SelectTrigger id="interest" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(label, key) in leadInterestLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.interest" />
                </div>

                <div class="grid gap-2">
                    <Label for="property_type">Tipo de imóvel</Label>
                    <Select v-model="propertyType">
                        <SelectTrigger id="property_type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">Qualquer</SelectItem>
                            <SelectItem
                                v-for="(label, key) in propertyTypeLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.property_type" />
                </div>

                <div class="grid gap-2">
                    <Label for="max_price">
                        {{
                            {
                                buy: 'Pode pagar até',
                                rent: 'Aluguel de até',
                                list: 'Valor pretendido',
                            }[interest]
                        }}
                    </Label>
                    <div class="relative">
                        <span
                            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-sm text-muted-foreground"
                            >R$</span
                        >
                        <Input
                            id="max_price"
                            name="max_price"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="0,00"
                            class="pl-10 tabular-nums"
                            :default-value="lead?.max_price ?? ''"
                        />
                    </div>
                    <InputError :message="errors.max_price" />
                </div>

                <div v-if="interest === 'buy'" class="grid gap-2">
                    <Label for="payment_plan">Forma de pagamento</Label>
                    <Select v-model="paymentPlan">
                        <SelectTrigger id="payment_plan" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE"
                                >Não sabe ainda</SelectItem
                            >
                            <SelectItem
                                v-for="(label, key) in paymentPlanLabels"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.payment_plan" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="neighborhoods">Bairros de interesse</Label>
                <Input
                    id="neighborhoods"
                    name="neighborhoods"
                    placeholder="Ex.: Centro, São Sebastião"
                    :default-value="lead?.neighborhoods ?? ''"
                />
                <InputError :message="errors.neighborhoods" />
            </div>

            <div class="grid gap-2">
                <Label for="notes">Anotações</Label>
                <textarea
                    id="notes"
                    name="notes"
                    rows="3"
                    placeholder="Ex.: precisa de 3 quartos, tem cachorro, quer mudar em janeiro…"
                    :class="textareaClass"
                    :value="lead?.notes ?? ''"
                />
                <InputError :message="errors.notes" />
            </div>
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ lead ? 'Salvar alterações' : 'Cadastrar interessado' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
