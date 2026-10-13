<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { LanguageOption, SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ options: LanguageOption[]; selected: string[] }>();
const emit = defineEmits<{ apply: [languages: string[]] }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.repositories);
const draft = ref([...props.selected]);
const open = ref(false);
watch(
    () => props.selected,
    (value) => {
        draft.value = [...value];
    },
);
function toggle(name: string, checked: boolean) {
    draft.value = checked ? [...new Set([...draft.value, name])] : draft.value.filter((value) => value !== name);
}
function commit(languages: string[]) {
    emit('apply', languages);
    open.value = false;
}
</script>

<template>
    <DropdownMenu v-model:open="open" @update:open="draft = [...selected]">
        <DropdownMenuTrigger as-child>
            <Button :variant="selected.length ? 'default' : 'outline'"
                >{{ lang.filter_language }}<span v-if="selected.length">({{ selected.length }})</span><ChevronDown class="size-4" aria-hidden="true"
            /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start" class="w-56">
            <div class="max-h-64 overflow-y-auto">
                <DropdownMenuCheckboxItem
                    v-for="option in options"
                    :key="option.name"
                    :checked="draft.includes(option.name)"
                    @update:checked="toggle(option.name, $event === true)"
                    @select.prevent
                >
                    {{ option.name }} ({{ option.count }})
                </DropdownMenuCheckboxItem>
                <p v-if="!options.length" class="p-3 text-sm text-muted-foreground">{{ lang.no_languages }}</p>
            </div>
            <DropdownMenuSeparator />
            <div class="flex justify-end gap-2 p-2">
                <Button variant="outline" @click="commit([])">{{ lang.filter_reset }}</Button>
                <Button @click="commit([...draft])">{{ lang.filter_ok }}</Button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
