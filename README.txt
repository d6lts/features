$Id$

Features for Drupal 6.x

WARNING
-------
Features is currently a proof-of-concept module -- it is (at time of
writing) less than a week old and should be of interest only to
developers and reckless Drupal enthusiasts. If you're looking for
something to use on your production site, you'd best bookmark and
come back later.

Installation
------------
Features can be installed like any other Drupal module -- place it in
the modules directory for your site and enable it on the
admin/build/modules page.

What is a feature?
------------------
In short, each feature is a module. A feature differs from most other
modules in that it is a collections of exportables from other modules
configured to work together. Features, in short, capture a specific
use case by taking advantage of larger, generalized, Drupal tools.
Examples of features might be:

* A blog
* A pressroom
* An image gallery
* An e-commerce t-shirt store

And so on. See API.txt for more information about the guts of feature
modules.

Basic usage
-----------
Features is geared toward usage by developers and site builders. It
is not intended to be used by the general audience of your Drupal site.
Features provides tools for accomplishing two important tasks:

Task 1: Export features

You can create features in Drupal by using site building tools. The ones
that currently integrate against Features are node, cck, views, context,
and imagecache. Other modules may integrate if they use common Drupal APIs
(for example, you can include a block from another module in your feature
or a different CCK field formatter), but modules that do things wildly
on their own or have problematic APIs will not work well.

Once you've created a feature, you can export it into a feature module by
using the export inteface at admin/features/export. Currently the export
interface requires you to start from a context -- however the API has
been designed that there might be other starting points for generating
features in the future.

Task 2: Manage features

The features module also provides a way to manage features through a more
targeted interface than admin/build/modules. The interface at admin/features
shows you only feature modules, and will also inform you if any of their
components have been overridden. If this is the case, you can also re-export
features to bring the module code up to date with any changes that have
occurred in the database.

For developers
--------------
Please read API.txt for more information about the concepts and integration
points in the Features module.

Maintainers
-----------
yhahn (Young Hahn)
jmiccolis (Jeff Miccolis)
