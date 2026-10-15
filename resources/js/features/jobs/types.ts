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
    match: JobMatchSummary | null;
    description?: string;
};

export type MatchLevel = 'high' | 'medium' | 'low';

export type JobMatchSummary = {
    score: number;
    level: MatchLevel;
    eligible: boolean;
};

export type MatchMessage = { key: string; params: Record<string, string> };

export type MatchCategory =
    | 'skills'
    | 'experience'
    | 'role'
    | 'location'
    | 'languages'
    | 'salary'
    | 'education';

export type JobMatch = JobMatchSummary & {
    ineligible_reasons: MatchMessage[];
    breakdown: Record<
        MatchCategory,
        { points: number; max: number; ratio: number; known: boolean }
    >;
    matched_skills: {
        skill: string;
        name: string;
        required: boolean;
        evidenced: boolean;
        level: number | null;
    }[];
    missing_skills: { skill: string; name: string; required: boolean }[];
    suggestions: MatchMessage[];
    computed_at: string;
};

export type JobRequirements = {
    source: 'rules' | 'ai';
    required_skills: {
        skill: string;
        name: string;
        min_years: number | null;
    }[];
    nice_to_have_skills: { skill: string; name: string }[];
    min_years_experience: number | null;
    seniority: string | null;
    languages: { code: string; level: string | null; required: boolean }[];
    education: { degree_required: boolean; field: string | null };
    certifications: string[];
    location: {
        exclusive: boolean;
        countries: string[];
        regions: string[];
        utc_offset_min: number | null;
        utc_offset_max: number | null;
    };
    asks_for: Record<
        | 'cover_letter'
        | 'github'
        | 'portfolio'
        | 'salary_expectations'
        | 'additional_questions',
        boolean
    >;
    language: string | null;
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
    tab: 'recommended' | null;
    min_score: number | null;
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
