# ChurchSuite Events

Pull ChurchSuite calendar JSON into WordPress and surface events via a custom post type that works with Query Loop blocks.

## Setup
- Activate the plugin.
- Go to **Settings → ChurchSuite Events** and enter your `account_id` (e.g. `yourchurch`).
- Adjust cache duration if needed and click **Save**.
- Click **Sync now** to pull events immediately; background sync runs on a schedule.

## Using in the Site Editor
- Add a Query Loop block and choose the `churchsuite_event` post type, or insert the built-in pattern **“ChurchSuite Events List.”**
- Featured images: if the ChurchSuite feed provides an image URL, the plugin will download it and set it as the event’s featured image (skips if you already set one).
- Categories: events map to a `ChurchSuite Categories` taxonomy. You can edit these terms under Events → ChurchSuite Categories and set a category image (term meta).
- If an event has no featured image, the Query Loop and single views will automatically fall back to the first assigned category image.
- To display category info on event pages/templates, use shortcodes in blocks:
  - `[churchsuite_event_category field="description"]`
  - `[churchsuite_event_category field="name"]`
  - `[churchsuite_event_category field="color"]`
  - `[churchsuite_event_category field="image" size="medium"]` (URL only)
  - `[churchsuite_event_category field="image_tag" size="large"]` (renders an `<img>`; falls back to featured image if present)
- Fields available as custom fields for blocks:
  - `_churchsuite_event_start`
  - `_churchsuite_event_end`
  - `_churchsuite_event_location`
  - `_churchsuite_event_category`
  - `_churchsuite_event_registration_url`

## Templates
Fallback templates for single and archive event views are bundled; your theme templates take precedence if present.
