This contains the source files for the "*Omnipedia - Commerce*" Drupal module,
which provided commerce-related functionality for
[Omnipedia](https://omnipedia.app/).

**This module is no longer used nor is any further development planned.**

⚠️ ***[Why open source? / Spoiler warning](https://omnipedia.app/open-source)***

----

# Requirements

* [Drupal 9.5 or 10](https://www.drupal.org/download)

* [Composer](https://getcomposer.org/)

## Drupal dependencies

Before attempting to install this, you must add the Composer repositories as
described in the installation instructions for these dependencies:

* The [`omnipedia_access`](https://github.com/neurocracy/drupal-omnipedia-access) and [`omnipedia_core`](https://github.com/neurocracy/drupal-omnipedia-core) modules.

----

# Major breaking changes

The following major version bumps indicate breaking changes:

* 4.x:

  * Requires Drupal 9.5 or [Drupal 10](https://www.drupal.org/project/drupal/releases/10.0.0).

  * Increases minimum version of [Hook Event Dispatcher](https://www.drupal.org/project/hook_event_dispatcher) to 3.1, removes deprecated code, and adds support for 4.0 which supports Drupal 10.
