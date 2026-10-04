import type { ComponentPropsWithoutRef } from 'react';
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export function NavFooter({
  items,
  className,
  ...props
}: ComponentPropsWithoutRef<typeof SidebarGroup> & {
  items: NavItem[];
}) {
  const { state } = useSidebar();
  const isCollapsed = state === 'collapsed';

  return (
    <SidebarGroup
      {...props}
      className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}
    >
      <SidebarGroupContent>
        <SidebarMenu>
          {items.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton
                asChild
                className="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
              >
                <a
                  href={toUrl(item.href)}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {item.icon && <item.icon className="h-5 w-5" />}
                  {!isCollapsed && <span>{item.title}</span>}
                </a>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}
