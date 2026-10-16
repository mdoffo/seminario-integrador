import { forwardRef, useEffect, useImperativeHandle, useRef, type InputHTMLAttributes } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
    isFocused?: boolean;
}

/** Lo que expone el ref: solo focus(), igual que antes de tipar. */
export interface TextInputHandle {
    focus: () => void;
}

export default forwardRef<TextInputHandle, Props>(function TextInput(
    { type = 'text', className = '', isFocused = false, ...props },
    ref,
) {
    const localRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => localRef.current?.focus(),
    }));

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    return (
        <input
            {...props}
            type={type}
            className={
                'rounded-none border-atalaya-border bg-atalaya-canvas font-mono text-xs text-white placeholder-atalaya-text-dim focus:border-atalaya-cyan focus:ring-1 focus:ring-atalaya-cyan ' +
                className
            }
            ref={localRef}
        />
    );
});
