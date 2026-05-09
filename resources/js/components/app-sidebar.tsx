import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Folder, LayoutGrid, Package, ReceiptText, ShoppingCart, Users, Warehouse } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const role = auth.user.role;
    const canViewOperationalModules = role === 'ADMIN' || role === 'SALES' || role === 'AUDITOR';
    const canViewProducts = role === 'ADMIN' || role === 'AUDITOR';
    const canViewCogs = role === 'ADMIN' || role === 'AUDITOR';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
        ...(canViewProducts
            ? [
                  {
                      title: 'Products',
                      url: '/products',
                      icon: Package,
                  },
                  {
                      title: 'Users',
                      url: '/users',
                      icon: Users,
                  },
                  {
                      title: 'Warehouses',
                      url: '/warehouses',
                      icon: Warehouse,
                  },
              ]
            : []),
        ...(canViewOperationalModules
            ? [
                  {
                      title: 'Purchases',
                      url: '/purchases',
                      icon: ReceiptText,
                  },
                  {
                      title: 'Sales',
                      url: '/sales',
                      icon: ShoppingCart,
                  },
                  ...(canViewCogs
                      ? [
                            {
                                title: 'COGS',
                                url: '/cogs',
                                icon: BarChart3,
                            },
                        ]
                      : []),
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" /> */}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
