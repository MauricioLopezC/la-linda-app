import type { Auth } from '@/types/auth';
import type {
  Method,
  RouteFormDefinition,
  RouteQueryOptions,
} from '@/wayfinder';

declare module 'react' {
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface InputHTMLAttributes<T> {
    passwordrules?: string;
  }
}

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    sharedPageProps: {
      name: string;
      auth: Auth;
      sidebarOpen: boolean;
      [key: string]: unknown;
    };
  }
}

declare global {
  interface Function {
    form(options?: RouteQueryOptions): RouteFormDefinition<Method>;
  }
}
