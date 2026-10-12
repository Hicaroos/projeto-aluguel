<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserRound, X } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/owner-selection';

const page = usePage();

const selected = computed(() => page.props.ownerSelector?.selected ?? null);

function showEveryOwner() {
    router.put(update().url, { owner_id: null });
}
</script>

<template>
    <div
        v-if="selected"
        class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-primary/20 bg-primary/10 px-4 py-1.5 text-center text-sm"
        role="status"
    >
        <span class="flex items-center gap-2">
            <UserRound class="size-4 shrink-0 text-primary" />
            <span>
                Vendo apenas o que é de
                <strong class="font-semibold">{{ selected.name }}</strong
                >.
                <span class="text-muted-foreground">
                    Funil, interessados e vendas continuam mostrando tudo.
                </span>
            </span>
        </span>
        <Button
            variant="ghost"
            size="sm"
            class="h-7 text-primary hover:bg-primary/15 hover:text-primary"
            @click="showEveryOwner"
        >
            <X class="size-4" />
            Ver todos
        </Button>
    </div>
</template>
