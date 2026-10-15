export type AlertFrequency = 'immediate' | 'daily' | 'weekly';

export type SavedAlert = {
    id: number;
    name: string;
    filters: Record<string, string>;
    frequency: AlertFrequency;
    email: boolean;
    active: boolean;
    matches: number;
    pending: number;
    last_notified_at: string | null;
    created_at: string;
};

export type JobAlertData = {
    type: 'job_alert';
    frequency: AlertFrequency;
    alert_id: number;
    alert_name: string;
    filters: Record<string, string>;
    count: number;
    jobs: {
        id: number;
        title: string;
        company: string;
        score: number | null;
    }[];
};

export type AppNotification = {
    id: string;
    data: JobAlertData;
    read_at: string | null;
    created_at: string | null;
};
