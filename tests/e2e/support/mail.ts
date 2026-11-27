import { php } from './artisan';

/**
 * The last email sent to an address (kept by the mail log of the application), with the links it contains.
 */
export function lastMailTo(email: string): { subject: string; body: string; links: string[] } | null {
    const mail = php<{ subject: string; body: string } | null>(`
        $log = \\Tapp\\FilamentMailLog\\Models\\MailLog::query()
            ->where('to', 'like', '%' . ${JSON.stringify(email)} . '%')
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
 * The first link of the last email sent to an address whose URL matches the pattern.
 */
export function linkInLastMailTo(email: string, pattern: RegExp): string {
    const link = lastMailTo(email)?.links.find((url) => pattern.test(url));

    if (!link) {
        throw new Error(`No link matching ${pattern} in the last email sent to ${email}.`);
    }

    return link;
}
