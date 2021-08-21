# wp-metacopy

Copy metadata from one post to another

## Overview

This plugin allows you to copy metadata from posts, pages and even custom posts types with a single click and allows you to select which fields you want to copy.

Has integration with [Advanced Custom Fields](https://www.advancedcustomfields.com/) for better results if you're using that plugin (and you should, it's very good).

## Requirements

- WordPress 5.x
- PHP 5.6+

## Installation

Clone/download the repo and install the plugin by unziping the contents of the zip into the `wp-content/plugins` folder of your WP installation, rename the resulting folder to `wp-metacopy` and activate it through the WordPress admin dashboard.

Once installed you will see a 'MetaCopy' entry on the left-side menu, click it and you will see the options page. There you will be able to specify on which content the MetaCopy command will be available.

Then just head up to 'Posts', 'Pages' or your custom post type listing and hover the element you want to copy metadata from, then click the MetaCopy action and select which fields you want to copy and where you want them to be copied.

You may also copy the contents of the post should you want to, just mark the checkbox right before the Copy metadata button and the contents will also be carried over.

Profit!

**Word of warning: This plugin effectively overwrites the metadata and/or contents of the target post, so please use it with care. I will not be responsible for data loss or corruption derived from the use of this software, so use it at your own risk.**

## Licensing

MIT licensed

Author: biohzrdmx [<github.com/biohzrdmx>](https://github.com/biohzrdmx)