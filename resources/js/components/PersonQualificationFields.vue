<script setup lang="ts">
import { ref } from 'vue';
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
import { maritalStatusLabels } from '@/lib/person-labels';
import type { MaritalStatus, PersonQualification } from '@/types';

type QualificationField =
    'rg' | 'nationality' | 'marital_status' | 'profession';

const props = defineProps<{
    /** Initial values, e.g. the record being edited. */
    value?: Partial<Pick<PersonQualification, QualificationField>> | null;
    errors: Record<string, string | undefined>;
    /** Nests the inputs under this key, e.g. "guarantor" posts guarantor[rg]. */
    namePrefix?: string;
}>();

const unsetMaritalStatus = 'unset';

const inputName = (field: QualificationField): string =>
    props.namePrefix ? `${props.namePrefix}[${field}]` : field;
const inputId = (field: QualificationField): string =>
    props.namePrefix ? `${props.namePrefix}_${field}` : field;
const errorFor = (field: QualificationField): string | undefined =>
    props.errors[props.namePrefix ? `${props.namePrefix}.${field}` : field];

const maritalStatus = ref<MaritalStatus | typeof unsetMaritalStatus>(
    props.value?.marital_status ?? unsetMaritalStatus,
);
</script>

<template>
    <div class="grid items-start gap-4 sm:grid-cols-2">
        <div class="grid gap-2">
            <Label :for="inputId('rg')">RG</Label>
            <Input
                :id="inputId('rg')"
                :name="inputName('rg')"
                placeholder="00.000.000-0"
                :default-value="value?.rg ?? ''"
            />
            <InputError :message="errorFor('rg')" />
        </div>

        <div class="grid gap-2">
            <Label :for="inputId('nationality')">Nacionalidade</Label>
            <Input
                :id="inputId('nationality')"
                :name="inputName('nationality')"
                placeholder="brasileira"
                :default-value="value?.nationality ?? ''"
            />
            <InputError :message="errorFor('nationality')" />
        </div>

        <div class="grid gap-2">
            <Label :for="inputId('marital_status')">Estado civil</Label>
            <input
                type="hidden"
                :name="inputName('marital_status')"
                :value="
                    maritalStatus === unsetMaritalStatus ? '' : maritalStatus
                "
            />
            <Select v-model="maritalStatus">
                <SelectTrigger :id="inputId('marital_status')" class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="unsetMaritalStatus">
                        Não informado
                    </SelectItem>
                    <SelectItem
                        v-for="(label, key) in maritalStatusLabels"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errorFor('marital_status')" />
        </div>

        <div class="grid gap-2">
            <Label :for="inputId('profession')">Profissão</Label>
            <Input
                :id="inputId('profession')"
                :name="inputName('profession')"
                placeholder="Ex.: Professora"
                :default-value="value?.profession ?? ''"
            />
            <InputError :message="errorFor('profession')" />
        </div>
    </div>
</template>
