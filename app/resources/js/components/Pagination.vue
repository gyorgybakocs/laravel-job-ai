<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { PaginationLink, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{ links: PaginationLink[] }>();
const page = usePage<SharedData>();
</script>

<template>
    <nav v-if="links.length > 3" :aria-label="page.props.lang.targets.pagination" class="mt-6 flex flex-wrap gap-1">
        <template v-for="(link, index) in links" :key="index">
            <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" as-child>
                <Link :href="link.url" :aria-current="link.active ? 'page' : undefined" preserve-scroll>
                    <span v-html="link.label" />
                </Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled><span v-html="link.label" /></Button>
        </template>
    </nav>
</template>
