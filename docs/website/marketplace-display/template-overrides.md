# Customizing the Look

Want to change how service cards, vendor profiles, or other marketplace elements look? WP Sell Services uses a template system that lets you customize the design through your theme -- without touching the plugin files.

---

## How It Works

The plugin comes with default template files that control how things look on the frontend. You can override any of these by copying them into your theme. Your custom version takes priority, and your changes survive plugin updates.

**The priority order:**

1. Your child theme's `wp-sell-services/` folder (checked first)
2. Your parent theme's `wp-sell-services/` folder
3. The plugin's default templates (fallback)

---

## What You Can Customize

| Template | What It Controls |
|----------|-----------------|
| Service card | How services appear in grid listings (thumbnail, price, rating, vendor name) |
| Single service page | The full service detail page layout |
| Service archive | The services catalog/browse page |
| Vendor profile | The public vendor profile page |
| Order details | The order detail view |

---

## How to Customize a Template

### Step 1: Create the folder

In your theme directory, create a folder called `wp-sell-services/`. If you are using a child theme (recommended), put it there.

```
your-theme/
  wp-sell-services/
```

### Step 2: Copy the template

Find the template you want to customize in the plugin's `templates/` folder and copy it into your theme's `wp-sell-services/` folder. Keep the same file name and subfolder structure.

**Example:** To customize service cards, copy:
```
wp-content/plugins/wp-sell-services/templates/content-service-card.php
```
To:
```
wp-content/themes/your-theme/wp-sell-services/content-service-card.php
```

### Step 3: Edit your copy

Open the file in your theme and make your design changes. Your customized version will be used automatically.

### Step 4: Test

Clear all caches (site cache, browser cache), then visit the relevant page to confirm your changes appear. Test on both desktop and mobile.

---

## Important Tips

**Always use a child theme.** If you put overrides in a parent theme, they will be lost when the theme updates. Child themes are safe from updates.

**Copy the entire file.** Do not create a partial template -- copy the full file and then modify the parts you want to change.

**Keep templates updated.** After major plugin updates, check if the default templates changed. If they did, you may need to update your overrides to match any new features or structure.

**Use hooks when possible.** For small additions (like adding a badge or extra text), the plugin provides action hooks that let you insert content at specific points without overriding the entire template. This is less maintenance than a full template override.

---

## What Not to Do

- **Do not edit plugin files directly.** Your changes will be lost on the next plugin update.
- **Do not remove essential functionality.** Keep the core output intact and add or restyle around it.
- **Do not forget to test.** Always check your changes on different screen sizes and browsers.

---

## Troubleshooting

**Changes not appearing?**
- Verify the file path is exactly `your-theme/wp-sell-services/{template-name}.php`
- File names are case-sensitive -- they must match exactly
- Clear all caches (object cache, page cache, CDN, browser)
- Check file permissions (644 for files, 755 for directories)

**Layout broken after plugin update?**
The plugin's default template structure may have changed. Compare your override with the new default and merge any structural changes.

**Styling conflicts with your theme?**
Some themes apply global styles that affect marketplace elements. Use your theme's custom CSS area or a child theme stylesheet to adjust.
