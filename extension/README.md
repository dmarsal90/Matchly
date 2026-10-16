# Matchly browser extension

Fills job application forms with the data of an application you approved in Matchly. It only **reads** (a bearer token, approved applications only) and only fills **empty** fields: it never clicks or submits anything, so you always review the form yourself.

## Install (Chrome / Edge / Brave)

1. In Matchly, open **Settings › Browser extension** and create a token.
2. Open `chrome://extensions`, turn on _Developer mode_ and choose _Load unpacked_, then pick this `extension/` folder.
3. Open the extension, paste your Matchly address and the token, and save. The browser asks permission for that address only.
4. On a job application page, pick the approved application and click _Fill this page_.

## How it works

- `popup.js` calls `GET /api/extension/packages`, `.../{id}/fields` and `.../{id}/resume.pdf`.
- `match.js` decides which Matchly field a control asks for (English, Spanish, Portuguese); it is unit tested in `match.test.js`.
- `fill.js` runs in the page, sets values with the native setter and fires `input`/`change`, and attaches the CV to a CV/resume file input.
- Pages that render the form inside a cross-origin iframe or a closed shadow root cannot be filled.
