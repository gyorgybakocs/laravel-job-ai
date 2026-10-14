<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDate } from '@/composables/useDate';
import type { SharedData, UnlinkedGithubSyncOperation } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ operations: UnlinkedGithubSyncOperation[] }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.targets);
</script>

<template>
    <Card v-if="operations.length" class="mt-6">
        <CardHeader><CardTitle>{{ lang.target_requests }}</CardTitle></CardHeader>
        <CardContent>
            <ul class="space-y-3" aria-live="polite">
                <li v-for="operation in operations" :key="operation.id" class="text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span>{{ operation.name }} · {{ operation.type === 'org' ? lang.type_org : lang.type_user }}</span>
                        <span>{{ lang[`operation_${operation.status}`] }}</span>
                    </div>
                    <p class="text-muted-foreground">{{ formatDate(operation.created_at, lang.unknown_value, true) }}</p>
                    <p v-if="operation.error" class="text-destructive">{{ operation.error }}</p>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
