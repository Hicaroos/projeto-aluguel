<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { roleDescriptions } from '@/lib/team-labels';
import type { Role, RoleOption, TeamMember } from '@/types';

const props = defineProps<{
    member?: TeamMember | null;
    roles: RoleOption[];
    branches: { id: number; name: string }[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const formAction = computed(() =>
    props.member
        ? TeamController.update.form(props.member)
        : TeamController.store.form(),
);

const role = ref<Role>(props.member?.role ?? 'agent');

const seesEveryBranch = computed(
    () =>
        props.roles.find((option) => option.value === role.value)
            ?.sees_every_branch ?? false,
);

/** Active branches, plus the ones the member already works in. */
const selectableBranches = computed(() => {
    const current = props.member?.branches ?? [];

    return [
        ...props.branches,
        ...current.filter(
            (branch) => !props.branches.some(({ id }) => id === branch.id),
        ),
    ];
});

const memberBranchIds = new Set(
    props.member?.branches.map((branch) => branch.id) ?? [],
);

/** A new member of a single branch agency works in that branch. */
function isInitiallyChecked(branchId: number): boolean {
    return props.member
        ? memberBranchIds.has(branchId)
        : selectableBranches.value.length === 1;
}
</script>

<template>
    <Form
        v-bind="formAction"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @success="emit('success')"
    >
        <div class="grid items-start gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    name="name"
                    autofocus
                    autocomplete="off"
                    placeholder="Nome completo"
                    :default-value="member?.name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-mail</Label>
                <Input
                    v-if="!member"
                    id="email"
                    name="email"
                    type="email"
                    autocomplete="off"
                    placeholder="pessoa@exemplo.com"
                />
                <div
                    v-else
                    class="flex h-9 items-center truncate rounded-md border bg-muted/40 px-3 text-sm text-muted-foreground"
                >
                    {{ member.email }}
                </div>
                <InputError :message="errors.email" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="role">Papel</Label>
            <Select v-model="role" name="role">
                <SelectTrigger id="role" class="w-full">
                    <SelectValue placeholder="Selecione o papel" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in roles"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
                {{ roleDescriptions[role] }}
            </p>
            <InputError :message="errors.role" />
        </div>

        <fieldset v-if="!seesEveryBranch" class="grid gap-3">
            <legend class="mb-2 text-sm font-medium">Unidades</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <Label
                    v-for="branch in selectableBranches"
                    :key="branch.id"
                    class="flex items-center gap-3 rounded-md border p-3 font-normal"
                >
                    <Checkbox
                        name="branch_ids[]"
                        :value="String(branch.id)"
                        :default-value="isInitiallyChecked(branch.id)"
                    />
                    {{ branch.name }}
                </Label>
            </div>
            <p class="text-xs text-muted-foreground">
                A pessoa só vê os imóveis, contratos e cobranças das unidades
                marcadas.
            </p>
            <InputError :message="errors.branch_ids" />
        </fieldset>

        <p v-else class="text-sm text-muted-foreground">
            Administradores trabalham em todas as unidades.
        </p>

        <DialogFooter class="border-t pt-6">
            <DialogClose as-child>
                <Button type="button" variant="outline">Cancelar</Button>
            </DialogClose>
            <Button type="submit" :disabled="processing">
                <Spinner v-if="processing" />
                {{ member ? 'Salvar alterações' : 'Criar convite' }}
            </Button>
        </DialogFooter>
    </Form>
</template>
