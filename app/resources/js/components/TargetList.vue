<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { formatDate } from '@/composables/useDate';
import type { GithubTarget, Paginator, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { ChevronDown, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

defineProps<{ paginator: Paginator<GithubTarget>; emptyMessage: string }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.targets);
const openId = ref<number | null>(null);
const processing = ref<number | null>(null);

function isSyncActive(target: GithubTarget): boolean {
    return target.sync_operation?.status === 'queued' || target.sync_operation?.status === 'running';
}

function updateOpen(id: number, open: boolean) {
    openId.value = open ? id : null;
}
function syncTarget(id: number) {
    processing.value = id;
    router.post(
        route('targets.sync', { target: id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
}
function deleteTarget(id: number) {
    processing.value = id;
    router.delete(route('targets.destroy', { target: id }), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = null;
        },
    });
}
</script>

<template>
    <p v-if="paginator.data.length === 0" class="text-sm text-muted-foreground">{{ emptyMessage }}</p>
    <template v-else>
        <div class="space-y-2">
            <Collapsible
                v-for="target in paginator.data"
                :key="target.id"
                :open="openId === target.id"
                @update:open="updateOpen(target.id, $event)"
                class="rounded-md border bg-background"
            >
                <div class="flex flex-wrap items-center justify-between gap-2 p-4">
                    <CollapsibleTrigger as-child>
                        <Button variant="ghost" class="h-auto min-w-0 flex-1 justify-start whitespace-normal text-left">
                            <ChevronDown class="mr-2 size-4 shrink-0" aria-hidden="true" />
                            <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span>{{ target.name }}</span>
                                <span class="text-xs text-muted-foreground">{{
                                    target.sync_operation ? lang[`operation_${target.sync_operation.status}`] : lang[`status_${target.status}`]
                                }}</span>
                                <span v-if="target.last_synced_at" class="text-xs text-muted-foreground"
                                    >{{ lang.last_synced }}: {{ formatDate(target.last_synced_at, lang.unknown_value, true) }}</span
                                >
                            </span>
                        </Button>
                    </CollapsibleTrigger>
                    <div class="flex shrink-0 gap-2">
                        <Button :disabled="processing !== null || isSyncActive(target)" @click="syncTarget(target.id)">{{ lang.sync_now }}</Button>
                        <Button
                            size="icon"
                            variant="destructive"
                            :disabled="processing !== null"
                            :aria-label="lang.delete_target.replace(':target', target.name)"
                            @click="deleteTarget(target.id)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </div>
                <p v-if="target.last_error" role="alert" class="px-4 pb-3 text-sm text-destructive">{{ target.last_error }}</p>
                <CollapsibleContent class="border-t p-4">
                    <p v-if="target.repositories.length === 0" class="text-sm text-muted-foreground">{{ lang.no_repositories }}</p>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="pb-2">{{ lang.col_name }}</th>
                                    <th class="pb-2">{{ lang.col_language }}</th>
                                    <th class="pb-2">{{ lang.col_stars }}</th>
                                    <th class="pb-2">{{ lang.col_open_issues }}</th>
                                    <th class="pb-2">{{ lang.col_updated }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="repo in target.repositories" :key="repo.id" class="border-b">
                                    <td class="py-2">
                                        <a :href="repo.url" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{
                                            repo.name
                                        }}</a>
                                    </td>
                                    <td class="py-2">{{ repo.language ?? lang.unknown_value }}</td>
                                    <td class="py-2">{{ repo.stars }}</td>
                                    <td class="py-2">{{ repo.open_issues }}</td>
                                    <td class="py-2">{{ formatDate(repo.github_updated_at, lang.unknown_value) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CollapsibleContent>
            </Collapsible>
        </div>
        <Pagination :links="paginator.links" />
    </template>
</template>
