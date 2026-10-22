<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Braces, MessageCircle, RotateCcw } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MessageEditor from '@/components/MessageEditor.vue';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/composables/usePermissions';
import { renderMessageTemplate, whatsAppSegments } from '@/lib/whatsapp';
import { edit as editAgency } from '@/routes/agency';
import { edit, update } from '@/routes/message-settings';
import { edit as editOwner } from '@/routes/owner';
import type { MessageTemplateKey, MessageTemplateSetting } from '@/types';

const props = defineProps<{
    templates: MessageTemplateSetting[];
    hasPixKey: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mensagens do WhatsApp',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const can = usePermissions();
const isAgency = page.props.auth.user.account?.type !== 'single_owner';

const form = useForm({
    message_templates: Object.fromEntries(
        props.templates.map((template) => [template.key, template.text]),
    ) as Record<MessageTemplateKey, string>,
});

const editors = ref<
    Partial<Record<MessageTemplateKey, InstanceType<typeof MessageEditor>>>
>({});

/** The label shown on each variable tag, keyed by variable. */
function variableLabels(
    template: MessageTemplateSetting,
): Record<string, string> {
    return Object.fromEntries(
        template.variables.map((variable) => [variable.key, variable.label]),
    );
}

function errorFor(key: MessageTemplateKey): string | undefined {
    return (form.errors as Record<string, string | undefined>)[
        `message_templates.${key}`
    ];
}

function submit() {
    form.put(update().url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Mensagens do WhatsApp" />

    <h1 class="sr-only">Mensagens do WhatsApp</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Mensagens do WhatsApp"
            description="Os botões de WhatsApp abrem a conversa no seu celular ou computador com a mensagem já escrita. Você confere e envia do seu próprio número."
        />

        <p
            v-if="!hasPixKey"
            class="rounded-lg border border-attention-border bg-attention px-3 py-2 text-sm text-attention-foreground"
        >
            Ainda não há chave Pix cadastrada, então a linha da chave sai das
            cobranças.
            <Link
                v-if="isAgency && can.manageAgency"
                :href="editAgency()"
                class="font-medium underline underline-offset-4"
                >Cadastrar em Dados da imobiliária</Link
            >
            <Link
                v-else-if="!isAgency"
                :href="editOwner()"
                class="font-medium underline underline-offset-4"
                >Cadastrar em Dados do proprietário</Link
            >
            <template v-else
                >Peça a um administrador para cadastrá-la em Dados da
                imobiliária.</template
            >
        </p>

        <form class="space-y-10" @submit.prevent="submit">
            <section
                v-for="template in templates"
                :key="template.key"
                class="grid gap-3"
            >
                <div class="space-y-1">
                    <h3 class="text-sm font-medium">{{ template.label }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{ template.description }}
                    </p>
                </div>

                <MessageEditor
                    :ref="
                        (editor) =>
                            (editors[template.key] =
                                (editor as InstanceType<
                                    typeof MessageEditor
                                > | null) ?? undefined)
                    "
                    v-model="form.message_templates[template.key]"
                    :labels="variableLabels(template)"
                    :name="template.label"
                />

                <div class="space-y-2">
                    <p
                        class="flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Braces class="size-3.5 text-primary" />
                        Clique para inserir na posição do cursor:
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="variable in template.variables"
                            :key="variable.key"
                            type="button"
                            class="rounded-md bg-primary/10 px-2 py-1 text-left text-xs font-medium text-primary transition-colors hover:bg-primary/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="
                                editors[template.key]?.insertVariable(
                                    variable.key,
                                )
                            "
                        >
                            {{ variable.label }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2">
                    <InputError :message="errorFor(template.key)" />
                    <Button
                        v-if="
                            form.message_templates[template.key].trim() !==
                            template.default
                        "
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="ml-auto"
                        @click="
                            form.message_templates[template.key] =
                                template.default
                        "
                    >
                        <RotateCcw class="size-4" />
                        Voltar ao texto padrão
                    </Button>
                </div>

                <div class="rounded-lg border bg-muted/40 p-3">
                    <p
                        class="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <MessageCircle class="size-3.5" />
                        Prévia com dados de exemplo
                    </p>
                    <p
                        class="max-w-md rounded-lg bg-background px-3 py-2 text-sm whitespace-pre-line shadow-xs"
                    >
                        <template
                            v-for="(segment, index) in whatsAppSegments(
                                renderMessageTemplate(
                                    form.message_templates[template.key] ||
                                        template.default,
                                    template.sample,
                                ),
                            )"
                            :key="index"
                        >
                            <strong v-if="segment.bold">{{
                                segment.text
                            }}</strong>
                            <template v-else>{{ segment.text }}</template>
                        </template>
                    </p>
                </div>
            </section>

            <p class="text-sm text-muted-foreground">
                Uma linha com variável vazia sai da mensagem, como a da chave
                Pix quando não há chave cadastrada. O negrito aparece no
                WhatsApp como no editor.
            </p>

            <Button :disabled="form.processing">Salvar</Button>
        </form>
    </div>
</template>
