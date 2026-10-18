<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Check, Copy, ExternalLink } from '@lucide/vue';
import { computed, ref } from 'vue';
import ShowcaseController from '@/actions/App/Http/Controllers/Settings/ShowcaseController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MaskedInput from '@/components/MaskedInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as propertiesIndex } from '@/routes/properties';
import { edit } from '@/routes/showcase-settings';
import type { ShowcaseTextKey } from '@/types';

const props = defineProps<{
    showcase: {
        showcase_enabled: boolean;
        showcase_slug: string;
        showcase_whatsapp: string | null;
        showcase_color: string;
        showcase_headline: string | null;
        showcase_shows_address: boolean;
        showcase_instagram: string | null;
        showcase_facebook: string | null;
        showcase_youtube: string | null;
        /** The showcase address, while it is online. */
        url: string | null;
    };
    /** The site address the showcase address goes after. */
    baseUrl: string;
    colors: { value: string; label: string; hex: string }[];
    /** The showcase texts, the account's own or the defaults. */
    texts: Record<ShowcaseTextKey, string>;
    defaultTexts: Record<ShowcaseTextKey, string>;
    textLimits: Record<ShowcaseTextKey, number>;
    /** Only agencies show the "why us" section and the banners. */
    isAgency: boolean;
    publishedCount: number;
    propertiesCount: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vitrine', href: edit() }],
    },
});

const isEnabled = ref(props.showcase.showcase_enabled);
const color = ref(props.showcase.showcase_color);
const showsAddress = ref(props.showcase.showcase_shows_address);
const isCopied = ref(false);

const socials = [
    {
        name: 'showcase_instagram',
        label: 'Instagram',
        placeholder: '@suaimobiliaria',
    },
    {
        name: 'showcase_facebook',
        label: 'Facebook',
        placeholder: 'facebook.com/suaimobiliaria',
    },
    {
        name: 'showcase_youtube',
        label: 'YouTube',
        placeholder: '@suaimobiliaria',
    },
] as const;

type TextField = { key: ShowcaseTextKey; label: string; isLong?: boolean };

type TextGroup = { title: string; fields: TextField[] };

const textGroups = computed<TextGroup[]>(() => {
    const groups: TextGroup[] = [
        {
            title: 'Rodapé',
            fields: [{ key: 'about', label: 'Sobre', isLong: true }],
        },
    ];

    if (!props.isAgency) {
        return groups;
    }

    return [
        ...groups,
        {
            title: 'Por que escolher',
            fields: [
                { key: 'why_lead', label: 'Introdução', isLong: true },
                ...([1, 2, 3, 4] as const).flatMap((position): TextField[] => [
                    {
                        key: `benefit_${position}_title` as ShowcaseTextKey,
                        label: `Vantagem ${position}`,
                    },
                    {
                        key: `benefit_${position}_text` as ShowcaseTextKey,
                        label: `Explicação da vantagem ${position}`,
                        isLong: true,
                    },
                ]),
            ],
        },
        {
            title: 'Banners da página inicial',
            fields: ([1, 2, 3] as const).flatMap((position): TextField[] => [
                {
                    key: `banner_${position}_title` as ShowcaseTextKey,
                    label: `Banner ${position}`,
                },
                {
                    key: `banner_${position}_action` as ShowcaseTextKey,
                    label: `Chamada do banner ${position}`,
                },
            ]),
        },
    ];
});

const textareaClass =
    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30';

function copyUrl() {
    if (!props.showcase.url) {
        return;
    }

    navigator.clipboard.writeText(props.showcase.url).then(() => {
        isCopied.value = true;
        setTimeout(() => (isCopied.value = false), 2000);
    });
}
</script>

