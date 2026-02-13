# Service Gallery & Media

Add images and videos to showcase your work. Gallery images are stored in WordPress media library and referenced in service meta.

## Gallery Limits

| Media Type | Free | Pro |
|-----------|------|-----|
| Main Image | 1 (required) | 1 (required) |
| Additional Images | 4 | Unlimited (filter: `wpss_service_max_gallery` = `-1`) |
| Videos | 1 URL | 3 URLs (filter: `wpss_service_max_videos` = `3`) |

## Main Image (Featured Image)

![Admin gallery upload interface for adding service images](../images/admin-gallery-upload.png)

Required for publishing. Set as WordPress post thumbnail.

**Requirements:**
- Recommended dimensions: 800x600px
- Allowed formats: JPG, JPEG, PNG, GIF, WebP
- Maximum file size: 5MB
- MIME type validation enforced

**Setting Main Image:**
```php
set_post_thumbnail( $service_id, $attachment_id );
```

**Fallback Behavior:**
If no main image uploaded, wizard uses first gallery image as featured image automatically.

## Additional Gallery Images

Showcase work samples beyond the main image.

**Limits:**
- Free: Maximum 4 additional images
- **[PRO]** Unlimited (filter returns `-1`)

**Allowed Formats:**
- JPG, JPEG
- PNG
- GIF
- **WebP** (supported, contrary to old docs)

**File Size:**
- Maximum 5MB per image
- Not 2MB as mentioned in old docs

**Storage:**
```php
// Stored in _wpss_gallery meta as:
[
  'images' => [123, 456, 789], // Attachment IDs
  'video' => 'https://youtube.com/watch?v=...'
]
```

## Video Embeds

Add video URLs to showcase services.

**Limits:**
- Free: 1 video URL
- **[PRO]** 3 video URLs

**Supported Platforms:**
- YouTube (`youtube.com`, `www.youtube.com`, `youtu.be`)
- Vimeo (`vimeo.com`, `www.vimeo.com`)

**NOT Supported:**
- Direct video uploads (MP4, MOV, etc.) - Only URL embeds
- Other platforms (TikTok, Instagram, etc.)

**Validation:**
```php
// In GalleryService::is_valid_embed_url()
$allowed_hosts = [
    'youtube.com',
    'www.youtube.com',
    'youtu.be',
    'vimeo.com',
    'www.vimeo.com'
];
```

## Image Upload Process

### Via Wizard

1. Click "Add Image" in gallery step
2. WordPress media uploader opens
3. Select or upload image
4. Wizard validates file size and type
5. Ownership verified (must be uploaded by current user or admin)
6. Image ID stored in `_wpss_gallery` meta

### AJAX Handler

```php
// ServiceWizard::ajax_upload_gallery()
add_action( 'wp_ajax_wpss_wizard_upload_gallery', [...] );

// Validates:
- File type (jpg, jpeg, png, gif, webp)
- MIME type (prevents extension spoofing)
- File size (max 5MB)
- User ownership
```

## Gallery Service Class

**Location:** `src/Services/GalleryService.php`

**Key Methods:**
```php
$gallery_service = new GalleryService();

// Get all gallery items
$gallery = $gallery_service->get_gallery( $service_id );

// Save gallery
$gallery_service->save_gallery( $service_id, $items );

// Add single item
$gallery_service->add_item( $service_id, $item_data );

// Remove item
$gallery_service->remove_item( $service_id, $index );

// Reorder items
$gallery_service->reorder( $service_id, $new_order );

// Get only images
$images = $gallery_service->get_images( $service_id );

// Get only videos
$videos = $gallery_service->get_videos( $service_id );
```

## Gallery Item Types

Three item types supported:

### Image
```php
[
  'type' => 'image',
  'attachment_id' => 123,
  'alt' => 'Alt text'
]
```

### Video (Direct Upload)
```php
[
  'type' => 'video',
  'attachment_id' => 456, // MP4/WebM/MOV file
  'poster_id' => 789 // Optional thumbnail
]
```

**Allowed Video Formats:**
- MP4
- WebM
- MOV

### Embed (YouTube/Vimeo)
```php
[
  'type' => 'embed',
  'url' => 'https://youtube.com/watch?v=...',
  'title' => 'Video title'
]
```

## Frontend Display

Gallery rendered via `GalleryService::render()`:

```php
$gallery_service = new GalleryService();
echo $gallery_service->render( $service_id, [
  'size' => 'large',
  'thumb_size' => 'thumbnail',
  'class' => 'wpss-gallery',
  'lightbox' => true
] );
```

**Output:**
- Main image displayed large
- Thumbnails below (if multiple images)
- Lightbox functionality (if enabled)
- Video embeds or HTML5 video player

## Image Optimization

**Automatic:**
- Lazy loading (images load as user scrolls)
- Responsive images (WordPress srcset)
- Browser caching

**Manual Recommendations:**
- Compress images before upload (TinyPNG, ImageOptim)
- Use correct format: JPEG for photos, PNG for graphics, WebP for best compression
- Keep files under 500KB for fast loading
- Remove EXIF metadata

**[PRO]** WebP Conversion:
- Automatically converts uploaded images to WebP
- 25-35% smaller file sizes
- Fallback to original for unsupported browsers

## Common Issues

### Image Won't Upload

**Causes:**
- File exceeds 5MB limit
- Unsupported format
- MIME type mismatch
- Ownership verification failed

**Fix:**
1. Compress image to under 5MB
2. Convert to JPG, PNG, GIF, or WebP
3. Ensure no corrupted file headers
4. Verify user is logged in and approved vendor

### Gallery Limit Reached

**Free version limited to 4 additional images.**

**Fix:**
1. Remove unused images
2. Replace low-quality images with better ones
3. Upgrade to **[PRO]** for unlimited images

### Video Not Embedding

**Causes:**
- Unsupported platform (not YouTube/Vimeo)
- Invalid URL format
- Private/restricted video

**Fix:**
1. Use only YouTube or Vimeo URLs
2. Verify URL format: `https://youtube.com/watch?v=VIDEO_ID`
3. Ensure video is public
4. Test URL in browser first

## Data Structure

**Meta Key:** `_wpss_gallery`

**Structure:**
```json
{
  "images": [
    {
      "type": "image",
      "attachment_id": 123,
      "alt": "Sample work"
    }
  ],
  "video": "https://youtube.com/watch?v=abc123"
}
```

**Note:** Wizard stores simplified structure. `GalleryService` supports advanced structure with multiple item types.

## Best Practices

### Image Quality
- Use high-resolution images (1200px+ width)
- Show actual work samples, not stock photos
- Maintain consistent style across gallery
- Include before/after comparisons if applicable

### Gallery Size
- 3-5 images optimal (balance variety with page speed)
- First image most important (after main/featured)
- Order by quality/relevance
- Remove outdated work samples

### Video Usage
- Keep under 3 minutes
- Add captions for accessibility
- Avoid auto-play
- Use as portfolio showcase, not sales pitch

## Related Documentation

- **[Service Wizard](./service-wizard.md)** - Gallery step in wizard
- **[Publishing & Moderation](./publishing-moderation.md)** - Image quality requirements
