export type PermissionOption = {
    id: number;
    name: string;
    label: string | null;
    module: string | null;
    is_built_in: boolean;
};

export type AdminRole = {
    id: number;
    name: string;
    slug: string | null;
    description: string | null;
    is_system: boolean;
    admins_count: number;
    permission_ids: number[];
};

export type RoleOption = {
    id: number;
    name: string;
};

export type AdminAccount = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};
