<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ githubUsername: string | null; status?: string | null }>();
const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.github);
const common = computed(() => page.props.lang.common);
const form = useForm({ github_username: props.githubUsername ?? '', github_token: '' });

function submit() {
    form.patch(route('github.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('github_token'),
    });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="github_username">{{ lang.username_label }}</Label>
            <Input id="github_username" v-model="form.github_username" maxlength="39" autocomplete="username" />
            <InputError :message="form.errors.github_username" />
        </div>
        <div class="grid gap-2">
            <Label for="github_token">{{ lang.token_label }}</Label>
            <Input
                id="github_token"
                v-model="form.github_token"
                type="password"
                maxlength="255"
                :placeholder="lang.token_placeholder"
                autocomplete="off"
            />
            <InputError :message="form.errors.github_token" />
        </div>
        <div class="flex items-center gap-4">
            <Button type="submit" :disabled="form.processing">{{ common.save }}</Button>
            <p v-if="form.recentlySuccessful && status" role="status" class="text-sm text-muted-foreground">{{ status }}</p>
        </div>
    </form>
</template>
