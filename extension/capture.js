/*
 * Runs in the page and returns the job posting it describes, read only from
 * the page's public structured data (schema.org JobPosting in JSON-LD). It does
 * not scrape the visible page, so nothing from behind a login is read. Returns
 * null when the page has no such posting.
 */
(function () {
    function postings(node, found) {
        if (Array.isArray(node)) {
            node.forEach((item) => postings(item, found));
        } else if (node && typeof node === 'object') {
            const type = [].concat(node['@type'] || []);
            if (type.includes('JobPosting')) {
                found.push(node);
            }
            postings(node['@graph'], found);
        }

        return found;
    }

    function text(value) {
        return typeof value === 'string' ? value.trim() : '';
    }

    function place(location) {
        const first = [].concat(location || [])[0] || {};
        const address = first.address || {};
        const parts = [
            address.addressLocality,
            address.addressRegion,
            address.addressCountry &&
                (address.addressCountry.name || address.addressCountry),
        ].filter((part) => typeof part === 'string' && part.trim() !== '');

        return parts.join(', ');
    }

    function capture() {
        const found = [];
        for (const script of document.querySelectorAll(
            'script[type="application/ld+json"]',
        )) {
            try {
                postings(JSON.parse(script.textContent), found);
            } catch {
                // A broken block on the page is not our problem.
            }
        }
        const job = found[0];
        if (!job) {
            return null;
        }

        const organization = job.hiringOrganization;
        const title = text(job.title);
        const company = text(
            typeof organization === 'string'
                ? organization
                : (organization || {}).name,
        );
        if (!title || !company) {
            return null;
        }

        return {
            url: globalThis.location.href,
            title,
            company,
            description: text(job.description),
            location: place(job.jobLocation),
            posted_at: text(job.datePosted),
        };
    }

    return capture();
})();
