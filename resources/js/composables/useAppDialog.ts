import { reactive } from 'vue';

export type DialogTone = 'default' | 'warning' | 'danger' | 'success';

type DialogOptions = {
    title?: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    tone?: DialogTone;
    input?: boolean;
    inputLabel?: string;
    inputPlaceholder?: string;
    inputRequired?: boolean;
};

type DialogResult = boolean | string | null;
let resolveCurrent: ((value: DialogResult) => void) | null = null;

export const appDialogState = reactive({
    open: false,
    title: '',
    message: '',
    confirmText: 'Confirmer',
    cancelText: 'Annuler',
    tone: 'default' as DialogTone,
    input: false,
    inputLabel: '',
    inputPlaceholder: '',
    inputRequired: false,
    inputValue: '',
    showCancel: true,
});

function openDialog(options: DialogOptions, showCancel = true): Promise<DialogResult> {
    if (resolveCurrent) resolveCurrent(false);
    Object.assign(appDialogState, {
        open: true,
        title: options.title ?? (options.tone === 'danger' ? 'Action sensible' : options.tone === 'warning' ? 'Attention' : 'Confirmation'),
        message: options.message,
        confirmText: options.confirmText ?? (showCancel ? 'Confirmer' : 'Compris'),
        cancelText: options.cancelText ?? 'Annuler',
        tone: options.tone ?? 'default',
        input: options.input ?? false,
        inputLabel: options.inputLabel ?? '',
        inputPlaceholder: options.inputPlaceholder ?? '',
        inputRequired: options.inputRequired ?? false,
        inputValue: '',
        showCancel,
    });
    return new Promise((resolve) => { resolveCurrent = resolve; });
}

export function resolveAppDialog(confirmed: boolean) {
    if (!resolveCurrent) return;
    const result = confirmed ? (appDialogState.input ? appDialogState.inputValue : true) : (appDialogState.input ? null : false);
    appDialogState.open = false;
    resolveCurrent(result);
    resolveCurrent = null;
}

export function appConfirm(message: string, options: Omit<DialogOptions, 'message'> = {}) {
    return openDialog({ ...options, message }) as Promise<boolean>;
}

export async function appAlert(message: string, options: Omit<DialogOptions, 'message'> = {}) {
    await openDialog({ ...options, message }, false);
}

export function appPrompt(message: string, options: Omit<DialogOptions, 'message' | 'input'> = {}) {
    return openDialog({ ...options, message, input: true }) as Promise<string | null>;
}
