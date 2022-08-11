local
CONTENTS OF THIS FILE
=====================
* INTRODUCTION
* REQUIREMENTS
* INSTALLATION
* CONFIGURATION

INTRODUCTION
============
This module allows image files to be optimised using the Optidash.ai web service
at http://optidash.ai. After the initial configuration of a Optidash.ai account, an
administrator of your site can then configure image optimize pipelines with the
Optidash optimize processor.

REQUIREMENTS
============
 * Image Optimize module https://drupal.org/project/imageapi_optimize

INSTALLATION
============
 * Install via composer

CONFIGURATION
=============

1. Create a new pipeline at /admin/config/media/imageapi-optimize-pipelines/add.

2. Choose 'Optidash optimize' in the 'Select new processor' list and add it as a
   processor.

3. Enter the API details from your Optidash.ai account.

4. Select lossy compression for smaller filesizes, if desired.

5. Either change a single image style to use your new pipeline or change the
   sitewide default to use it at /admin/config/media/image-styles

Read more about 'Working with images in Drupal 7, 8 & 9 here:

https://drupal.org/documentation/modules/image

