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

# Installation

## Composer

### Set up

Ensure that you have your Drupal installation set up with the correct Composer
installer types such as those provided by [the `drupal/recommended-project`
template](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates#s-drupalrecommended-project).
If you're starting from scratch, simply requiring that template and following
[the Drupal.org Composer
documentation](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates)
should get you up and running.

### Repository

In your root `composer.json`, add the following to the `"repositories"` section:

```json
"drupal/omnipedia_commerce": {
  "type": "vcs",
  "url": "https://github.com/neurocracy/drupal-omnipedia-commerce.git"
}
```

### Installing

Once you've completed all of the above, run `composer require
"drupal/omnipedia_commerce:4.x-dev@dev"` in the root of your project to have
Composer install this and its required dependencies for you.

----

# Major breaking changes

The following major version bumps indicate breaking changes:

* 4.x:

  * Requires Drupal 9.5 or [Drupal 10](https://www.drupal.org/project/drupal/releases/10.0.0).

  * Increases minimum version of [Hook Event Dispatcher](https://www.drupal.org/project/hook_event_dispatcher) to 3.1, removes deprecated code, and adds support for 4.0 which supports Drupal 10.
