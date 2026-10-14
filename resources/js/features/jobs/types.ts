export type JobSalary = {
    min: number | null;
    max: number | null;
    currency: string | null;
    period: 'hour' | 'day' | 'week' | 'month' | 'year' | null;
    text: string | null;
};

export type Job = {
    id: number;
    slug: string;
    title: string;
    company: string;
    company_logo_url: string | null;
    location: string | null;
    work_mode: string;
    remote_scope: string | null;
    allowed_regions: string[];
    employment_type: string | null;
    seniority: string | null;
    category: string | null;
    role: string | null;
    salary: JobSalary | null;
    tags: string[];
    posted_at: string;
    status: string;
    apply_url: string;
    source: { key: string; name: string; url: string };
    tracking_status: string | null;
    viewed: boolean;
    description?: string;
};

export type JobPage = {
    data: Job[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};

/** Mirrors JobController::echo(): the query-string state of the listing. */
export type JobFilters = {
    q: string | null;
    work_mode: string[];
    scope: string[];
    type: string[];
    role: string[];
    category: string[];
    seniority: string[];
    source: string[];
    tag: string[];
    posted: string | null;
    from: string | null;
    to: string | null;
    salary_min: number | null;
    currency: string | null;
    sort: string;
    group: string | null;
    per_page: number;
};

export type MultiFilterKey =
    | 'work_mode'
    | 'scope'
    | 'type'
    | 'role'
    | 'category'
    | 'seniority'
    | 'source'
    | 'tag';

export type JobOptions = {
    work_mode: string[];
    scope: string[];
    type: string[];
    role: string[];
    category: string[];
    seniority: string[];
    posted: string[];
    sort: string[];
    group: string[];
    per_page: number[];
    default_per_page: number;
    currencies: string[];
    source: { key: string; name: string }[];
    tag: string[];
};
