# Journey 07 — A service page that has a video

**Role:** logged-out visitor
**Status:** passing as of commits `1ab6d12`, `e13921f`

Guards card 10208068212.

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
