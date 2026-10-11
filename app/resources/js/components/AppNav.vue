<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuLink,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { UserRound } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const lang = computed(() => page.props.lang.nav);
const user = computed(() => page.props.auth.user);
</script>

<template>
    <header class="border-b border-gray-100 bg-white dark:border-gray-800 dark:bg-gray-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex h-16 items-center justify-end">
                <NavigationMenu class="flex-none">
                    <NavigationMenuList class="gap-4">
                        <template v-if="user">
                            <NavigationMenuItem>
                                <NavigationMenuLink as-child :active="page.url.startsWith('/settings')" :class="navigationMenuTriggerStyle()">
                                    <Link :href="route('profile.edit')" aria-label="Profile">
                                        <UserRound class="h-6 w-6" aria-hidden="true" />
                                    </Link>
                                </NavigationMenuLink>
                            </NavigationMenuItem>
                            <NavigationMenuItem>
                                <Button variant="ghost" as-child>
                                    <Link :href="route('logout')" method="post" as="button">{{ lang.logout }}</Link>
                                </Button>
                            </NavigationMenuItem>
                        </template>
                        <template v-else>
                            <NavigationMenuItem>
                                <NavigationMenuLink as-child :active="page.url === '/login'" :class="navigationMenuTriggerStyle()">
                                    <Link :href="route('login')">{{ lang.login }}</Link>
                                </NavigationMenuLink>
                            </NavigationMenuItem>
                            <NavigationMenuItem>
                                <NavigationMenuLink as-child :active="page.url === '/register'" :class="navigationMenuTriggerStyle()">
                                    <Link :href="route('register')">{{ lang.register }}</Link>
                                </NavigationMenuLink>
                            </NavigationMenuItem>
                        </template>
                    </NavigationMenuList>
                </NavigationMenu>
            </div>
        </div>
    </header>
</template>
