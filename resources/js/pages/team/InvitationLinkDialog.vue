<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Copy, Link2, MessageCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type { InvitationLink } from '@/types';

const props = defineProps<{
    invitation: InvitationLink | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const isCopied = ref(false);

/** Message sent along with the link, e.g. on WhatsApp. */
const message = computed(() => {
    if (!props.invitation) {
        return '';
    }

    const agency = page.props.auth.user.account?.name ?? page.props.name;

    return [
        `Olá, ${props.invitation.name}!`,
        `Você foi convidado para a equipe da ${agency} no ${page.props.name}.`,
        `Crie sua senha pelo link abaixo (válido por ${props.invitation.expires_in_days} dias):`,
        props.invitation.url,
    ].join('\n');
});

const whatsAppUrl = computed(
    () => `https://wa.me/?text=${encodeURIComponent(message.value)}`,
);

async function copyLink() {
    if (!props.invitation) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.invitation.url);
        isCopied.value = true;
        setTimeout(() => (isCopied.value = false), 2000);
    } catch {
        toast.error('Não foi possível copiar. Selecione o link e copie.');
    }
}
</script>

<template>
    <Dialog
        :open="!!invitation"
        @update:open="(open) => !open && emit('close')"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader class="flex-row items-center gap-3 text-left">
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"
                >
                    <Link2 class="size-5" />
                </div>
                <div class="space-y-1">
                    <DialogTitle>Link de convite</DialogTitle>
                    <DialogDescription>
                        Envie para {{ invitation?.name }}. Pelo link a pessoa
                        cria a própria senha e já entra no sistema. Vale por
                        {{ invitation?.expires_in_days }} dias.
                    </DialogDescription>
                </div>
            </DialogHeader>

            <Input
                :model-value="invitation?.url ?? ''"
                readonly
                aria-label="Link de convite"
                class="font-mono text-xs"
                @focus="($event.target as HTMLInputElement).select()"
            />

            <DialogFooter class="gap-2 sm:justify-between">
                <Button variant="outline" @click="copyLink">
                    <Check v-if="isCopied" class="size-4" />
                    <Copy v-else class="size-4" />
                    {{ isCopied ? 'Copiado' : 'Copiar link' }}
                </Button>
                <Button as-child>
                    <a :href="whatsAppUrl" target="_blank" rel="noopener">
                        <MessageCircle class="size-4" />
                        Enviar pelo WhatsApp
                    </a>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
