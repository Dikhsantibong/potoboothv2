import type { Auth } from './auth';

export type * from './auth';
export type * from './navigation';
export type * from './ui';

export type SharedData = {
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};
