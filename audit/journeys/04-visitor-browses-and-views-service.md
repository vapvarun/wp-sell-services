# Journey 04 - Visitor browses services and vendors, then views a service

**Role:** logged-out visitor, then buyer
**Rail:** standalone, woo
**Status:** rewritten 2026-10-18; walk pending (see docs/qa/walks.json)

Guards card 10208183009.

## Why this one matters

This journey is one click long and it was broken for **every vendor link on the
site**. `wpss_get_vendor_url()` points at `{vendors page}?vendor={nicename}`
whenever a vendors page exists, and `[wpss_vendors]` never read that parameter —
so following any vendor link re-rendered the directory.

It was dormant until the installer started seeding the vendors page: before
that, the resolver fell through to its `/provider/{nicename}` branch. A fix in
one place turned a latent gap into a site-wide one, which is the general lesson
— when a resolver has a "preferred" branch nothing has been using, check the
branch works before making it preferred.

## Steps

### 1. Directory renders
Visit `/vendors/`.

**Expect**
- Vendor cards render; no raw `[wpss_vendors]` text.
- Breadcrumb reads Home › Vendors.
- 390px: cards stack, `scrollWidth === innerWidth` (no horizontal overflow).

### 2. Vendor link goes to the vendor
Click any vendor card.

**Expect**
- Their **profile** renders — not the directory again.
- URL is `/vendors/?vendor={nicename}`.
- Zero `.wpss-vendors-grid .wpss-vendor-card` elements on the page: if the grid
  is still there, the profile did not take over.

### 3. A vendor with no services
Open a vendor who has published nothing.

**Expect** the profile with its empty states ("hasn't published any services",
"No reviews yet") — **not** "Vendor not found", and not the directory.

### 4. Stale or invented slug
Hand-edit to `?vendor=does-not-exist-xyz`.

**Expect** the directory, with no error text. A dead link should show the list,
not a wall.

### 5. A non-vendor's slug
Use a plain subscriber's nicename.

**Expect** the directory — guarded by `wpss_is_vendor()`, so a buyer's slug
cannot render an empty vendor profile.

### 6. Other entry points
Vendor names link from service pages, seller cards and order screens.

**Expect** all of them land on the profile. They share
`wpss_get_vendor_url()`, so they break together and fix together — worth
spot-checking two of them after any change to that resolver.

### 7. Through to the work
From the profile, open one of their services.

**Expect** the single-service page, with that vendor attributed.


# Part 2 - a service page that has a video (was journey 07)

## Why this one matters

A seller's intro video was, in three separate ways, worse than not having one:

- It **took over the main gallery area**, so a buyer landed on an embed instead
  of the work being sold.
- It had **no thumbnail**, so once anything replaced it there was no way back.
- Clicking any image thumb ran `$active.html('<img>')`, which **deleted the
  embed from the DOM**. One click lost the video permanently; only a page reload
  brought it back.

Two more things surfaced while fixing it, and both are the kind that never
appear in a bug report because nobody sees them:

- the template ran `update_post_meta()` **on every render**, so every anonymous
  page view wrote a database row;
- every visit loaded a YouTube player **whether or not anyone asked for one**.

## Preconditions

A published service with a video URL and **two or more** gallery images. Also
test the awkward shape: a video and **exactly one** image.

## Steps

### 1. The image is what you land on
Open the service page.

**Expect**
- The main area shows the featured or first image.
- The video is present but hidden — `.wpss-gallery-video[hidden]`, empty.
- **No request to youtube.com in the Network panel.** The embed sits in a
  `<template>`, which browsers do not fetch. If a player loads here, every
  visitor is paying for a third-party request nobody asked for.

### 2. The strip leads with the video
Look at the thumbnail strip.

**Expect**
- The **first** thumb is the video, carrying a play badge.
- Its poster is the **provider's own frame** (`i.ytimg.com/...` for YouTube),
  not a copy of the service's featured image. If it is the featured image, the
  video thumb is pixel-identical to the image thumb beside it and only the badge
  distinguishes them.
- The **active** thumb is the first image — the one actually displayed.

### 3. The video plays
Click the video thumb.

**Expect** the embed appears in the main area and the image is hidden. The
player is created now, not before.

### 4. It survives switching — the original defect
Click an image thumb.

**Expect** the image shows **and the embed is still in the DOM**. Then click the
video thumb again: the video returns, with no page reload.

This is the step the card was filed about. Check the embed node still exists
after the image click; if it was removed, the next click has nothing to restore.

### 5. Nothing was written by looking
Note `_wpss_video_url` for the service. Load the page as a logged-out visitor.

**Expect** the meta is **unchanged** — including still absent, if the video only
ever lived in `_wpss_gallery`. A template is a read surface.

### 6. A video and exactly one image
Set the service to one gallery image plus a video.

**Expect** the thumbnail strip still renders, with two thumbs. The old test was
`count( $gallery_ids ) > 1`, which hid the strip entirely for this shape and
made the video unreachable once the image took the main area.

### 7. 390px
**Expect** the video sizes to the column with no horizontal scroll, and the
strip is reachable.

## Notes for whoever runs this

- Check the Network panel on first load, not just the DOM. "No iframe present"
  and "no request made" are different claims and only the second one is the
  performance win.
- The poster URL is cached for a week. After changing a service's video, the old
  poster can persist — clear `wpss_video_thumb_{md5(url)}` or wait it out.

### 8. Empty states
A sitewide archive with no services shows `content-no-services`, and a request list with no requests shows `content-no-requests`, each with the next step for that visitor and no broken heading.
