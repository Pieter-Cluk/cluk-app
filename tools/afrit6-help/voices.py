#!/usr/bin/env python3
"""
Spoken explanation for the Afrit 6 help videos (runs in GitHub Actions: .github/workflows/help-voices.yml).
Dutch: Azure Speech "nl-BE-ArnaudNeural" (Flemish male, chosen by Pieter 04/11/2026; needs the AZURE_SPEECH_KEY secret),
Piper "nl_BE-nathalie-medium" only as a fallback when asked for (VOICE_NL=piper). English: Kokoro "af_heart" (warm female).
LANGS (env, default "nl,en") picks the languages to make.
Input: tools/afrit6-help/lines.json  {lang: {topic: [line, …]}}  ·  Output: <out>/<lang>/<topic>/<nn>.wav
Usage: python3 voices.py lines.json out-dir piper-model.onnx kokoro-dir
"""
import json, os, sys, wave
import numpy as np, soundfile as sf

lines_file, out, piper_model, kokoro_dir = sys.argv[1:5]
lines = json.load(open(lines_file, encoding='utf-8'))
langs = [l.strip() for l in os.environ.get('LANGS', 'nl,en').split(',') if l.strip()]
lines = {l: v for l, v in lines.items() if l in langs}


def azure_say(text, path):
    import urllib.request
    from xml.sax.saxutils import escape
    key, region = os.environ['AZURE_SPEECH_KEY'], os.environ.get('AZURE_SPEECH_REGION') or 'northeurope'
    ssml = ('<speak version="1.0" xml:lang="nl-BE"><voice name="nl-BE-ArnaudNeural">'
            f'<prosody rate="-4%">{escape(text)}</prosody></voice></speak>').encode('utf-8')
    req = urllib.request.Request(f'https://{region}.tts.speech.microsoft.com/cognitiveservices/v1', data=ssml, method='POST', headers={
        'Ocp-Apim-Subscription-Key': key, 'Content-Type': 'application/ssml+xml',
        'X-Microsoft-OutputFormat': 'riff-24khz-16bit-mono-pcm', 'User-Agent': 'cluk-help-videos'})
    with urllib.request.urlopen(req, timeout=60) as r, open(path, 'wb') as f:
        f.write(r.read())

def piper_say(voice, text, path):
    from piper import SynthesisConfig
    with wave.open(path, 'wb') as wf:
        voice.synthesize_wav(text, wf, syn_config=SynthesisConfig(length_scale=1.06))

if 'nl' in lines and os.environ.get('VOICE_NL', 'azure') == 'azure':
    if not os.environ.get('AZURE_SPEECH_KEY'):
        sys.exit('AZURE_SPEECH_KEY is missing: add it as a GitHub secret (Azure portal > Speech resource > Keys).')
    for topic, ls in lines['nl'].items():
        os.makedirs(f'{out}/nl/{topic}', exist_ok=True)
        for i, t in enumerate(ls):
            azure_say(t, f'{out}/nl/{topic}/{i:02d}.wav')
        print('nl', topic, len(ls), flush=True)
elif 'nl' in lines:
    from piper import PiperVoice
    nl = PiperVoice.load(piper_model)
    for topic, ls in lines['nl'].items():
        os.makedirs(f'{out}/nl/{topic}', exist_ok=True)
        for i, t in enumerate(ls):
            piper_say(nl, t, f'{out}/nl/{topic}/{i:02d}.wav')
        print('nl', topic, len(ls), flush=True)

if 'en' in lines:
    from kokoro_onnx import Kokoro
    k = Kokoro(os.path.join(kokoro_dir, 'kokoro-v1.0.int8.onnx'), os.path.join(kokoro_dir, 'voices-v1.0.bin'))
    for topic, ls in lines['en'].items():
        os.makedirs(f'{out}/en/{topic}', exist_ok=True)
        for i, t in enumerate(ls):
            s, sr = k.create(t, voice='af_heart', speed=0.94, lang='en-us')
            sf.write(f'{out}/en/{topic}/{i:02d}.wav', s.astype(np.float32), sr)
        print('en', topic, len(ls), flush=True)
