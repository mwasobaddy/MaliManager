---
paths:
    - 'app/Support/**, app/Http/Requests/**'
---

# Requests

## Rich text sanitizes via the `rich_text` HTMLPurifier profile

Rich text HTML (lease agreement bodies, occupant agreement_text) is sanitized with HTMLPurifier through mews/purifier using the `rich_text` profile (config/purifier.php), NOT strip_tags (which strips all attributes). Call clean($html, 'rich_text') via App\Support\LeaseAgreementTemplate::sanitize(). Profile allowlists headings[style], p/span[style], tables, imgs, links, and CSS props (color/font-_/text-align/etc.); it strips scripts/iframes/on_/javascript:. TextAlign on headings needs h1-h6[style] in HTML.Allowed. Because purifier is a deferred provider it only resolves when the Laravel app is booted — sanitizer tests MUST live in tests/Feature, not tests/Unit.
