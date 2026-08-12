# تصميم اليوم — Concept of the day

The site opens on a full concept-car picture. The maroon header stays on top,
the navigation bar stays at the bottom, and the picture fills everything in
between. One gold button lifts it away — the valuation form is already
underneath, exactly as before.

A different car every day. The same car for everybody that day.

**Going back:** pressing the name and badge in the top corner
(**ثـمـــن مــوتــرك / Evaluate Your Car**) brings the picture back at any moment, and
returns to the first page. Whatever the customer has already typed into the form
is kept — the picture is laid over the top, not a reload.

---

## What was added

| File | New? | What it does |
|---|---|---|
| `concept.php` | new | picks today's car, prints the splash **and its styling** |
| `assets/concepts/` | new | the pictures (5 cars × 4 files each) |
| `tools/test_concept.php` | new | 25 checks on the rotation and the files |
| `README-CONCEPT.md` | new | this file |
| `index.php` | **changed** | four small hooks, listed below |
| `sw.js` | **changed** | cache name bumped to `eyc-v7` |

There is **no separate stylesheet**. The CSS is printed inside `concept.php`,
so the splash can never appear half-dressed because one file did not reach the
server or a browser was holding an old copy of it. If an `assets/concept.css`
is left over from an earlier upload it is simply unused and can be deleted.

Nothing else in the site was touched. No database, no cron job.

---

## The four hooks in `index.php`

1. line 4 — `require __DIR__ . '/concept.php';`
2. in `<head>` after `app.css` — `<?= concept_head() ?>`  *(this one only adds
   the image preload; if it is missing, the splash still styles itself)*
3. the body tag — `<body<?= concept_body_class() ?>>`
4. straight after `</header>` — `<?= concept_splash() ?>`

Remove those four lines and the site is exactly what it was.

---

## Changing the pictures

Put the new picture in `assets/concepts/` under any name, in **four** files:

```
mycar.jpg        about 1500 px wide   — phones and desktops
mycar@sm.jpg     about  860 px wide   — small phones, saves data
mycar.webp       same size as the jpg — modern browsers, half the weight
mycar@sm.webp    same as @sm.jpg
```

Only `mycar.jpg` is compulsory; the other three are used when they exist.
The `.jpg` files are what the site scans, so a name with no `.jpg` is ignored.
Drop the files in and the car joins the rotation by itself — nothing to edit.

To take a car out of the rotation, move its four files out of the folder.

### What a good picture looks like

- **Wide**, at least **1400 px** across. The site never enlarges a small
  picture — a blurry car looks cheap on a phone, so a small file is refused
  by `tools/test_concept.php` rather than stretched.
- The whole car, with empty room above and below it and a clear margin at each
  side. On a phone the sides get trimmed a little; the empty floor and glass
  are what disappear, never the car.
- **No manufacturer badge, logo or readable writing.**

### Legal — read before launch

The five pictures in the folder now are AI-generated concept cars with no
badges, which is what makes them safe to use. Never replace them with a
manufacturer's press photo: on a commercial Qatari site that is a copyright
problem and a trademark problem at the same time.

---

## How the rotation works

- The pool is every `*.jpg` in `assets/concepts/` (excluding the `@sm` twins).
- It is shuffled **once**, always the same way, seeded by how many pictures
  there are — so the order is not simply car1, car2, car3, and it is identical
  on your machine and on the server.
- The calendar then walks that order, one picture per **Qatar day**
  (`Asia/Qatar`, so the car changes at midnight Doha time).
- A car only comes back after every other car has had its turn.

With 5 pictures each car shows every 5th day. Add a 6th picture and the whole
order is reshuffled — that is normal, and still one car per day.

Check any time with:

```
php tools/test_concept.php
```

It prints the running order, the next ten days, and 25 pass/fail checks.

---

## Skipping the splash

| | |
|---|---|
| the corner brand | brings the picture **back**, from anywhere in the site |
| `index.php?nosplash=1` | opens straight on the form — handy when showing the form itself |
| `index.php?id=ABC123` | the link in Khalid's e-mail; goes straight to the status screen |
| JavaScript switched off | the splash never appears; the form is shown instead |

The splash appears on every visit. To show it only once a day per visitor,
say so and it is a three-line change in `concept.php`.

---

## Notes

- Both languages are printed into the page and CSS shows one of them, so the
  site's own **English / العربية** button switches the splash instantly with
  no reload.
- Light and dark mode both work; the picture is a night showroom, so the text
  over it stays white in both.
- Today's picture is preloaded with `fetchpriority="high"`, WebP first, so it
  paints as fast as the page itself.
- Total weight of one visit: about **100 KB** for the picture on a phone.
- The splash is hidden rather than thrown away when it lifts, so bringing it back
  is instant and costs nothing — the picture is already in the page.
- `Esc` also lifts the picture, for anyone on a keyboard; the corner brand takes
  `Enter` and `Space`.

---

## v9 — seven cars, three changes a day, and the lightning

**Pool is now 7** (`car6`, `car7` added 7 Aug). They went through exactly the same
pipeline as the first five: the AI sparkle at ≈(0.90, 0.84) inpainted out with
`cv2.inpaint`, 3.26 % trimmed off each side so the shape matches (aspect 1.672),
then 1500 w and 860 w as JPEG q84 + WebP q78. Both land at 1500×897, ~200 KB / ~96 KB —
the same weight as the others. The build script is kept outside the site.

**Rotation is no longer daily.** `CONCEPT_PER_DAY = 3` in `concept.php` splits the day
into 8-hour slots (Doha time) and walks the shuffled order one step per slot, so a
visitor who comes back in the evening sees a different car from the morning. Set it to
1, 2, 3 or 4 — nothing else changes. `concept_for_day()` is kept as an alias so older
callers still work.

Order with 7 pictures: **car1 → car5 → car7 → car4 → car3 → car2 → car6**, a full
cycle every 2 days and 8 hours.

**The lightning (برق).** Khalid asked for a lightning effect with sound when the page
opens. The sound was dropped on purpose: no browser will play audio before the visitor
has touched the screen, so a "sound on load" would simply be silent for most people —
and sites that make noise unprompted get closed. The visual is in.

A white bolt with a maroon glow over a maroon-and-white wash — the two Qatar flag
colours. It strikes once as the picture appears, then every 14–20 seconds while the
picture is still up. It stops when the splash is lifted away, and it stops when the tab
goes to the background.

Two deliberate limits, and they matter:

- **the full-screen wash flashes once per strike.** Large-area flashing faster than
  three times a second is a genuine seizure risk (WCAG 2.3.1). It rises and falls once.
- **the bolt flickers three times.** It is a thin shape covering a small part of the
  screen, which is what makes it read as lightning rather than a fade — small-area
  flashes are outside the rule.

`prefers-reduced-motion: reduce` removes the whole thing, in CSS *and* in JS.

Switched on and off from the control panel: **الصفحة الرئيسية → برق على صورة الترحيب**
(`splashLightning`). Off means the `data-storm` attribute is `0` and no timer is ever
started.
