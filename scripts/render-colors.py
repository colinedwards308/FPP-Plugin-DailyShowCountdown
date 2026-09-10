#!/usr/bin/env python3
"""Render two centered text lines to RGB bytes; no FPP requests or file writes."""
import json
import re
import subprocess
import sys
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


def font_path(name):
    # Match the exact PostScript name, never silently substitute another font.
    result = subprocess.run(
        ['fc-list', '-f', '%{postscriptname}|%{file}\n'],
        check=True, capture_output=True, text=True, timeout=2,
    )
    matches = []
    for line in result.stdout.splitlines():
        names, separator, path = line.partition('|')
        if separator and name in names.split(',') and Path(path).is_file():
            matches.append(path)
    for path in sorted(matches, key=lambda p: (Path(p).suffix.lower() not in ('.otf', '.ttf'), p)):
        try:
            ImageFont.truetype(path, 16)
            return path
        except OSError:
            continue
    raise ValueError(f'Installed FPP font {name!r} cannot be resolved for separate colors.')


def render(config):
    width, height = config['width'], config['height']
    if not (1 <= width and 1 <= height and width * height <= 1048576):
        raise ValueError('Invalid model dimensions')
    for key in ('headingColor', 'timerColor'):
        if not re.fullmatch(r'#[0-9a-fA-F]{6}', config[key]):
            raise ValueError('Invalid color')
    size = config['fontSize']
    if not 4 <= size <= 200:
        raise ValueError('Invalid font size')
    lines = config['text'].split('\n')
    if len(lines) > 2 or len(config['text']) > 512:
        raise ValueError('Expected a heading and timer')
    font = ImageFont.truetype(font_path(config['font']), size)
    image = Image.new('RGB', (width, height))
    draw = ImageDraw.Draw(image)
    ascent, descent = font.getmetrics()
    line_height = ascent + descent
    top = (height - line_height * len(lines)) // 2
    for index, line in enumerate(lines):
        color = config['headingColor'] if len(lines) == 2 and index == 0 else config['timerColor']
        draw.text((width / 2, top + index * line_height + ascent), line, font=font, fill=color, anchor='ms')
    return image


if __name__ == '__main__':
    try:
        sys.stdout.buffer.write(render(json.load(sys.stdin)).tobytes())
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
