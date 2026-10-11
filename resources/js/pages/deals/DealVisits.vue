<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    CalendarCheck,
    CalendarPlus,
    CalendarX,
    MoreHorizontal,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { usePermissions } from '@/composables/usePermissions';
import {
    formatVisitTime,
    visitStatusBadgeClasses,
    visitStatusLabels,
} from '@/lib/sales-labels';
import { destroy, update } from '@/routes/deal-visits';
import { store } from '@/routes/deals/visits';
import type { Deal, DealVisit, TeamMemberOption } from '@/types';

const props = defineProps<{
    deal: Deal;
    users: TeamMemberOption[];
}>();

const can = usePermissions();

const NONE = 'none';

const isScheduling = ref(false);

/** Tomorrow at 10:00, in the format of a datetime-local input. */
function tomorrowMorning(): string {
    const date = new Date();
    date.setDate(date.getDate() + 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}T10:00`;
}

const form = useForm({
    scheduled_at: tomorrowMorning(),
    user_id: props.deal.user_id ? String(props.deal.user_id) : NONE,
});

function schedule() {
    form.transform((data) => ({
        ...data,
        user_id: data.user_id === NONE ? null : data.user_id,
    })).post(store(props.deal).url, {
        preserveScroll: true,
        onSuccess: () => {
            isScheduling.value = false;
            form.reset();
        },
    });
}

/** The visit being told how it went. */
const visitToFinish = ref<DealVisit | null>(null);
const feedback = ref('');

function startFinishing(visit: DealVisit) {
    visitToFinish.value = visit;
    feedback.value = visit.feedback ?? '';
}

function saveVisit(
    visit: DealVisit,
    status: DealVisit['status'],
    visitFeedback = visit.feedback,
) {
    router.put(
        update(visit).url,
        {
            scheduled_at: visit.scheduled_at,
            user_id: visit.user_id,
            status,
            feedback: visitFeedback,
        },
        {
            preserveScroll: true,
            onSuccess: () => (visitToFinish.value = null),
        },
    );
}

function removeVisit(visit: DealVisit) {
    router.delete(destroy(visit).url, { preserveScroll: true });
}
</script>

<template>
    <section class="space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h3
                class="flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                <CalendarCheck class="size-3.5" />
                Visitas
            </h3>
            <Button
                v-if="can.manageSales && !isScheduling && !deal.closed_at"
                variant="outline"
                size="sm"
                @click="isScheduling = true"
            >
                <CalendarPlus class="size-4" />
                Agendar visita
            </Button>
        </div>

        <form
            v-if="isScheduling"
            class="grid gap-3 rounded-lg border bg-muted/40 p-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
            @submit.prevent="schedule"
        >
            <div class="grid gap-2">
                <Label for="visit_scheduled_at">Data e hora</Label>
                <Input
                    id="visit_scheduled_at"
                    v-model="form.scheduled_at"
                    type="datetime-local"
                    required
                />
                <InputError :message="form.errors.scheduled_at" />
            </div>
            <div v-if="users.length > 0" class="grid gap-2">
                <Label for="visit_user_id">Quem acompanha</Label>
                <Select v-model="form.user_id">
                    <SelectTrigger id="visit_user_id" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="NONE">A definir</SelectItem>
                        <SelectItem
                            v-for="user in users"
                            :key="user.id"
                            :value="String(user.id)"
                        >
                            {{ user.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="flex gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    @click="isScheduling = false"
                >
                    Cancelar
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Spinner v-if="form.processing" />
                    Agendar
                </Button>
            </div>
        </form>

        <p
            v-if="deal.visits.length === 0 && !isScheduling"
            class="rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
        >
            Nenhuma visita agendada.
        </p>

        <ul
            v-else-if="deal.visits.length > 0"
            class="divide-y rounded-lg border"
        >
            <li
                v-for="visit in deal.visits"
                :key="visit.id"
                class="space-y-2 p-3"
            >
                <div class="flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex items-center gap-2 text-sm font-medium">
                            {{ formatVisitTime(visit.scheduled_at) }}
                            <Badge
                                variant="outline"
                                :class="visitStatusBadgeClasses[visit.status]"
                            >
                                {{ visitStatusLabels[visit.status] }}
                            </Badge>
                        </p>
                        <p
                            v-if="visit.user"
                            class="text-xs text-muted-foreground"
                        >
                            Com {{ visit.user.name }}
                        </p>
                    </div>
                    <DropdownMenu v-if="can.manageSales">
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon-sm">
                                <MoreHorizontal class="size-4" />
                                <span class="sr-only">Ações da visita</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuItem @click="startFinishing(visit)">
                                <CalendarCheck class="size-4" />
                                {{
                                    visit.status === 'done'
                                        ? 'Editar como foi'
                                        : 'Marcar como realizada'
                                }}
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="visit.status === 'scheduled'"
                                @click="saveVisit(visit, 'canceled')"
                            >
                                <CalendarX class="size-4" />
                                Cancelar visita
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                @click="removeVisit(visit)"
                            >
                                <Trash2 class="size-4" />
                                Remover
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <p
                    v-if="visit.feedback && visitToFinish?.id !== visit.id"
                    class="rounded-md bg-muted/50 p-2 text-sm whitespace-pre-line"
                >
                    {{ visit.feedback }}
                </p>

                <form
                    v-if="visitToFinish?.id === visit.id"
                    class="grid gap-2"
                    @submit.prevent="saveVisit(visit, 'done', feedback)"
                >
                    <Label :for="`feedback_${visit.id}`">Como foi?</Label>
                    <textarea
                        :id="`feedback_${visit.id}`"
                        v-model="feedback"
                        rows="2"
                        placeholder="Ex.: gostou da casa, achou o quintal pequeno, vai conversar com a esposa…"
                        class="w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-base shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:text-sm dark:bg-input/30"
                    />
                    <div class="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="visitToFinish = null"
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" size="sm">Salvar</Button>
                    </div>
                </form>
            </li>
        </ul>
    </section>
</template>
