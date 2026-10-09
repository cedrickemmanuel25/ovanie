"""Génère l'emblème OVANIE, l'icône de l'application et l'image de démarrage Android
à partir du logo officiel (assets/images/ovanie_logo.png). À relancer si le logo change."""
from PIL import Image, ImageDraw
import os

logo = Image.open('assets/images/ovanie_logo.png').convert('RGBA')
em = logo.crop((0, 12, 342, 308))
d = ImageDraw.Draw(em)
# Début de la signature manuscrite, qui passe juste sous la vague bleue.
d.polygon([(245, 244), (290, 208), (342, 208), (342, 296), (200, 296), (215, 273)], fill=(0, 0, 0, 0))
d.rectangle((322, 82, 342, 240), fill=(0, 0, 0, 0))   # bord de la lettre V

# On ne garde que les grandes formes de l'emblème : les petits éclats isolés sont retirés.
import numpy as np
alpha = np.array(em)[:, :, 3] > 40
labels = np.zeros(alpha.shape, dtype=np.int32)
sizes = {}
current = 0
for y0, x0 in zip(*np.nonzero(alpha)):
    if labels[y0, x0]:
        continue
    current += 1
    stack = [(y0, x0)]
    labels[y0, x0] = current
    count = 0
    while stack:
        y, x = stack.pop()
        count += 1
        for ny, nx in ((y + 1, x), (y - 1, x), (y, x + 1), (y, x - 1)):
            if 0 <= ny < alpha.shape[0] and 0 <= nx < alpha.shape[1] and alpha[ny, nx] and not labels[ny, nx]:
                labels[ny, nx] = current
                stack.append((ny, nx))
    sizes[current] = count
pixels = np.array(em)
for label, count in sizes.items():
    if count < 900:
        pixels[labels == label] = 0
pixels[~alpha] = 0
em = Image.fromarray(pixels, 'RGBA')
em = em.crop(em.getbbox())
side = max(em.size) + 8
sq = Image.new('RGBA', (side, side), (0, 0, 0, 0))
sq.alpha_composite(em, ((side - em.width) // 2, (side - em.height) // 2))
sq.resize((512, 512), Image.LANCZOS).save('assets/images/ovanie_mark.png')


def gradient(size):
    g = Image.new('RGBA', (size, size))
    px = g.load()
    for y in range(size):
        for x in range(size):
            t = (x + y) / (2 * size)
            px[x, y] = (int(11 - 9 * t), int(44 - 30 * t), int(95 - 61 * t), 255)
    return g


def icon(size):
    s = size * 4
    bg = gradient(s)
    m = sq.resize((int(s * .7), int(s * .7)), Image.LANCZOS)
    bg.alpha_composite(m, ((s - m.width) // 2, (s - m.height) // 2))
    mask = Image.new('L', (s, s), 0)
    ImageDraw.Draw(mask).rounded_rectangle((0, 0, s - 1, s - 1), radius=int(s * .22), fill=255)
    bg.putalpha(mask)
    return bg.resize((size, size), Image.LANCZOS)


res = 'android/app/src/main/res'
for density, size in {'mdpi': 48, 'hdpi': 72, 'xhdpi': 96, 'xxhdpi': 144, 'xxxhdpi': 192}.items():
    icon(size).save(f'{res}/mipmap-{density}/ic_launcher.png')
for density, size in {'mdpi': 96, 'hdpi': 144, 'xhdpi': 192, 'xxhdpi': 288, 'xxxhdpi': 384}.items():
    os.makedirs(f'{res}/drawable-{density}', exist_ok=True)
    sq.resize((size, size), Image.LANCZOS).save(f'{res}/drawable-{density}/launch_mark.png')
os.makedirs(f'{res}/drawable-nodpi', exist_ok=True)
s = 432
canvas = Image.new('RGBA', (s, s), (0, 0, 0, 0))
m = sq.resize((int(s * .5), int(s * .5)), Image.LANCZOS)
canvas.alpha_composite(m, ((s - m.width) // 2, (s - m.height) // 2))
canvas.save(f'{res}/drawable-nodpi/launch_mark_splash.png')
