<script setup lang="ts">
import { MessageCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { lastSentLabel, recordWhatsAppMessage } from '@/lib/whatsapp';
import type { WhatsAppMessage } from '@/types';

withDefaults(
    defineProps<{
        message: WhatsAppMessage;
        /** Where the app records that the message was opened. */
        recordUrl: string;
        /** Whether to show who sent the last message and when. */
        showLastSent?: boolean;
    }>(),
    { showLastSent: true },
);
</script>

<template>
    <div class="flex flex-col items-start gap-1">
        <Button v-if="message.url" variant="outline" size="sm" as-child>
            <a
                :href="message.url"
                target="_blank"
                rel="noopener"
                @click="recordWhatsAppMessage(recordUrl)"
            >
                <MessageCircle class="size-4" />
                {{ message.action_label }}
            </a>
        </Button>
        <template v-else>
            <Button variant="outline" size="sm" disabled>
                <MessageCircle class="size-4" />
                {{ message.action_label }}
            </Button>
            <p class="text-xs text-muted-foreground">
                Cadastre o celular do {{ message.recipient }} para enviar pelo
                WhatsApp.
            </p>
        </template>
        <p
            v-if="showLastSent && message.last_sent"
            class="text-xs text-muted-foreground"
        >
            Último envio: {{ lastSentLabel(message) }}
        </p>
    </div>
</template>
