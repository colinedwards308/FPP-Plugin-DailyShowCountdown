"""Run on FPP: python3 -m unittest -v test_countdown_colors.py"""
import importlib.util
import math
from pathlib import Path
import unittest

from PIL import ImageFont

spec = importlib.util.spec_from_file_location(
    "countdown_colors", Path(__file__).with_name("countdown-colors.py"))
renderer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(renderer)


class TimerAlignmentTests(unittest.TestCase):
    def test_colons_stay_fixed_as_digits_change(self):
        font = ImageFont.truetype(
            "/usr/share/fonts/opentype/urw-base35/NimbusSans-Regular.otf", 24)
        samples = [f"{n:02}:{n:02}:{n:02}" for n in range(60)]
        samples += ["00:00:00", "11:11:14", "11:11:10", "23:59:59", "99:59:59"]
        left = (128 - font.getlength(samples[0])) / 2
        # A colon is unchanged content: its pixels must not move between frames.
        x0 = math.ceil(left + font.getlength("00"))
        x1 = math.floor(left + font.getlength("00:"))
        expected = renderer.render(samples[0]).crop((x0, 50, x1, 90))
        self.assertIsNotNone(expected.getbbox())
        for sample in samples:
            with self.subTest(sample=sample):
                image = renderer.render(sample)
                self.assertEqual(image.crop((x0, 50, x1, 90)).tobytes(), expected.tobytes())
                self.assertEqual(image.size, (128, 96))
                self.assertEqual(len(image.tobytes()), 36864)


if __name__ == "__main__":
    unittest.main()
