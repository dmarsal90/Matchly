import { index as applicationsIndex } from '@/routes/applications';
import { index as jobsIndex } from '@/routes/jobs';
import { trans, transChoice } from 'laravel-vue-i18n';
import type { AppNotification } from '../types';

export function notificationTitle(data: AppNotification['data']): string {
    if (data.type === 'follow_up') {
        return trans('follow_up.notification.title');
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

    return data.jobs.map((job) => job.title).join(' · ');
}

export function notificationHref(data: AppNotification['data']): string {
    return data.type === 'follow_up'
        ? applicationsIndex.url()
        : jobsIndex.url({ query: data.filters });
}
