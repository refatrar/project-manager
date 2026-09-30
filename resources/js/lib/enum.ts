export type EnumOption<T extends string = string> = {
    value: T;
    label: string;
};

/**
 * Display label for an enum value, taken from the server's `options()`
 * (RULES.md §3: labels are never re-derived in TypeScript). Falls back to
 * the raw value so an option missing from the payload stays visible.
 */
export function optionLabel<T extends string>(
    options: ReadonlyArray<EnumOption<T>> | undefined,
    value: T | null | undefined,
): string {
    if (value === null || value === undefined) {
        return '';
    }

    return options?.find((option) => option.value === value)?.label ?? value;
}
