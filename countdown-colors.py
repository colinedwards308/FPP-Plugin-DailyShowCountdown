#!/usr/bin/python3
"""Render one 128x96 RGB countdown frame to stdout for FPP's pixel API."""
import re
import sys

from PIL import Image, ImageDraw, ImageFont


def render(countdown):
    if not re.fullmatch(r"\d{2}:\d{2}:\d{2}", countdown):
        raise ValueError("Expected HH:MM:SS")
    font_path = "/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf"
    heading_font = ImageFont.truetype(font_path, 16)
    timer_font = ImageFont.truetype(font_path, 24)
    frame = Image.new("RGB", (128, 96), "black")
    draw = ImageDraw.Draw(frame)
    words = [("Show", "#00FF00"), ("begins", "#FF0000"), ("in:", "#0000FF")]
    widths = [draw.textlength(word, font=heading_font) for word, _ in words]
    gap = draw.textlength(" ", font=heading_font)
    total = sum(widths) + 2 * gap
    if total > 124:
        raise ValueError("Heading does not fit the matrix")
    x = (128 - total) / 2
    for (word, color), width in zip(words, widths):
        draw.text((x, 45), word, font=heading_font, fill=color, anchor="ls")
        x += width + gap
    # Measure the advance for centering, not the changing visible glyph bounds.
    width = draw.textlength(countdown, font=timer_font)
    if width > 124:
        raise ValueError("Countdown does not fit the matrix")
    # Derive a single baseline from the whole digit set. Individual digits have
    # different top bearings; using their current bbox makes the line jump.
    reference = draw.textbbox((0, 0), "0123456789:", font=timer_font, anchor="ls")
    baseline = 56 - reference[1]
    draw.text((64, baseline), countdown, font=timer_font, fill="white", anchor="ms")
    return frame


if __name__ == "__main__":
    sys.stdout.buffer.write(render(sys.argv[1]).tobytes())
