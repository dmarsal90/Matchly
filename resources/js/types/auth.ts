/** Mirrors HandleInertiaRequests::user(): only what every page needs. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    email_verified_at: string | null;
    has_password: boolean;
    is_admin: boolean;
    locale: string | null;
    two_factor_enabled: boolean;
};

export type Auth = {
    user: User | null;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
