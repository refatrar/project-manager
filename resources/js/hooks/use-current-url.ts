import type { InertiaLinkProps } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import { toUrl } from '@/lib/utils';

export type IsCurrentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
    startsWith?: boolean,
) => boolean;

export type IsCurrentOrParentUrlFn = (
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl?: string,
) => boolean;

export type MostSpecificCurrentIndexFn = (
    hrefs: ReadonlyArray<NonNullable<InertiaLinkProps['href']>>,
) => number;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
    mostSpecificCurrentIndex: MostSpecificCurrentIndexFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

export function useCurrentUrl(): UseCurrentUrlReturn {
    const page = usePage();
    const currentUrlPath = new URL(
        page.url,
        typeof window !== 'undefined'
            ? window.location.origin
            : 'http://localhost',
    ).pathname;

    const pathOf = (
        url: NonNullable<InertiaLinkProps['href']>,
    ): string | null => {
        const urlString = toUrl(url);

        if (!urlString.startsWith('http')) {
            return urlString;
        }

        try {
            return new URL(urlString).pathname;
        } catch {
            return null;
        }
    };

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
        startsWith: boolean = false,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    };

    // Matches whole path segments, so `/timesheet` is a parent of
    // `/timesheet/2` but not of `/timesheet-approvals`.
    const isCurrentOrParentUrl: IsCurrentOrParentUrlFn = (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => {
        const path = pathOf(urlToCheck);

        if (path === null) {
            return false;
        }

        const urlToCompare = currentUrl ?? currentUrlPath;
        const prefix = path.endsWith('/') ? path : `${path}/`;

        return urlToCompare === path || urlToCompare.startsWith(prefix);
    };

    // The item that best matches the current page: the longest href that is
    // the current URL or one of its parents. Returns its index, or -1, so a
    // nav highlights at most one item. An href that is the parent of another
    // item in the same nav (`/admin` over `/admin/users`) is a section root
    // and only matches exactly, so it isn't lit on unlisted pages like
    // `/admin/profile`.
    const mostSpecificCurrentIndex: MostSpecificCurrentIndexFn = (hrefs) => {
        const paths = hrefs.map(pathOf);
        let bestIndex = -1;
        let bestLength = -1;

        hrefs.forEach((href, index) => {
            const path = paths[index];

            if (path === null || path.length <= bestLength) {
                return;
            }

            const isSectionRoot = paths.some(
                (other, otherIndex) =>
                    otherIndex !== index &&
                    other !== null &&
                    other !== path &&
                    isCurrentOrParentUrl(href, other),
            );
            const matches = isSectionRoot
                ? isCurrentUrl(href)
                : isCurrentOrParentUrl(href);

            if (matches) {
                bestIndex = index;
                bestLength = path.length;
            }
        });

        return bestIndex;
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        mostSpecificCurrentIndex,
        whenCurrentUrl,
    };
}
