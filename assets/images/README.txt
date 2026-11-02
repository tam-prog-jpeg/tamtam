HOW TO ADD YOUR OWN HERO PHOTO
===============================

The homepage (index.php) looks for an image at:

    assets/images/hero.jpg

To add your own photo:
1. Pick a landscape (wider than tall) photo — a campus building, the
   IPO office, students/researchers at work, etc. Recommended size:
   around 1200x900px or larger.
2. Rename your file to exactly:  hero.jpg
3. Copy it into this folder (assets/images/), replacing nothing —
   there's no existing hero.jpg by default.
4. Refresh the homepage. That's it — no code changes needed.

If you'd rather use a .png or .jpeg file instead, that's fine too:
just open index.php, find this line near the top of the <body>:

    <img src="<?= BASE_URL ?>assets/images/hero.jpg" ...>

and change "hero.jpg" to your file's actual name.

If no hero.jpg is present, the homepage automatically shows a styled
placeholder pattern instead of a broken image icon, so the page never
looks broken while you're deciding on a photo.
