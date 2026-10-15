# Email deliverability

Alert e-mails go through Laravel's mailer. Any SMTP provider works; Resend
and Brevo both offer an SMTP endpoint, so no extra package is needed.

## Mailer configuration

`.env` (production):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps            # or "smtp" with STARTTLS on port 587
MAIL_FROM_ADDRESS=alerts@your-domain.com
MAIL_FROM_NAME="${APP_NAME}"
```

| Provider | MAIL_HOST              | MAIL_PORT | MAIL_USERNAME | MAIL_PASSWORD   |
|----------|------------------------|-----------|---------------|-----------------|
| Resend   | `smtp.resend.com`      | 465       | `resend`      | your API key    |
| Brevo    | `smtp-relay.brevo.com` | 587       | your login    | your SMTP key   |

In development every message is caught by Mailpit: <http://localhost:8025>.

Mails are queued on the `notifications` queue (Horizon, `interactive`
supervisor) and retried three times with back-off (1, 5 and 15 minutes).

## DNS records for the sending domain

Without these, alert e-mails will land in spam or be rejected.

### SPF

One TXT record on the domain used in `MAIL_FROM_ADDRESS`, listing every
service allowed to send for it (merge with an existing SPF record: there must
be only one):

```
your-domain.com.  TXT  "v=spf1 include:amazonses.com include:spf.brevo.com ~all"
```

Resend sends through Amazon SES (`include:amazonses.com`, set on the
`send.` subdomain Resend tells you); Brevo uses `include:spf.brevo.com`.

### DKIM

The provider generates the keys; add the records it shows in its dashboard
(Resend: `resend._domainkey`, Brevo: `brevo1._domainkey` / `brevo2._domainkey`)
and wait until the provider marks the domain as verified.

### DMARC

Start in monitoring mode, then tighten once reports look clean:

```
_dmarc.your-domain.com.  TXT  "v=DMARC1; p=none; rua=mailto:dmarc@your-domain.com; adkim=s; aspf=s"
```

Move to `p=quarantine` and then `p=reject` after a few weeks.

## Unsubscribe

Every alert e-mail carries a signed unsubscribe link and the RFC 8058
headers:

```
List-Unsubscribe: <https://your-domain.com/alerts/12/unsubscribe?signature=…>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
```

Gmail and Yahoo require one-click unsubscribe for bulk senders. The link
turns e-mail off for that alert only; the alert keeps working in the app.
Users can also turn all alert e-mails off in *Settings › Notifications*,
which withdraws their e-mail consent.

## Checklist before going live

- [ ] Domain verified in the provider (SPF and DKIM green).
- [ ] DMARC record published.
- [ ] `MAIL_FROM_ADDRESS` on that domain, with a monitored reply address.
- [ ] Send a test alert to <https://www.mail-tester.com> and aim for 9/10+.
- [ ] Horizon running so queued mail is sent.
