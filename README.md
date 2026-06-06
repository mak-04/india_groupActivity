# AI Study Tracker & Reviewer

A XAMPP-friendly PHP/MySQL study assistant using Gemini for educational lessons, explanations, and quizzes only.

## Setup

1. Copy this folder into `xampp/htdocs/ai-study-tracker`.
2. Open phpMyAdmin and import `database.sql`.
3. Set environment variables (or edit `config/config.php`):
   - `GEMINI_API_KEY` — your Google Gemini API key
   - `DB_HOST` (default: 127.0.0.1)
   - `DB_NAME` (default: ai_study_tracker)
   - `DB_USER` (default: root)
   - `DB_PASS` (default: empty)
4. Optional — PDF upload text extraction:
   ```bash
   pip install pypdf
   ```
5. Visit `http://localhost/ai-study-tracker/`.

If no Gemini key is configured, the app uses built-in sample responses so the UI can be tested locally.

## File Structure

```
ai-study-tracker/
├── config/
│   └── config.php         # App settings, DB, helpers
├── includes/
│   └── auth.php           # Session bootstrap + remember-me
├── assets/
│   ├── css/style.css
│   ├── js/app.js
│   ├── js/auth.js
│   └── img/logo.png       # Add your logo here
├── python/
│   ├── extract_text.py    # PDF/DOCX/TXT text extractor
│   └── gemini_call.py     # Python Gemini bridge (optional)
├── storage/sessions/      # Auto-created
├── uploads/               # Auto-created (temp files)
├── index.php              # Login / Sign-up page
├── dashboard.php          # Main app (requires login)
├── auth_action.php        # Login/signup POST handler
├── forgot_password.php    # Password reset
├── logout.php             # Session destroy
├── api.php                # AJAX endpoint (stats, AI, history)
└── database.sql           # DB schema
```

## Bug Fixes Applied

| File | Issue | Fix |
|------|-------|-----|
| `config/config.php` | Real Gemini API key was hardcoded | Removed — env var only, placeholder fallback |
| `index.php` | Was an exact copy of `dashboard.php` (infinite redirect loop) | Restored as proper login/signup page |
| `dashboard.php` | Duplicate `id="promptBarEmpty"` on chat prompt bar | Renamed chat bar to `id="promptBarChat"` |
| `api.php` | `$model` undefined in `error_log()` call inside `gemini_curl()` | Passed `$model` parameter correctly |
| `api.php` | Uploaded temp files never deleted after extraction | Added `@unlink($target)` after `shell_exec` |
| `api.php` | Used `python` command (may not exist on Linux) | Changed to `python3` |
| `app.js` | `buildFormData()` used `pendingFile` after it was cleared | Capture file reference before clearing |
| `app.js` | `retryLast()` always targeted `chatInput` even on empty state | Now picks correct textarea based on view |
| `app.js` | `clearPendingFile()` only targeted `#promptBarEmpty`, missing chat bar | Updated to target both bars |
| `app.js` | Settings sidebar button ignored (wrong selector) | Added dedicated listener for `.side-settings` |

## Notes

- The forgot password page generates a reset link on screen (for localhost). In production, connect it to `mail()` or SMTP.
- Set `secure => true` in the remember-me `setcookie()` call when deploying over HTTPS.
