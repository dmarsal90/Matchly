export type ResumePosition = {
    title: string;
    company: string | null;
    location: string | null;
    started_on: string | null;
    finished_on: string | null;
    bullets: string[];
};

export type Resume = {
    contact: {
        name: string;
        email: string | null;
        phone: string | null;
        location: string | null;
        links: { label: string; url: string }[];
    };
    headline: string | null;
    summary: string | null;
    experience: ResumePosition[];
    education: {
        school: string;
        degree: string | null;
        started_on: string | null;
        finished_on: string | null;
    }[];
    certifications: { name: string; authority: string | null }[];
    projects: {
        title: string;
        description: string | null;
        url: string | null;
    }[];
    skills: string[];
    languages: { code: string; level: string }[];
    keywords: string[];
};

export type PackageStatus = 'generating' | 'draft' | 'approved' | 'failed';

export type ContentSource = 'ai' | 'rules' | 'user';

export type PackageWarning = { key: string; params: Record<string, string> };

export type ApplicationPackage = {
    id: number;
    status: PackageStatus;
    language: string;
    tone: string;
    length: string;
    cover_letter: string | null;
    resume: Resume | null;
    master_resume: Resume | null;
    letter_source: ContentSource | null;
    resume_source: ContentSource | null;
    warnings: PackageWarning[];
    error: string | null;
    approved_at: string | null;
    created_at: string;
};

export type QuickApplyOptions = {
    ai_available: boolean;
    ai_consented: boolean;
    packages?: number;
    latest_package_id?: number | null;
};
