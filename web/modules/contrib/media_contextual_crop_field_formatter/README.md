CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Recommended modules
* Installation
* Configuration
* How use it
* FAQ
* Maintainers

INTRODUCTION
------------

"Media Contextual Crop" collection provide a solution to localy override crop
setting for media.

This module provide a way to push contextualize crop information
to image Styling stack.

This module provide a Contextual Crop "use-case", but need a Contextual Crop
Plugin in ordre to show a crop widget.

REQUIREMENTS
------------

* [Media Contextual Cropping API](https://www.drupal.org/project/media_contextual_crop)
for the plugin definition
* [Media Library Media Modify](https://www.drupal.org/project/media_library_media_modify)
for saving contextual media modification. Waiting
[Override media fields from the reference field](https://www.drupal.org/project/drupal/issues/3023807)
to be port
* Drupal Core Media

RECOMMENDED MODULES
------------

You need a Crop widget for Contextualisation

In the Interfaces you will found :

* [Media Contextual Cropping Focal Point Adapter](https://www.drupal.org/project/media_contextual_crop_fp_adapter)
which provides a plugin to interface for Focal Point Crop module
* [Media Contextual Cropping Image Widget Crop Adapter](https://www.drupal.org/project/media_contextual_crop_iwc_adapter)
which provides a plugin to interface for Image Widget Crop module

INSTALLATION
------------

Install this module as you would normally install a contributed
Drupal module. Visit <https://www.drupal.org/node/1897420> for further
information.

CONFIGURATION
-------------

* Activate the module
* Configure the media image view mode
Go to the view mode, and on the image field, choose the image style configured
with a crop effect (see readme of interfacing module choosed)
* In your entity bundle (content type for the exemple)
  * Add a "Reference / media with contextual modifications" instead of the
  standard "Reference / media" and configure it as usual media reference, and
  target a image media bundle.
![](https://www.drupal.org/files/node_field.png)

* In your media entity form mode configuration
  * Replace native "image" field widget by the Crop plugin dedicated widget :
  "Image (Focal Point)" or "ImageWidget Crop"

[Reference](https://www.drupal.org/docs/contributed-modules/how-use-media-multicroping-11x-branch/preparation-configuration#s-contextual-cropping-for-media-embed-in-wysiwyg)

HOW USE IT
----------

Create a content from the content type configured.
In the media reference field, add a media in the field.
Edit the field after, and in the modal you can contextualize the crop.
![](https://www.drupal.org/files/field_contextual.png)

Save the content, you can see the crop contextualized

![](https://www.drupal.org/files/effect_0.png)

FAQ
----


MAINTAINERS
-----------

* Damien ROBERT - <https://www.drupal.org/u/drdam>

Supporting organization:

* SMILE - <https://www.drupal.org/smile>
