#!/usr/bin/env python3
"""Generate a lossless, pixel-identical WebP for the bundled website logo.

Never overwrite original artwork. Remove the optional output if it is not
smaller or exact same RGBA pixels; the PHP template uses the original then.
"""
from pathlib import Path
from PIL import Image

original = Path("assets/logo.png")
optimized = Path("assets/logo.optimized.webp")
optimized.unlink(missing_ok=True)
if not original.is_file():
    print("No bundled logo; keeping configured logo URL.")
else:
    with Image.open(original) as logo:
        pixels = logo.convert("RGBA")
        pixels.save(optimized, "WEBP", lossless=True, method=6, exact=True)
        with Image.open(optimized) as compressed:
            same = compressed.convert("RGBA").tobytes() == pixels.tobytes()
            same_dimensions = compressed.size == logo.size
    if not same or not same_dimensions or optimized.stat().st_size >= original.stat().st_size:
        optimized.unlink(missing_ok=True)
        print("Lossless WebP not smaller/pixel-identical; original PNG retained.")
    else:
        print(f"Lossless logo optimized: {original.stat().st_size} -> {optimized.stat().st_size} bytes")
