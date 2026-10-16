import { index as applicationsIndex } from '@/routes/applications';
import { index as jobsIndex } from '@/routes/jobs';
import { trans, transChoice } from 'laravel-vue-i18n';
import type { AppNotification } from '../types';

export function notificationTitle(data: AppNotification['data']): string {
    if (data.type === 'follow_up') {
        return trans('follow_up.notification.title');
    }

    if (data.type === 'deadline') {
        return trans('deadline.notification.title');
    }

    if (data.type === 'radar') {
        return trans('radar.notification.title');
    }

    const key =
        data.frequency === 'immediate'
            ? 'alerts.notifications.job_alert'
            : 'alerts.notifications.job_alert_digest';

    return transChoice(key, data.count, { name: data.alert_name });
}

/** The second line: the jobs of an alert, or what a reminder is about. */
export function notificationSummary(data: AppNotification['data']): string {
    if (data.type === 'follow_up') {
        return trans('follow_up.notification.body', {
            company: data.company ?? '',
            title: data.title ?? '',
            days: String(data.days),
        });
    }

    if (data.type === 'deadline') {
        return transChoice('deadline.notification.body', data.days_left, {
            company: data.company ?? '',
            title: data.title ?? '',
            days: String(data.days_left),
        });
    }

    if (data.type === 'radar') {
        return transChoice('radar.notification.body', data.count, {
            company: data.company,
            count: String(data.count),
            titles: data.jobs.map((job) => job.title).join(' · '),
        });
    }

    return data.jobs.map((job) => job.title).join(' · ');
}

export function notificationHref(data: AppNotification['data']): string {
    if (data.type === 'radar') {
        return jobsIndex.url({ query: { q: data.company } });
    }

    return data.type === 'follow_up' || data.type === 'deadline'
        ? applicationsIndex.url()
        : jobsIndex.url({ query: data.filters });
}
