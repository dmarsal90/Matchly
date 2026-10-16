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

export type ApplicationEventEntry = {
    id: number;
    type: 'status_changed' | 'interview' | 'note' | 'follow_up';
    status: string | null;
    round: number | null;
    note: string | null;
    occurred_at: string;
};

export type InterviewStar = {
    situation: string | null;
    task: string | null;
    action: string | null;
    result: string | null;
};

export type PracticeNote = { key?: string; text?: string };

export type PracticeAnswer = {
    answer: string;
    feedback: {
        strengths: PracticeNote[];
        improvements: PracticeNote[];
    } | null;
    feedback_source: 'ai' | 'rules' | null;
};

export type InterviewPrepData = {
    status: 'generating' | 'ready' | 'failed';
    source: 'ai' | 'rules' | null;
    content: {
        questions: {
            key: string;
            category: 'technical' | 'behavioral' | 'motivation';
            question: string;
            skill: string | null;
            star: InterviewStar | null;
        }[];
        gaps: { skill: string; required: boolean; answer: string }[];
        ask: string[];
    } | null;
    warnings: PackageWarning[];
    updated_at: string;
};

export type CompanyNoteEntry = {
    id: number;
    fact: string;
    source_url: string | null;
    origin: 'manual' | 'web';
    approved: boolean;
};
