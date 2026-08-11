import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Monitor, Image as ImageIcon, FileText, Smile, ReceiptText, CreditCard, Ticket, Megaphone } from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { MachineSwitcher } from '@/components/ui/machine-switcher';
import { dashboard } from '@/routes';
import machines from '@/routes/machines';
import paperSizes from '@/routes/paper-sizes';
import templates from '@/routes/templates';
import stickers from '@/routes/stickers';
import type { NavItem } from '@/types';

import { Users } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Transactions',
        href: '/transactions',
        icon: ReceiptText,
    },
    {
        title: 'Gallery',
        href: '/gallery',
        icon: ImageIcon,
    },
    {
        title: 'Users',
        href: '/users',
        icon: Users,
    },
    {
        title: 'Templates',
        href: templates.index(),
        icon: BookOpen,
    },
    {
        title: 'Machines',
        href: machines.index(),
        icon: Monitor,
    },
    {
        title: 'Stickers',
        href: stickers.index(),
        icon: Smile,
    },
    {
        title: 'Iklan / Banners',
        href: '/iklans',
        icon: Megaphone,
    },
    {
        title: 'Paper Sizes',
        href: paperSizes.index(),
        icon: FileText,
    },
    {
        title: 'Vouchers',
        href: '/vouchers',
        icon: Ticket,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const isMitra = auth.user.role === 'mitra';
    
    // Mitra only sees Dashboard and Transactions
    const visibleItems = isMitra 
        ? mainNavItems.filter(item => item.title === 'Transactions' || item.title === 'Dashboard') 
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <MachineSwitcher />
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={visibleItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
