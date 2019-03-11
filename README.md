# acf-takeout

ACF flexible content modules are the building blocks of our sites, but they're a little bit...not straightforward to mix and match. This is a cute little interface for dynamically combining the code needed to support these modules and getting them into a new site quickly and easily.

The output (and likely more than a few utility classes on the modules) assumes you're using [Zemplate v4](https://github.com/zenman/zemplate/tree/4.0.0).

## Modules

Modules are defined as individual folders, each holding its own required assets. These live in a git submodule:

https://github.com/zenman/acf-takeout-modules

A new module won't show up without at least one json file and one php file (file names are ignored). Modules can also have stylesheets and javascript files that will get passed along when they're included.

* **fields.json** (Required)

  Extract this from the master json file's "layouts" array, right after the quoted layout name so your JSON file looks like:
~~~~
{
	"key": "layout_randomstring",
	"name": "module_slug",
	"label": "Some Helpful Module",
	"display": "block",
	"sub_fields": [{ ... }]
}
~~~~

* **template.php** (Required)

  Look out for any [custom functions](../../issues/4) you may have added which will cause a 500 error when they're not found.

* **style.scss** (Optional)

  Can be an .scss or .css file, but only one per folder will be copied into the resultant zip file.

* **script.js** (Optional)

  The only extension that will actually have the filename passed along. There can be multiple JS files per folder, but they will all end up next to each other in a folder named after the module (so you can't expect the script to end up in our main `scripts` folder, or have multiple files split between two folders).

Submodules are a little weird. (Hopefully this ends up being the right decision.)

~~~~
git submodule update --init --recursive
~~~~

#### Defaults

Default modules have their checkboxes pre-clicked when the page loads, but there's a nifty little trick as well:

Appending  `?defaults` to the URL will automatically trigger a download of just those default modules (as well as the default settings/names).

This is a handy shortcut, but is of special interest for trying to bake a few ACF customizations into [zen-init](https://github.com/zenman/zen-shell-utils#zen-init).

These are defined as a simple array of folder names in a [defaults.json](https://github.com/zenman/acf-takeout-modules/blob/master/defaults.json) file at the root of the folders themselves.

## TODO

There are a few enhancement ideas, and discussions of maybe-bugs in the [issues section](../../issues).

## Changelog

- **2019-03-11**: Initial release
