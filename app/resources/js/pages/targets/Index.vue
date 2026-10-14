<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TargetList from '@/components/TargetList.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useGithubSyncOperations } from '@/composables/useGithubSyncOperations';
import AppLayout from '@/layouts/AppLayout.vue';
import type { GithubTarget, Paginator, SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    users: Paginator<GithubTarget>;
    orgs: Paginator<GithubTarget>;
    hasActiveSyncOperations: boolean;
    syncPollInterval: number;
}>();
useGithubSyncOperations(
    computed(() => props.hasActiveSyncOperations),
    props.syncPollInterval,
);

const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.targets);
const common = computed(() => page.props.lang.common);
const targetResult = computed(() => page.props.flash.targetResult);
const showModal = ref(false);
const form = useForm({ name: '', type: 'user' as 'user' | 'org' });

function submit() {
    form.post(route('targets.store'), {
        onSuccess: () => {
            showModal.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head :title="lang.add_target" />
    <AppLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-end">
                <Button @click="showModal = true">{{ lang.add_target }}</Button>
            </div>
            <p v-if="targetResult" role="status" class="rounded-md border bg-background px-4 py-3 text-sm">
                {{ targetResult }}
            </p>
            <section class="mb-10 mt-6">
                <h2 class="mb-3 text-sm font-semibold">{{ lang.users }}</h2>
                <TargetList :paginator="users" :empty-message="lang.no_users" />
            </section>
            <section>
                <h2 class="mb-3 text-sm font-semibold">{{ lang.organizations }}</h2>
                <TargetList :paginator="orgs" :empty-message="lang.no_orgs" />
            </section>
        </div>
        <Dialog v-model:open="showModal">
            <DialogContent :aria-describedby="undefined" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ lang.modal_title }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="target-name">{{ lang.username_label }}</Label>
                        <Input id="target-name" v-model="form.name" maxlength="39" required :placeholder="lang.username_placeholder" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="target-type">{{ lang.type_label }}</Label>
                        <select id="target-type" v-model="form.type" class="h-10 rounded-md border border-input bg-background px-3 text-sm">
                            <option value="user">{{ lang.type_user }}</option>
                            <option value="org">{{ lang.type_org }}</option>
                        </select>
                        <InputError :message="form.errors.type" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showModal = false">{{ common.cancel }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ common.add }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
