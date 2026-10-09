import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { Auth } from '@/types';

/**
 * What the signed in user may do, to show only the actions their role allows.
 * The server enforces the same permissions on every request.
 */
export function usePermissions(): ComputedRef<Auth['can']> {
    const page = usePage();

    return computed(() => page.props.auth.can);
}
