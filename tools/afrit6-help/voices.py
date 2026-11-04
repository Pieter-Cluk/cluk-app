#!/usr/bin/env python3
"""
Spoken explanation for the Afrit 6 help videos (runs in GitHub Actions: .github/workflows/help-voices.yml).
Dutch: Piper "nl_BE-nathalie-medium" (Flemish female). English: Kokoro "af_heart" (warm female).
Input: tools/afrit6-help/lines.json  {lang: {topic: [line, …]}}  ·  Output: <out>/<lang>/<topic>/<nn>.wav
Usage: python3 voices.py lines.json out-dir piper-model.onnx kokoro-dir
"""
import json, os, sys, wave
import numpy as np, soundfile as sf

lines_file, out, piper_model, kokoro_dir = sys.argv[1:5]
lines = json.load(open(lines_file, encoding='utf-8'))

def piper_say(voice, text, path):
    from piper import SynthesisConfig
    with wave.open(path, 'wb') as wf:
        voice.synthesize_wav(text, wf, syn_config=SynthesisConfig(length_scale=1.06))

if 'nl' in lines:
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
