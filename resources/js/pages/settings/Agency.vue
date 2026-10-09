<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ImageUp, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AgencyController from '@/actions/App/Http/Controllers/Settings/AgencyController';
import AddressFields from '@/components/AddressFields.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/agency';
import {
    destroy as destroyLogo,
    update as updateLogo,
} from '@/routes/agency/logo';
import type { Account } from '@/types';

defineProps<{
    agency: Account;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dados da imobiliária',
                href: edit(),
            },
        ],
    },
});

const logoInput = ref<HTMLInputElement | null>(null);
const isUploadingLogo = ref(false);
const isRemovingLogo = ref(false);
const logoError = ref<string | undefined>();

function uploadLogo(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    input.value = '';

    if (!file) {
        return;
    }

    isUploadingLogo.value = true;
    logoError.value = undefined;

    router.post(
        updateLogo().url,
        { logo: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => (logoError.value = errors.logo),
            onFinish: () => (isUploadingLogo.value = false),
        },
    );
}

function removeLogo() {
    isRemovingLogo.value = true;

    router.delete(destroyLogo().url, {
        preserveScroll: true,
        onFinish: () => (isRemovingLogo.value = false),
    });
}
</script>

<template>
    <Head title="Dados da imobiliária" />

    <h1 class="sr-only">Dados da imobiliária</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Dados da imobiliária"
            description="Informações da empresa usadas nos contratos, recibos e no sistema."
        />

        <section class="grid gap-4">
            <h3
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Logo
            </h3>

            <div class="flex items-center gap-4">
                <div
                    class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted/40"
                >
                    <img
                        v-if="agency.logo_url"
                        :src="agency.logo_url"
                        alt="Logo da imobiliária"
                        class="size-full object-contain"
                    />
                    <ImageUp v-else class="size-6 text-muted-foreground" />
                </div>

                <div class="flex flex-col gap-2">
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="isUploadingLogo"
                            @click="logoInput?.click()"
                        >
                            <Spinner v-if="isUploadingLogo" />
                            <ImageUp v-else class="size-4" />
                            {{
                                agency.logo_url ? 'Trocar logo' : 'Enviar logo'
                            }}
                        </Button>
                        <Button
                            v-if="agency.logo_url"
                            type="button"
                            variant="ghost"
                            size="sm"
                            :disabled="isRemovingLogo"
                            @click="removeLogo"
                        >
                            <Spinner v-if="isRemovingLogo" />
                            <Trash2 v-else class="size-4" />
                            Remover
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        PNG, JPG ou WEBP de até 2 MB. Prefira uma imagem
                        quadrada.
                    </p>
                    <InputError :message="logoError" />
                </div>

                <input
                    ref="logoInput"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    @change="uploadLogo"
                />
            </div>
        </section>

        <Form
            v-bind="AgencyController.update.form()"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Empresa
                </h3>

                <div class="grid gap-2">
                    <Label for="name">Nome fantasia</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Ex.: Imobiliária Silva"
                        :default-value="agency.name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="legal_name">
                        Razão social
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="legal_name"
                        name="legal_name"
                        placeholder="Ex.: Silva Negócios Imobiliários Ltda."
                        :default-value="agency.legal_name ?? ''"
                    />
                    <InputError :message="errors.legal_name" />
                </div>

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="document">CNPJ</Label>
                        <MaskedInput
                            id="document"
                            mask="document"
                            name="document"
                            required
                            placeholder="00.000.000/0000-00"
                            :default-value="agency.document"
                        />
                        <InputError :message="errors.document" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="creci">
                            CRECI
                            <span class="font-normal text-muted-foreground"
                                >(opcional)</span
                            >
                        </Label>
                        <Input
                            id="creci"
                            name="creci"
                            maxlength="20"
                            placeholder="Ex.: 1234-J"
                            :default-value="agency.creci ?? ''"
                        />
                        <InputError :message="errors.creci" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Contato
                </h3>

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="phone">Telefone</Label>
                        <MaskedInput
                            id="phone"
                            mask="phone"
                            name="phone"
                            type="tel"
                            required
                            placeholder="(00) 0000-0000"
                            :default-value="agency.phone"
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
                            placeholder="contato@imobiliaria.com.br"
                            :default-value="agency.email ?? ''"
                        />
                        <InputError :message="errors.email" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Endereço da sede
                </h3>

                <AddressFields :value="agency" :errors="errors" />
            </section>

            <Button :disabled="processing">Salvar</Button>
        </Form>
    </div>
</template>
