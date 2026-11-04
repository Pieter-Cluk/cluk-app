# Afrit 6 help videos

Short instruction videos for Afrit 6 members (Help tab in the member portal), in Dutch and English.
They are filmed in a demo copy of the portal with made-up members (never real data).

1. Texts: `lang/{nl,en,fr,es}/a6help.php` · steps (what to highlight and do): `config/afrit6-help.php`.
2. Demo database + local server:
   `DB_DATABASE=/tmp/a6demo.sqlite php artisan migrate:fresh --force` and
   `DB_DATABASE=/tmp/a6demo.sqlite php artisan tinker --execute="require 'tools/afrit6-help/demo.php';"`,
   then serve `public/` with that `DB_DATABASE` on http://127.0.0.1:8124.
3. Plan per language: `PLAN_OUT=/tmp/plan-nl.json php artisan tinker --execute="\$lang='nl'; require 'tools/afrit6-help/plan.php';"`
   (set the demo member's locale to the same language, on a fresh demo database per language).
4. Frames: `node tools/afrit6-help/film.cjs /tmp/plan-nl.json /tmp/frames-nl http://127.0.0.1:8124`.
5. Voices: `python3 tools/afrit6-help/lines.py /tmp/plan-nl.json /tmp/plan-en.json > tools/afrit6-help/lines.json`,
   push, run the "Help videos · voices" workflow; it puts the voice files on the `help-voices` branch.
6. Videos: `python3 tools/afrit6-help/render.py /tmp/frames-nl <help-voices checkout>/nl public/media/afrit6-help/nl`.
