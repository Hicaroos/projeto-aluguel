<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import OwnerController from '@/actions/App/Http/Controllers/OwnerController';
import AddressFields from '@/components/AddressFields.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import PayoutRulesFields from '@/components/PayoutRulesFields.vue';
import PersonQualificationFields from '@/components/PersonQualificationFields.vue';
import { Button } from '@/components/ui/button';
import { DialogClose, DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { payoutRuleColumns } from '@/lib/payout-labels';
import { link } from '@/routes/owners';
import type { Owner, PayoutRules } from '@/types';

const props = defineProps<{
    owner?: Owner | null;
    /** The agency payout defaults, for members who handle the money; null hides the payout terms. */
    payoutDefaults?: PayoutRules | null;
}>();

/** Show the payout terms when the owner already has terms of their own. */
const showPayoutRules = ref(
    !!props.owner &&
        payoutRuleColumns.some(
            (column) =>
                props.owner?.[column] !== null &&
                props.owner?.[column] !== undefined,
        ),
);

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.owner
        ? OwnerController.update.form(props.owner)
        : OwnerController.store.form(),
);

const isLinking = ref(false);

/**
 * Bring the owner with the typed document, registered by another branch, to the user's branch.
 */
function useExistingOwner() {
    const document = (
        window.document.getElementById('cpf_cnpj') as HTMLInputElement | null
    )?.value;

    isLinking.value = true;

    router.post(
        link().url,
        { cpf_cnpj: document ?? '' },
        {
            onSuccess: () => emit('success'),
            onFinish: () => (isLinking.value = false),
        },
    );
}
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-8"
        @success="emit('success')"
    >
        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Dados pessoais
            </h3>

            <div class="grid gap-2">
                <Label for="name">Nome completo ou razão social</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    placeholder="Ex.: Maria da Silva"
                    :default-value="owner?.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid items-start gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="cpf_cnpj">CPF/CNPJ</Label>
                    <MaskedInput
                        id="cpf_cnpj"
                        mask="document"
                        name="cpf_cnpj"
                        placeholder="000.000.000-00"
                        :default-value="owner?.cpf_cnpj"
                    />
                    <InputError :message="errors.cpf_cnpj" />
                    <Button
                        v-if="errors.registered_elsewhere"
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-fit"
                        :disabled="isLinking"
                        @click="useExistingOwner"
                    >
                        <Spinner v-if="isLinking" />
                        <UserPlus v-else class="size-4" />
                        Usar cadastro existente
                    </Button>
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Celular</Label>
                    <MaskedInput
                        id="phone"
                        mask="phone"
                        name="phone"
                        type="tel"
                        placeholder="(00) 00000-0000"
                        :default-value="owner?.phone"
                    />
                    <InputError :message="errors.phone" />
                </div>
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
                    :default-value="owner?.email ?? ''"
                />
                <InputError :message="errors.email" />
            </div>
        </section>

        <section class="grid gap-4">
            <div>
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Qualificação
                </h3>
                <p class="mt-1 text-sm text-muted-foreground">
                    Usada no contrato como locador. Pode ser preenchida depois.
                </p>
            </div>

            <PersonQualificationFields :value="owner" :errors="errors" />
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Endereço
            </h3>

            <AddressFields :value="owner" :errors="errors" />
        </section>

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Recebimento
            </h3>

            <div class="grid gap-2">
                <Label for="pix_key">
                    Chave Pix
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    >
                </Label>
                <Input
                    id="pix_key"
                    name="pix_key"
                    placeholder="CPF, e-mail, telefone ou chave aleatória"
                    :default-value="owner?.pix_key ?? ''"
                />
                <p class="text-xs text-muted-foreground">
                    Para onde vão os repasses dos aluguéis.
                </p>
                <InputError :message="errors.pix_key" />
            </div>
        </section>

        <section v-if="payoutDefaults" class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Condições de repasse
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            showPayoutRules
                                ? 'Em branco, segue o padrão da imobiliária. Cada contrato ainda pode ter as suas.'
                                : 'Segue o padrão da imobiliária.'
                        }}
                    </p>
                </div>
                <Button
                    v-if="!showPayoutRules"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="showPayoutRules = true"
                >
                    Definir condições próprias
                </Button>
            </div>

            <PayoutRulesFields
                v-if="showPayoutRules"
                :values="owner ?? null"
                :inherited="payoutDefaults"
                :errors="errors"
            />
        </section>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ owner ? 'Salvar alterações' : 'Cadastrar proprietário' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