<template>
    <Head title="Vitrine" />

    <h1 class="sr-only">Vitrine</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Vitrine"
            description="Um site com os imóveis publicados, para mandar aos clientes e divulgar nas redes. Quem pede uma visita entra no funil como interessado."
        />

        <div
            v-if="showcase.url"
            class="flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center"
        >
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">A vitrine está no ar</p>
                <p class="truncate text-sm text-muted-foreground">
                    {{ showcase.url }}
                </p>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" @click="copyUrl">
                    <Check v-if="isCopied" class="size-4" />
                    <Copy v-else class="size-4" />
                    {{ isCopied ? 'Copiado' : 'Copiar link' }}
                </Button>
                <Button size="sm" as-child>
                    <a :href="showcase.url" target="_blank" rel="noopener">
                        <ExternalLink class="size-4" />
                        Abrir
                    </a>
                </Button>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            <span class="font-medium text-foreground tabular-nums">
                {{ publishedCount }} de {{ propertiesCount }}
            </span>
            {{ propertiesCount === 1 ? 'imóvel aparece' : 'imóveis aparecem' }}
            na vitrine. Para publicar um imóvel, edite-o em
            <Link
                :href="propertiesIndex()"
                class="underline underline-offset-4"
            >
                Imóveis
            </Link>
            e marque "Publicar na vitrine". Imóveis alugados ou vendidos saem
            sozinhos.
        </p>

        <Form
            v-bind="ShowcaseController.update.form()"
            class="space-y-8"
            v-slot="{ errors, processing }"
        >
            <input
                type="hidden"
                name="showcase_enabled"
                :value="isEnabled ? 1 : 0"
            />

            <Label
                class="flex items-start gap-3 rounded-md border p-4 font-normal"
            >
                <Checkbox v-model="isEnabled" class="mt-0.5" />
                <span class="grid gap-1">
                    <span class="font-medium">Deixar a vitrine no ar</span>
                    <span class="text-sm text-muted-foreground">
                        Qualquer pessoa com o endereço vê os imóveis publicados.
                    </span>
                </span>
            </Label>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Endereço e contato
                </h3>

                <div class="grid gap-2">
                    <Label for="showcase_slug">Endereço da vitrine</Label>
                    <div
                        class="flex items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                    >
                        <span
                            class="shrink-0 border-r bg-muted/40 px-3 py-2 text-sm text-muted-foreground max-sm:hidden"
                            >{{ baseUrl }}</span
                        >
                        <input
                            id="showcase_slug"
                            name="showcase_slug"
                            class="h-9 min-w-0 flex-1 bg-transparent px-3 text-base outline-none md:text-sm"
                            placeholder="sua-imobiliaria"
                            maxlength="40"
                            autocomplete="off"
                            :value="showcase.showcase_slug"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Letras minúsculas, números e hífens. Mudar o endereço
                        faz os links antigos pararem de funcionar.
                    </p>
                    <InputError :message="errors.showcase_slug" />
                </div>

                <div class="grid gap-2 sm:max-w-xs">
                    <Label for="showcase_whatsapp"
                        >WhatsApp de atendimento</Label
                    >
                    <MaskedInput
                        id="showcase_whatsapp"
                        name="showcase_whatsapp"
                        mask="phone"
                        placeholder="(00) 00000-0000"
                        :default-value="showcase.showcase_whatsapp"
                    />
                    <p class="text-xs text-muted-foreground">
                        Os botões da vitrine abrem uma conversa com este número,
                        com a mensagem e o código do imóvel já escritos.
                    </p>
                    <InputError :message="errors.showcase_whatsapp" />
                </div>
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Aparência
                </h3>

                <fieldset class="grid gap-2">
                    <legend class="mb-2 text-sm font-medium">Cor</legend>
                    <input type="hidden" name="showcase_color" :value="color" />
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="option in colors"
                            :key="option.value"
                            type="button"
                            class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm transition-colors hover:bg-accent"
                            :class="
                                color === option.value
                                    ? 'border-foreground'
                                    : ''
                            "
                            :aria-pressed="color === option.value"
                            @click="color = option.value"
                        >
                            <span
                                class="size-4 rounded-sm"
                                :style="{ backgroundColor: option.hex }"
                            />
                            {{ option.label }}
                        </button>
                    </div>
                    <InputError :message="errors.showcase_color" />
                </fieldset>

                <div class="grid gap-2">
                    <Label for="showcase_headline">
                        Título da página inicial
                        <span class="font-normal text-muted-foreground"
                            >(opcional)</span
                        >
                    </Label>
                    <Input
                        id="showcase_headline"
                        name="showcase_headline"
                        maxlength="120"
                        placeholder="Ex.: Casas para comprar e alugar em Ouricuri."
                        :default-value="showcase.showcase_headline ?? ''"
                    />
                    <p class="text-xs text-muted-foreground">
                        Em branco, a vitrine escreve um título com a cidade e o
                        que você oferece.
                    </p>
                    <InputError :message="errors.showcase_headline" />
                </div>
            </section>

            <section class="grid gap-4">
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Página do imóvel
                </h3>

                <input
                    type="hidden"
                    name="showcase_shows_address"
                    :value="showsAddress ? 1 : 0"
                />
                <Label
                    class="flex items-start gap-3 rounded-md border p-4 font-normal"
                >
                    <Checkbox v-model="showsAddress" class="mt-0.5" />
                    <span class="grid gap-1">
                        <span class="font-medium">
                            Mostrar o endereço completo e o mapa
                        </span>
                        <span class="text-sm text-muted-foreground">
                            Sem isso, a vitrine mostra só o bairro e a cidade, e
                            o endereço é passado ao agendar a visita.
                        </span>
                    </span>
                </Label>
            </section>

            <section class="grid gap-4">
                <div>
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Redes sociais
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Aparecem no rodapé da vitrine. Pode colar o link ou
                        escrever só o @ do perfil.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div
                        v-for="social in socials"
                        :key="social.name"
                        class="grid content-start gap-2"
                    >
                        <Label :for="social.name">{{ social.label }}</Label>
                        <Input
                            :id="social.name"
                            :name="social.name"
                            maxlength="255"
                            :placeholder="social.placeholder"
                            :default-value="showcase[social.name] ?? ''"
                        />
                        <InputError :message="errors[social.name]" />
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <div>
                    <h3
                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        Textos
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Apague um texto para voltar ao padrão.
                    </p>
                </div>

                <div
                    v-for="group in textGroups"
                    :key="group.title"
                    class="grid gap-3"
                >
                    <p class="text-sm font-medium">{{ group.title }}</p>
                    <div
                        v-for="field in group.fields"
                        :key="field.key"
                        class="grid gap-2"
                    >
                        <Label
                            :for="`text_${field.key}`"
                            class="text-muted-foreground"
                        >
                            {{ field.label }}
                        </Label>
                        <textarea
                            v-if="field.isLong"
                            :id="`text_${field.key}`"
                            :name="`showcase_texts[${field.key}]`"
                            rows="2"
                            :maxlength="textLimits[field.key]"
                            :placeholder="defaultTexts[field.key]"
                            :class="textareaClass"
                            :value="texts[field.key]"
                        />
                        <Input
                            v-else
                            :id="`text_${field.key}`"
                            :name="`showcase_texts[${field.key}]`"
                            :maxlength="textLimits[field.key]"
                            :placeholder="defaultTexts[field.key]"
                            :default-value="texts[field.key]"
                        />
                        <InputError
                            :message="errors[`showcase_texts.${field.key}`]"
                        />
                    </div>
                </div>
            </section>

            <Button :disabled="processing">Salvar</Button>
        </Form>
    </div>
</template>
