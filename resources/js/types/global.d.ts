import type { route as routeFn } from 'ziggy-js';

declare global {
    // Ziggy: la directiva @routes de app.blade.php expone route() global.
    const route: typeof routeFn;
}

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
