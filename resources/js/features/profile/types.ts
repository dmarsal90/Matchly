export type LanguageSkill = { code: string; level: string };

export type CareerProfile = {
    headline: string | null;
    phone: string | null;
    country: string | null;
    city: string | null;
    github_username: string | null;
    linkedin_url: string | null;
    portfolio_url: string | null;
    languages: LanguageSkill[];
    salary_min: number | null;
    salary_currency: string | null;
    salary_period: string | null;
    work_modes: string[];
    employment_types: string[];
};

export type CvDocument = {
    id: number;
    name: string;
    size: number;
    type: 'pdf' | 'docx';
    status: 'uploaded' | 'extracted' | 'failed';
    is_primary: boolean;
    error: string | null;
    uploaded_at: string;
};

export type ProfileOptions = {
    countries: Record<string, string>;
    currencies: string[];
    languageLevels: string[];
    salaryPeriods: string[];
    workModes: string[];
    employmentTypes: string[];
    maxCvKilobytes: number;
};

export type SkillSource = 'declared' | 'cv' | 'github' | 'linkedin';

export type DeclaredSkill = {
    name: string;
    level: number;
    years: number | null;
    last_used_year: number | null;
};

export type QuestionnaireStep =
    'role' | 'skills' | 'languages' | 'location' | 'conditions';

export type QuestionnaireAnswers = {
    target_roles: string[];
    seniority: string | null;
    years_experience: number | null;
    skills: DeclaredSkill[];
    languages: LanguageSkill[];
    regions: string[];
    utc_offset_min: number | null;
    utc_offset_max: number | null;
    work_modes: string[];
    employment_types: string[];
    salary_min: number | null;
    salary_currency: string | null;
    salary_period: string | null;
    dealbreakers: string[];
    dealbreaker_notes: string | null;
};

export type QuestionnaireOptions = {
    roles: string[];
    seniorities: string[];
    skillSuggestions: string[];
    maxSkills: number;
    currentYear: number;
    languageLevels: string[];
    regions: string[];
    workModes: string[];
    employmentTypes: string[];
    currencies: string[];
    salaryPeriods: string[];
    dealbreakers: string[];
};

export type MergedSkill = {
    skill: string;
    name: string;
    sources: SkillSource[];
    level: number | null;
    declared_level: number | null;
    evidence_level: number | null;
    years: number | null;
    last_used_year: number | null;
    confidence: number;
    level_gap: number | null;
    has_evidence: boolean;
};

export type ImportSummary = {
    id: number;
    source: SkillSource;
    status: 'processing' | 'pending_review' | 'confirmed' | 'failed';
    reference: string | null;
    error: string | null;
    skills_count: number;
    created_at: string;
    confirmed_at: string | null;
};

export type SourceState = {
    skills_count: number;
    confirmed: ImportSummary | null;
    pending: ImportSummary | null;
    consented: boolean;
};

export type SearchProfile = {
    roles: string[];
    technologies: string[];
    technology_names: string[];
    seniorities: string[];
    work_modes: string[];
    employment_types: string[];
    salary_min: number | null;
    salary_currency: string | null;
    edited: boolean;
    listing_query: Record<string, string>;
};

export type SkillsOptions = {
    roles: string[];
    seniorities: string[];
    workModes: string[];
    employmentTypes: string[];
    currencies: string[];
    skillSuggestions: string[];
    linkedinMaxKilobytes: number;
    linkedinExportUrl: string;
};

export type GithubState = {
    linked_username: string | null;
    suggested_username: string | null;
    oauth_available: boolean;
};

export type ImportedSkill = {
    skill: string;
    name: string;
    level: number | null;
    years: number | null;
    last_used_year: number | null;
    evidence: {
        repository_count?: number;
        repositories?: string[];
        signals?: string[];
        listed?: boolean;
        positions?: string[];
        mentions?: number;
    };
};

export type ImportReview = {
    id: number;
    source: Exclude<SkillSource, 'declared'>;
    reference: string | null;
    created_at: string;
    data: {
        skills?: ImportedSkill[];
        profile?: {
            headline: string | null;
            summary: string | null;
            industry: string | null;
            location: string | null;
            websites: string[];
        } | null;
        positions?: {
            title: string;
            company: string | null;
            description: string | null;
            location: string | null;
            started_on: string | null;
            finished_on: string | null;
        }[];
        education?: {
            school: string;
            degree: string | null;
            notes: string | null;
            started_on: string | null;
            finished_on: string | null;
        }[];
        certifications?: {
            name: string;
            authority: string | null;
            url: string | null;
            started_on: string | null;
            finished_on: string | null;
        }[];
        projects?: {
            title: string;
            description: string | null;
            url: string | null;
            started_on: string | null;
            finished_on: string | null;
        }[];
        languages?: {
            name: string;
            code: string | null;
            level: string | null;
        }[];
        repositories?: {
            name: string;
            url: string;
            description: string | null;
            languages: Record<string, number>;
            topics: string[];
            stars: number;
            pushed_at: string;
        }[];
        files?: string[];
        skipped_files?: number;
        cv?: { id: number; name: string };
    };
};
