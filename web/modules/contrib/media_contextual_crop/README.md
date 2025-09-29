CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Recommended modules
* Installation
* FAQ
* Maintainers

INTRODUCTION
------------

"Media Contextual Crop" collection provide a solution to locally override crop
setting for media.

This module provide the 2 main technical elements :

 * A plugin manager used to make interfaces with crop plugin (Focal Point or
Image Widget Crop).
 * A service used to alter Drupal Core Style generation and force Drupal Core
to generate multiple version for 1 image and 1 style.

This module does nothing on is own.

REQUIREMENTS
------------

This module requires CROP module.

RECOMMENDED MODULES
------------

The Media Contextual Crop collection are structured in 2 types of modules :

**1- In the Interfaces you will found :**

* [Media Contextual Cropping Focal Point Adapter](https://www.drupal.org/project/media_contextual_crop_fp_adapter)
which provides a plugin to interface for Focal Point Crop module
* [Media Contextual Cropping Image Widget Crop Adapter](https://www.drupal.org/project/media_contextual_crop_iwc_adapter)
which provides a plugin to interface for Image Widget Crop module

**2- In the use-case you will found :**

* [Media Contextual Cropping Embed](https://www.drupal.org/project/media_contextual_crop_embed)
which provides a Filter to replace MediaEmbed Core Filter in order to use
contextual crop features in Wysiwyg Media Embed.
* [Media Contextual Cropping Field Formatter](https://www.drupal.org/project/media_contextual_crop_field_formatter)
which provides a formatter in order to use contextual crop in media entity
reference fields.

INSTALLATION
------------

Install this module as you would normally install a contributed
Drupal module. Visit [main documentation](https://www.drupal.org/node/1897420) for further
information.
This module can be installed automatically by composer dependency check.

FAQ
---------------

**Q: How did you force the core ImageStyle process to generate multiple time
one couple Image/Style ?**

**A :** This is the "dirty point". The bigger problem with
this kind of functionality (Manage multi render case for 1 image in the same
imageStyle) are the "Core ImageStyle" processing.
The core DOES NOT allow to a image to have multiple derivatives from a unique
ImageStyle.

In order to avoid this problem, we make don't use the native image formatter
and the native derivative URL (/styles/[style_name]/[image_path]).
The "contextual Image" formatter, create a CROP item specific for the context
need, and create a specific "contextual derivative" URL.

This URL (/contextual/styles/[style_name]/[context_id]/[image_path]) are
processed by a controller as near as possible to the native image::deliver.

All ImageStyle flush & cleaning processes are now fully implemented in order
to limit ghost and unused data/images.

**Q: There is a whole documentation ?**

**A**: The documentation had been updated : [here](https://www.drupal.org/docs/contributed-modules/how-use-media-multicroping-11x-branch/preparation-configuration)

MAINTAINERS
-----------

* Damien ROBERT - <https://www.drupal.org/u/drdam>

Supporting organization:

* SMILE - <https://www.drupal.org/smile>

if you want to support my work on this feature : https://ko-fi.com/drdamlab
