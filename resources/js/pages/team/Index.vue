<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Link2,
    MoreHorizontal,
    Pencil,
    Power,
    PowerOff,
    Trash2,
    UserPlus,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ConfirmDeleteDialog from '@/components/ConfirmDeleteDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import { formatDate } from '@/lib/formatters';
import {
    roleDescriptions,
    teamMemberStatusClasses,
    teamMemberStatusLabels,
} from '@/lib/team-labels';
import InvitationLinkDialog from '@/pages/team/InvitationLinkDialog.vue';
import TeamMemberForm from '@/pages/team/TeamMemberForm.vue';
import { destroy, index, invitation, status } from '@/routes/team';
import type { InvitationLink, RoleOption, TeamMember } from '@/types';

const props = defineProps<{
    members: TeamMember[];
    roles: RoleOption[];
    branches: { id: number; name: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Equipe', href: index() }],
    },
});

const page = usePage();

const roleLabels = computed(
    () =>
        Object.fromEntries(
            props.roles.map((role) => [role.value, role.label]),
        ) as Record<TeamMember['role'], string>,
);

const isFormDialogOpen = ref(false);
const formDialogMember = ref<TeamMember | null>(null);

function openInviteDialog() {
    formDialogMember.value = null;
    isFormDialogOpen.value = true;
}

function openEditDialog(member: TeamMember) {
    formDialogMember.value = member;
    isFormDialogOpen.value = true;
}

/** The link of the invitation just created or renewed, handed over by the server once. */
const invitationLink = ref<InvitationLink | null>(null);

watch(
    () => page.flash?.invitation,
    (value) => {
        if (value) {
            invitationLink.value = value as InvitationLink;
        }
    },
    { immediate: true },
);

function renewInvitation(member: TeamMember) {
    router.post(invitation(member).url, {}, { preserveScroll: true });
}

function toggleStatus(member: TeamMember) {
    router.patch(status(member).url, {}, { preserveScroll: true });
}

const memberToCancel = ref<TeamMember | null>(null);
const isCanceling = ref(false);

function confirmCancel() {
    if (!memberToCancel.value) {
        return;
    }

    isCanceling.value = true;

    router.delete(destroy(memberToCancel.value).url, {
        preserveScroll: true,
        onFinish: () => {
            isCanceling.value = false;
            memberToCancel.value = null;
        },
    });
}

function isLocked(member: TeamMember): boolean {
    return member.is_owner || member.is_self;
}

function isInvitation(member: TeamMember): boolean {
    return member.status === 'pending' || member.status === 'expired';
}

function branchesLine(member: TeamMember): string {
    if (member.role === 'admin') {
        return 'Todas as unidades';
    }

    return member.branches.map((branch) => branch.name).join(', ') || '—';
}
</script>

