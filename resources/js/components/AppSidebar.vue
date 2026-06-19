<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BarChart3, Map, Settings2, Users } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Events',
        href: '/events',
        icon: Settings2,
    },
    {
        title: 'Events Visual 1',
        href: '/events-visual-1',
        icon: BarChart3,
    },
    {
        title: 'Events Visual 2',
        href: '/events-visual-2',
        icon: Map,
    },
    {
        title: 'Attendees',
        href: '/attendees',
        icon: Users,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link href="/events">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
