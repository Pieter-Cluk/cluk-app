#!/usr/bin/env python3
"""Spoken lines per language and topic from the filming plans: title + intro, each step, the outro.
Usage: python3 lines.py plan-nl.json plan-en.json > tools/afrit6-help/lines.json"""
import json, re, sys

SAY = {
    'nl': [('excl. btw', 'exclusief btw'), ('Clukie', 'Klukkie'), ('CLUK', 'Kluk'), ('colli', 'kolli'), ('24 uur-afhaalrek', 'vierentwintig-uur-afhaalrek'), ('bv.', 'bijvoorbeeld')],
    'en': [('Clukie', 'Cluckie'), ('CLUK', 'Cluck'), ('24-hour', 'twenty-four hour'), ('e.g.', 'for example')],
}

def speak(lang, t):
    for a, b in SAY[lang]:
        t = t.replace(a, b)
    return re.sub(r'\s+', ' ', t).strip()

out = {}
for f in sys.argv[1:]:
    p = json.load(open(f, encoding='utf-8'))
    lang = p['lang']
    out[lang] = {t['key']: [speak(lang, t['title'] + '. ' + t['intro'])] + [speak(lang, s['text']) for s in t['steps']] + [speak(lang, p['outro'])] for t in p['topics']}
print(json.dumps(out, ensure_ascii=False, indent=1))
