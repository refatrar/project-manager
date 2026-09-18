export type ScopeStatus = 'active' | 'inactive';

export type Scope = {
    id: number;
    name: string;
    description: string | null;
    status: ScopeStatus;
};

export type ScopeStatusOption = {
    value: ScopeStatus;
    label: string;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};
