#!/usr/bin/env python3
"""
Turns the filmed frames (film.cjs) and the spoken lines (voices.py) into one MP4 per topic, plus a poster JPG.
Each frame stays on screen while its line is spoken; frames cross-fade.
Usage: python3 render.py <frames-dir> <voices-dir/lang> <out-dir> [topic]
"""
import os, subprocess, sys, tempfile
import numpy as np, soundfile as sf

frames_dir, voices_dir, out_dir = sys.argv[1:4]
only = sys.argv[4] if len(sys.argv) > 4 else None
LEAD, TAIL, FADE, SR = 0.45, 0.7, 0.35, 24000
os.makedirs(out_dir, exist_ok=True)


def load(path):
    s, sr = sf.read(path, dtype='float32')
    if s.ndim > 1:
        s = s.mean(axis=1)
    if sr != SR:  # simple resample (Piper speaks at 22050 Hz)
        n = int(round(len(s) * SR / sr))
        s = np.interp(np.linspace(0, len(s) - 1, n), np.arange(len(s)), s).astype(np.float32)
    peak = float(np.max(np.abs(s))) or 1.0
    return s * (0.89 / peak)


for topic in sorted(os.listdir(frames_dir)):
    fdir = os.path.join(frames_dir, topic)
    if not os.path.isdir(fdir) or (only and topic != only):
        continue
    frames = sorted(f for f in os.listdir(fdir) if f.endswith('.png'))
    lines = sorted(f for f in os.listdir(os.path.join(voices_dir, topic)) if f.endswith(('.wav', '.flac')))
    assert len(frames) == len(lines), f'{topic}: {len(frames)} frames, {len(lines)} lines'
    audio, durs = [], []
    for i, wav in enumerate(lines):
        s = load(os.path.join(voices_dir, topic, wav))
        tail = TAIL + (FADE if i == len(lines) - 1 else 0)
        seg = np.concatenate([np.zeros(int(LEAD * SR), np.float32), s, np.zeros(int(tail * SR), np.float32)])
        audio.append(seg)
        durs.append(len(seg) / SR)
    with tempfile.TemporaryDirectory() as tmp:
        wav = os.path.join(tmp, 'a.wav')
        sf.write(wav, np.concatenate(audio), SR)
        # Each picture lasts as long as its line (+ the cross-fade it shares with the next one).
        args = ['ffmpeg', '-v', 'error', '-y']
        for i, f in enumerate(frames):
            d = durs[i] + (FADE if i < len(frames) - 1 else 0)
            args += ['-loop', '1', '-t', f'{d:.3f}', '-framerate', '25', '-i', os.path.join(fdir, f)]
        args += ['-i', wav]
        chain, last, t = [], '[0:v]', 0.0
        for i in range(1, len(frames)):
            t += durs[i - 1]
            chain.append(f'{last}[{i}:v]xfade=transition=fade:duration={FADE}:offset={t - 0:.3f}[v{i}]')
            last = f'[v{i}]'
        chain.append(f'{last}format=yuv420p[vout]')
        out = os.path.join(out_dir, topic + '.mp4')
        args += ['-filter_complex', ';'.join(chain), '-map', '[vout]', '-map', f'{len(frames)}:a',
                 '-c:v', 'libx264', '-preset', 'slow', '-crf', '27', '-tune', 'stillimage', '-r', '25',
                 '-c:a', 'aac', '-b:a', '96k', '-movflags', '+faststart', '-shortest', out]
        subprocess.run(args, check=True)
        subprocess.run(['ffmpeg', '-v', 'error', '-y', '-i', os.path.join(fdir, frames[0]), '-q:v', '4', os.path.join(out_dir, topic + '.jpg')], check=True)
    print(topic, f'{sum(durs):.1f}s', f'{os.path.getsize(out) / 1e6:.2f} MB', flush=True)
