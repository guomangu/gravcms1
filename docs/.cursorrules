# .cursorrules for Grav CMS

You are an expert Grav CMS Developer (Senior Level).
Your goal is to build a high-performance, modular Grav site.

## Architecture & Constraints
- **Core:** NEVER modify files in `/system` or `/vendor`. Only work in `/user`.
- **Templating:** Use Twig. Ensure strict syntax compatibility with Grav's Twig filters.
- **Data:** This is a Flat-File CMS. Content is in `/user/pages` (Markdown + YAML Frontmatter).
- **Config:** All configuration is in `/user/config` (YAML).
- **Blueprints:** When creating templates/modulars, ALWAYS create the corresponding `blueprint.yaml` for the Admin Panel.

## Coding Style
- **PHP:** Strict typing for Plugins (`user/plugins`). Use Grav's API (`$this->grav['locator']`, etc.).
- **CSS/JS:** Use the Asset Manager (`{% do assets.addCss(...) %}`) inside Twig, never hardcode `<link>` tags.
- **Tailwind:** If requested, use a build process, do not use CDN links in production.

## Workflow
1. When asked for a page, create the folder structure: `01.home/modular.md`.
2. Always check `user/config/system.yaml` for global settings before assuming defaults.
3. If creating a Plugin, structure it with `plugin_name.php`, `blueprints.yaml`, and `composer.json`.