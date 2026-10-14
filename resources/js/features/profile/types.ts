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
