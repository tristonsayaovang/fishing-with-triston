CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Recommended modules
* Installation
* Configuration
* FAQ
* Maintainers

INTRODUCTION
------------

"Media Contextual Crop" collection provide a solution to localy overide crop
setting for media.

This module provide the plugin which be the interface for FocalPoint Crop
Plugin.

This plugin which permit to use focalPoint widget for a Contextual Crop,
it need a "use-case" module in order use it.

REQUIREMENTS
------------

* [Media Contextual Cropping API](https://www.drupal.org/project/media_contextual_crop)
for the plugin definition
* [Focal Point](https://www.drupal.org/project/focal_point) for the crop widget.

RECOMMENDED MODULES
------------

You need a use-case module in oder to use focal point widget for a contextual
crop.

In the use-case you will found :

* [Media Contextual Cropping Embed](https://www.drupal.org/project/media_contextual_crop_embed)
which provides a Filter to replace MediaEmbed Core Filter in order to use
contextual crop features in Wysiwyg Media Embed.
* [Media Contextual Cropping Field Formatter](https://www.drupal.org/project/media_contextual_crop_field_formatter)
which provides a formatter in order to use contextual crop in media entity
reference fields.

INSTALLATION
------------

Install this module as you would normally install a contributed
Drupal module. Visit <https://www.drupal.org/node/1897420> for further
information.

CONFIGURATION
-------------

No specific configuration is required, except "nativ" focal point configuration.

Fast Focal point configuration :
    1. Install the module as usual.
    2. Create a crop type (automatically created by focal_point activation )
    3. Create an image style for focal_point crop use "focal point crop" or
"focal point crop and scale" effect

FAQ
---------------

MAINTAINERS
-----------

* Damien ROBERT - <https://www.drupal.org/u/drdam>

Supporting organization:

* SMILE - <https://www.drupal.org/smile>