<template>
    <Head title="Equipe" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Equipe"
            description="Quem trabalha na imobiliária, o que cada pessoa pode fazer e em quais unidades."
        >
            <Button @click="openInviteDialog">
                <UserPlus class="size-4" />
                Convidar pessoa
            </Button>
        </PageHeader>

        <ul class="divide-y rounded-xl border bg-card shadow-xs">
            <li
                v-for="member in members"
                :key="member.id"
                class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                :class="{ 'opacity-60': member.status === 'inactive' }"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <Avatar class="size-10">
                        <AvatarFallback class="bg-primary/10 text-primary">
                            {{ getInitials(member.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <div class="min-w-0 space-y-0.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate font-medium">
                                {{ member.name }}
                            </p>
                            <Badge v-if="member.is_self" variant="secondary">
                                Você
                            </Badge>
                            <Badge v-if="member.is_owner" variant="outline">
                                Criou a conta
                            </Badge>
                        </div>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ member.email }}
                        </p>
                    </div>
                </div>

                <div
                    class="grid grid-cols-2 gap-3 text-sm sm:flex sm:w-auto sm:items-center sm:gap-6"
                >
                    <div class="sm:w-44">
                        <p class="font-medium">
                            {{ roleLabels[member.role] }}
                        </p>
                        <p
                            class="truncate text-xs text-muted-foreground"
                            :title="branchesLine(member)"
                        >
                            {{ branchesLine(member) }}
                        </p>
                    </div>
                    <div class="sm:w-40">
                        <Badge
                            variant="outline"
                            :class="teamMemberStatusClasses[member.status]"
                        >
                            {{ teamMemberStatusLabels[member.status] }}
                        </Badge>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <template v-if="isInvitation(member)">
                                Convidado em
                                {{ formatDate(member.invited_at ?? '') }}
                            </template>
                            <template v-else-if="member.last_login_at">
                                Último acesso
                                {{ formatDate(member.last_login_at) }}
                            </template>
                            <template v-else>Ainda não entrou</template>
                        </p>
                    </div>
                </div>

                <DropdownMenu v-if="!isLocked(member)">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="self-end sm:self-center"
                        >
                            <MoreHorizontal class="size-4" />
                            <span class="sr-only">Ações</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem @click="openEditDialog(member)">
                            <Pencil class="size-4" />
                            Editar acesso
                        </DropdownMenuItem>
                        <template v-if="isInvitation(member)">
                            <DropdownMenuItem @click="renewInvitation(member)">
                                <Link2 class="size-4" />
                                Gerar novo link
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                @click="memberToCancel = member"
                            >
                                <Trash2 class="size-4" />
                                Cancelar convite
                            </DropdownMenuItem>
                        </template>
                        <DropdownMenuItem v-else @click="toggleStatus(member)">
                            <component
                                :is="
                                    member.status === 'inactive'
                                        ? Power
                                        : PowerOff
                                "
                                class="size-4"
                            />
                            {{
                                member.status === 'inactive'
                                    ? 'Reativar acesso'
                                    : 'Desativar acesso'
                            }}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <div v-else class="hidden w-8 sm:block" />
            </li>
        </ul>

        <section class="grid gap-3">
            <h2
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Papéis
            </h2>
            <dl class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="role in roles"
                    :key="role.value"
                    class="rounded-xl border bg-card p-4 text-sm shadow-xs"
                >
                    <dt class="font-medium">{{ role.label }}</dt>
                    <dd class="mt-1 text-muted-foreground">
                        {{ roleDescriptions[role.value] }}
                    </dd>
                </div>
            </dl>
        </section>
    </div>

    <Dialog v-model:open="isFormDialogOpen">
        <DialogScrollContent class="sm:max-w-xl">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <component
                        :is="formDialogMember ? Pencil : Users"
                        class="size-5"
                    />
                </div>
                <div class="space-y-1">
                    <DialogTitle>
                        {{
                            formDialogMember
                                ? 'Editar acesso'
                                : 'Convidar pessoa'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            formDialogMember
                                ? 'Mude o papel e as unidades da pessoa.'
                                : 'Você recebe um link para enviar à pessoa, que cria a própria senha.'
                        }}
                    </DialogDescription>
                </div>
            </DialogHeader>
            <TeamMemberForm
                :key="formDialogMember?.id ?? 'invite'"
                :member="formDialogMember"
                :roles="roles"
                :branches="branches"
                @success="isFormDialogOpen = false"
            />
        </DialogScrollContent>
    </Dialog>

    <InvitationLinkDialog
        :invitation="invitationLink"
        @close="invitationLink = null"
    />

    <ConfirmDeleteDialog
        :open="!!memberToCancel"
        title="Cancelar convite?"
        :processing="isCanceling"
        @close="memberToCancel = null"
        @confirm="confirmCancel"
    >
        O link enviado para
        <span class="font-medium text-foreground">{{
            memberToCancel?.name
        }}</span>
        deixa de funcionar.
    </ConfirmDeleteDialog>
</template>
