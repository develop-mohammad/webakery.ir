---
name: webakery-login-design
description: Design and visually QA the ورود آسان login UI using the WBL design system. Use when editing login page, glass UI, OTP form, Elementor widget styles, or design-system tokens.
---

# ورود آسان — Design + Visual QA

## Source of truth
- Tokens / principles: `webakery-login/design-system/DESIGN-SYSTEM.md`
- Tokens CSS: `webakery-login/design-system/tokens.css`
- Components: `webakery-login/design-system/components.css`
- Style guide: `webakery-login/design-system/index.html`
- Full page: `webakery-login/design-system/login-page.html`
- Plugin frontend CSS: `webakery-login/assets/css/frontend.css`

## Preview (NOT npm)
This repo is WordPress/PHP — do **not** run `npm run dev` at repo root.

```bash
cd webakery-login && python3 -m http.server 8765 --bind 127.0.0.1
```

- Style guide: `http://127.0.0.1:8765/design-system/`
- Login page: `http://127.0.0.1:8765/design-system/login-page.html`
- Widget canvas: `http://127.0.0.1:8765/canvas-preview.html`

## Design rules (must follow)
1. Brand **ورود آسان** is hero-level on the login page.
2. Glass panel on atmospheric gradient — never flat white-only page.
3. Accent teal `#0d9488` (or Elementor global primary).
4. Only **one** OTP step visible (`[hidden]` must win over `display:flex`).
5. RTL + Vazirmatn; Persian copy.
6. Avoid purple/indigo AI cliché and cream+terracotta cliché.

## After UI changes — Visual QA checklist
1. Open login-page (desktop) → screenshot brand panel + glass form.
2. Resize ~390×844 → screenshot mobile stack.
3. Submit phone → OTP step only (phone form hidden).
4. Trigger error alert → shake/visible, single step still.
5. Check console for JS errors; note failed network only if WordPress AJAX expected.
6. Optional: `cd webakery-login/visual-tests && npm run test:visual`

## When editing components
- Change tokens in `tokens.css` first, then components / frontend.css.
- Keep Elementor bridge: `--e-global-color-primary` / text.
- Rebuild `webakery-login.zip` after shipping UI changes.
