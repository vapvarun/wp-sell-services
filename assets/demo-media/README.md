# Demo media (bundled)

Sample images shipped with the plugin so `wp wpss demo marketplace` produces an
image-complete marketplace with **no network dependency** — the demo is the
customer's first impression, so it must look good out of the box on any server.

- `services/` — landscape photos used for service gallery images, service
  featured images, and portfolio items.
- `avatars/` — square photos used for vendor avatars.

`MarketplaceSeeder::sideload_image()` picks a file from the matching pool
deterministically (by seed) and copies it into the media library at seed time;
the bundled originals are never modified. If this folder is ever missing, the
seeder falls back to locally generated placeholder images so a seed is never
imageless.

Source: photos from Lorem Picsum (https://picsum.photos), free for commercial
use. Replace with your own brand-appropriate photos any time — keep the same
filenames/aspect ratios (services landscape, avatars square) and the seeder
picks them up automatically.

**Do not exclude this folder from the distribution build** — it must ship in the
plugin zip for the demo to work on customer installs.
