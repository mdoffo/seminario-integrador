import type { HTMLAttributes } from 'react';

interface Props extends HTMLAttributes<HTMLParagraphElement> {
    message?: string | null;
}

export default function InputError({ message, className = '', ...props }: Props) {
    return message ? (
        <p
            {...props}
            className={'font-mono text-xs text-atalaya-crimson ' + className}
        >
            {message}
        </p>
    ) : null;
}
