# Media Library Media Modify

This module provides:
 - A field type to add contextual overrides to media reference fields
 - A views field plugin to open an edit form from the media library
 - An option to replace the multiple single forms that appear after a new media item was created, by a single form which will apply the form values to all newly created media items.

## Installation
### Contextual overrides

Add a field "Media with contextual modifications" to your content type and
choose the "Media library extra" form widget.

#### Caution

In case you use content translation, make sure the media_library_media_modify fields are translatable.
Otherwise, you might experience some unexpected behaviors.

### Edit media items from the media library

Add the "Edit link for the Media Library" field to your media library view in
the widget display. You can configure which fields are shown using the
"media_library" form display.
