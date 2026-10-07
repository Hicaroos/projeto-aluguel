<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Braces, FileSearch, Info, Search } from '@lucide/vue';
import { computed, ref, useTemplateRef } from 'vue';
import ContractTemplateController from '@/actions/App/Http/Controllers/ContractTemplateController';
import ContractEditor from '@/components/contract-editor/ContractEditor.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, preview } from '@/routes/contract-templates';
import type { ContractTemplate, ContractVariableOption } from '@/types';

const props = defineProps<{
    template: Pick<
        ContractTemplate,
        'id' | 'name' | 'body' | 'is_default'
    > | null;
    variables: ContractVariableOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Modelos de contrato', href: '/contract-templates' },
        ],
    },
});

const formAction = computed(() =>
    props.template
        ? ContractTemplateController.update.form(props.template)
        : ContractTemplateController.store.form(),
);

const body = ref(props.template?.body ?? '');
const editor = useTemplateRef('editor');

const variableLabels = Object.fromEntries(
    props.variables.map((variable) => [variable.key, variable.label]),
);

const variableSearch = ref('');

const variableGroups = computed(() => {
    const term = variableSearch.value.trim().toLocaleLowerCase('pt-BR');
    const groups = new Map<string, ContractVariableOption[]>();

    for (const variable of props.variables) {
        if (term && !variable.label.toLocaleLowerCase('pt-BR').includes(term)) {
            continue;
        }

        groups.set(variable.group, [
            ...(groups.get(variable.group) ?? []),
            variable,
        ]);
    }

    return [...groups.entries()];
});

const isPreviewing = ref(false);
const previewError = ref<string | null>(null);

function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

/**
 * Generate the PDF of what is in the editor, saved or not, and open it in a new tab.
 * The tab opens right away so the browser does not block it as a pop-up.
 */
async function previewPdf() {
    const previewTab = window.open('', '_blank');
    isPreviewing.value = true;
    previewError.value = null;

    try {
        const response = await fetch(preview().url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json, application/pdf',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ body: body.value }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => null);
            previewError.value =
                data?.errors?.body?.[0] ??
                'Não foi possível gerar a pré-visualização.';
            previewTab?.close();

            return;
        }

        const pdfUrl = URL.createObjectURL(await response.blob());

        if (previewTab) {
            previewTab.location.href = pdfUrl;
        } else {
            window.open(pdfUrl, '_blank');
        }
    } catch {
        previewError.value = 'Não foi possível gerar a pré-visualização.';
        previewTab?.close();
    } finally {
        isPreviewing.value = false;
    }
}
</script>

<template>
    <Head
        :title="
            template ? `Editar ${template.name}` : 'Novo modelo de contrato'
        "
    />

    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-1 flex-col gap-6 p-4 md:p-6"
    >
        <PageHeader
            :title="
                template
                    ? 'Editar modelo de contrato'
                    : 'Novo modelo de contrato'
            "
            description="Escreva o texto do contrato e insira as variáveis onde os dados devem aparecer."
        >
            <Button variant="outline" as-child>
                <Link :href="index()">
                    <ArrowLeft class="size-4" />
                    Voltar
                </Link>
            </Button>
            <Button
                type="button"
                variant="outline"
                :disabled="isPreviewing"
                @click="previewPdf"
            >
                <Spinner v-if="isPreviewing" />
                <FileSearch v-else class="size-4" />
                Pré-visualizar PDF
            </Button>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ template ? 'Salvar alterações' : 'Cadastrar modelo' }}
            </Button>
        </PageHeader>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div class="grid gap-4">
                <div class="grid gap-2 sm:max-w-md">
                    <Label for="name">Nome do modelo</Label>
                    <Input
                        id="name"
                        name="name"
                        placeholder="Ex.: Contrato residencial"
                        :default-value="template?.name ?? ''"
                    />
                    <InputError :message="errors.name" />
                </div>

                <input type="hidden" name="body" :value="body" />
                <ContractEditor
                    ref="editor"
                    v-model="body"
                    :labels="variableLabels"
                />
                <InputError
                    :message="errors.body ?? previewError ?? undefined"
                />
                <p class="flex gap-2 text-xs text-muted-foreground">
                    <Info class="mt-0.5 size-3.5 shrink-0" />
                    O texto tem a mesma largura e fonte do PDF, então as linhas
                    quebram no mesmo lugar. As marcas nas laterais indicam
                    aproximadamente o fim de cada página; para o resultado
                    exato, use "Pré-visualizar PDF".
                </p>
            </div>

            <aside
                class="grid gap-4 rounded-xl border bg-card p-4 shadow-xs lg:sticky lg:top-4 lg:max-h-[calc(100dvh-2rem)] lg:overflow-y-auto"
            >
                <div class="space-y-1">
                    <h2 class="flex items-center gap-2 text-sm font-semibold">
                        <Braces class="size-4 text-primary" />
                        Variáveis
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Clique para inserir na posição do cursor. No PDF, cada
                        variável é trocada pelos dados do contrato.
                    </p>
                </div>

                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="variableSearch"
                        type="search"
                        placeholder="Buscar variável"
                        class="pl-9"
                        aria-label="Buscar variável"
                    />
                </div>

                <div
                    v-for="[group, groupVariables] in variableGroups"
                    :key="group"
                    class="space-y-2"
                >
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ group }}
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="variable in groupVariables"
                            :key="variable.key"
                            type="button"
                            class="rounded-md bg-primary/10 px-2 py-1 text-left text-xs font-medium text-primary transition-colors hover:bg-primary/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @click="editor?.insertVariable(variable.key)"
                        >
                            {{ variable.label }}
                        </button>
                    </div>
                </div>

                <p
                    v-if="variableGroups.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Nenhuma variável encontrada.
                </p>

                <p
                    class="flex gap-2 rounded-lg bg-muted/60 p-3 text-xs text-muted-foreground"
                >
                    <Info class="mt-0.5 size-3.5 shrink-0" />
                    Dados não preenchidos saem como uma linha em branco (______)
                    para completar à mão.
                </p>
            </aside>
        </div>
    </Form>
</template>
