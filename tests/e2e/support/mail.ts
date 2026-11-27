import { php } from './artisan';

/**
 * The last email sent to an address (kept by the mail log of the application), with the links it contains; only among
 * the emails with the subject when given one, since other emails may reach the address meanwhile (e.g. the
 * announcement of a campaign created by another test).
 */
export function lastMailTo(email: string, subject?: string): { subject: string; body: string; links: string[] } | null {
    const mail = php<{ subject: string; body: string } | null>(`
        $log = \\Tapp\\FilamentMailLog\\Models\\MailLog::query()
            ->where('to', 'like', '%' . ${JSON.stringify(email)} . '%')
            ${subject === undefined ? '' : `->where('subject', ${JSON.stringify(subject)})`}
            ->latest('id')
            ->first();

        return $log ? ['subject' => $log->subject, 'body' => (string) $log->body] : null;
    `);

    if (mail === null) {
        return null;
    }

    const links = [...mail.body.matchAll(/href="([^"]+)"/g)].map((match) => match[1].replaceAll('&amp;', '&'));

    return { ...mail, links: [...new Set(links)] };
}

/**
 * The link matching the pattern in the newest email sent to an address that has one (see `linkInMailsTo()`).
 */
export function linkInLastMailTo(email: string, pattern: RegExp): string {
    const link = linkInMailsTo(email, pattern);

    if (!link) {
        throw new Error(`No link matching ${pattern} in the emails sent to ${email}.`);
    }

    return link;
}

/**
 * The link matching the pattern in the newest email sent to an address that has one, or `null`: other emails may
 * have reached the address since (e.g. the announcement of a campaign created by another test).
 */
export function linkInMailsTo(email: string, pattern: RegExp): string | null {
    const bodies = php<string[]>(`
        return \\Tapp\\FilamentMailLog\\Models\\MailLog::query()
            ->where('to', 'like', '%' . ${JSON.stringify(email)} . '%')
            ->latest('id')
            ->limit(20)
            ->pluck('body')
            ->map(fn ($body) => (string) $body)
            ->all();
    `);

    for (const body of bodies) {
        const link = [...body.matchAll(/href="([^"]+)"/g)].map((match) => match[1].replaceAll('&amp;', '&')).find((url) => pattern.test(url));

        if (link) {
            return link;
        }
    }

    return null;
}
