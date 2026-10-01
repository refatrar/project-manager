import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect x="3" y="4" width="4.5" height="16" rx="1.5" />
            <rect x="9.75" y="4" width="4.5" height="10" rx="1.5" />
            <rect x="16.5" y="4" width="4.5" height="13" rx="1.5" />
        </svg>
    );
}
