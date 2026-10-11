<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PayoutSettingsController from '@/actions/App/Http/Controllers/Settings/PayoutSettingsController';
import Heading from '@/components/Heading.vue';
import PayoutRulesFields from '@/components/PayoutRulesFields.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/payout-settings';
import type { PayoutRules } from '@/types';

defineProps<{
    rules: PayoutRules;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Taxas e repasses',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Taxas e repasses" />

    <h1 class="sr-only">Taxas e repasses</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Taxas e repasses"
            description="O padrão da imobiliária. Cada proprietário e cada contrato pode ter condições diferentes."
        />

        <Form
            v-bind="PayoutSettingsController.update.form()"
            class="space-y-8"
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing }"
        >
            <PayoutRulesFields :values="rules" :errors="errors" />

            <Button :disabled="processing">Salvar</Button>
        </Form>
    </div>
</template>
