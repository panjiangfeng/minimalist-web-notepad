# Minimalist Web Notepad

This is an open source clone of notepad.cc, which is now defunct.

See demo at https://notes.orga.cat or https://notes.orga.cat/whatever.

## Features

- Plain text notes, auto-saved every second while you type.
- **Three view modes**: edit / split / preview (switch at the top right,
  preference remembered; split is the default on wide screens, with scroll sync).
- **Formatting toolbar**: bold, italic, strikethrough, heading, lists, quote,
  inline code, code block, table, link and image upload — buttons wrap the
  selected text in Markdown syntax.
- **Markdown preview**: powered by bundled [marked.js](https://github.com/markedjs/marked)
  and [DOMPurify](https://github.com/cure53/DOMPurify), stored locally in `lib/`.
- **Syntax highlighting** in code blocks via bundled
  [highlight.js](https://highlightjs.org) (GitHub light/dark themes, ~40 common
  languages, auto-detected when no language tag is given).
- **Image paste & drag & drop**: paste a screenshot or drop an image file into
  the editor — it is uploaded automatically and Markdown image syntax
  `![](url)` is inserted at the cursor. Images are stored in `_tmp/i/` and
  served via `index.php?img=<random-name>`. Max size 10 MB,
  types: png/jpg/jpeg/gif/webp/svg.
- **Image storage**: uploaded images are kept permanently by default. Set
  `$image_ttl_days` at the top of `index.php` (e.g. `7`) to auto-purge
  images older than that many days instead.

## Note logic (same as the original notepad)

- Open the site root → you are redirected to a fresh note with a random
  5-character address (e.g. `/k3m9x`).
- Open any address (e.g. `/meeting-notes`) → that note is opened; it is
  created on first save.
- Content is saved automatically about once per second while typing.
- Clearing a note's content deletes it.
- `curl <url>?raw` returns the plain text.

## Quick local test

No web server handy? Use PHP's built-in server with the bundled router:

    php -S localhost:8000 router.php

Set `$base_url = 'http://localhost:8000'` at the top of `index.php`, then
open http://localhost:8000.

## Installation

At the top of `index.php` file, change `$base_url` variable to point to your
site.

Make sure the web server is allowed to write to the `_tmp` directory.

### On Apache

You may need to enable mod_rewrite and set up `.htaccess` files in your site configuration.
See [How To Set Up mod_rewrite for Apache](https://www.digitalocean.com/community/tutorials/how-to-set-up-mod_rewrite-for-apache-on-ubuntu-14-04).

### On Nginx

To enable URL rewriting, put something like this in your configuration file:

If the project resides in the root directory:
```
location / {
    rewrite ^/([a-zA-Z0-9_-]+)$ /index.php?note=$1;
}
```

If the project resides in a subdirectory:
```
location ~* ^/notes/([a-zA-Z0-9_-]+)$ {
    try_files $uri /notes/index.php?note=$1;
}
```

## Branches

To install it with Docker see the [docker](https://github.com/pereorga/minimalist-web-notepad/tree/docker) branch.

A version that allows for encryption using the Web Crypto API is available at the [encryption](https://github.com/pereorga/minimalist-web-notepad/tree/encryption) branch.


## Copyright and license

Copyright 2012 Pere Orga <pere@orga.cat>

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this work except in compliance with the License.
You may obtain a copy of the License at:

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
