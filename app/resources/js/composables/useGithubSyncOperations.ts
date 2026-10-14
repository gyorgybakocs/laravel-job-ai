import { usePoll } from '@inertiajs/vue3';
import { onMounted, watch, type Ref } from 'vue';

export function useGithubSyncOperations(hasActiveOperations: Ref<boolean>, interval: number): void {
    const { start, stop } = usePoll(
        interval,
        {
            only: ['users', 'orgs', 'hasActiveSyncOperations', 'flash'],
        },
        { autoStart: false },
    );

    onMounted(() => {
        if (hasActiveOperations.value) start();
    });
    watch(hasActiveOperations, (active) => {
        if (active) start();
        else stop();
    });
}
