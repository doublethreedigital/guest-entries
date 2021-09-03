<!-- statamic:hide -->

![Banner](banner.png)

## Guest Entries

<!-- /statamic:hide -->

This repository contains the source code for Guest Entries. Guest Entries allows your users to perform CRUD operations on the front-end of your site. (basically [Workshop](https://statamic.com/addons/statamic/workshop) from the v2 days)

Guest Entries is a commercial addon, to use it in production, you'll need to [purchase a license](https://statamic.com/guest-entries).

## Installation

1. Install via Composer `composer require doublethreedigital/guest-entries`
2. To publish this addon's configuration file, run: `php artisan vendor:publish --tag="guest-entries-config"`

## Documentation

### Configuration

Guest Entries provides a configuration file that allows you to define the collections you wish for entries to be created/updated within. You will have published this configuration file during the installation process - it'll be located at `config/guest-entries.php`.

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Collections
    |--------------------------------------------------------------------------
    |
    | Configure which collections you'd like to be created/updated with
    | the 'Guest Entries' addon.
    |
    */

    'collections' => [
        'pages' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Honeypot
    |--------------------------------------------------------------------------
    |
    | If you'd like to enable the honeypot, specify the name of the input
    | you'd like to use.
    |
    */

    'honeypot' => false,

];
```

To enable a collection, create an entry in the array, where the key is the handle of the collection and where the value is `true`/`false`, depending on if you wish to enable/disable it.

You may also configure this addon's [Honeypot](#honeypot) feature. `false` will mean the feature is disabled. To enable, you may change this to a field name that will never be entered by a human (as it'll be hidden) but may be auto-filled by a robot.

### Tags

#### Create Entry

```antlers
{{ guest-entries:create collection="articles" }}
    <h2>Create article</h2>

    <input type="text" name="title">
    <textarea name="content"></textarea>

    <button type="submit">Create</button>
{{ /guest-entries:create }}
```

#### Update Entry

```antlers
{{ guest-entries:update collection="articles" id="article-id" }}
    <h2>Edit article</h2>

    <input type="text" name="title" value="{{ title }}">
    <textarea name="content">{{ content }}</textarea>

    <button type="submit">Update</button>
{{ /guest-entries:update }}
```

#### Delete Entry

```antlers
{{ guest-entries:delete collection="articles" id="article-id" }}
    <h2>Delete article</h2>
    <p>Are you 100% sure you want to get rid of this article? It'll be gone forever. Which if you didn't know - is a very long time!</p>

    <button type="submit">DELETE</button>
{{ /guest-entries:delete }}
```

#### Parameters

When using any of the `guest-entries` tags, there's a few parameters available to you:

**`collection` *required***

Every tag will require you to pass in the `collection` parameter, which should be the handle of the collection you want to deal with.

**`id` *sometimes required***

Both the `update` and `delete` tags require you to pass in the ID of the entry you want to work with.

**`redirect`**

You may specify a URL to redirect the user to once the Guest Entry form has been submitted.

**`error_redirect`**

You may specify a URL to redirect the user to once the Guest Entry form has been submitted unsuccessfully - commonly due to a validation error.

**`request`**

You may specify a Laravel Form Request to be used for validation of the form. You can pass in simply the name of the class or the FQNS (fully qualified namespace) - eg. `ArticleStoreRequest` vs `App\Http\Requests\ArticleStoreRequest`

#### Variables

If you're using the update/delete forms provided by Guest Entries, you will be able to use any of your entries data, in case you wish to fill `value` attributes on the input fields.

### Honeypot

Guest Entries includes a simple Honeypot feature to help reduce spam via your front-end forms. Documentation around configuring can be seen under '[Configuration](#configuration)'.

Once you've enabled the Honeypot, ensure to add the field to your forms, like so:

```antlers
{{ guest-entries:create collection="articles" }}
    <h2>Create article</h2>

    <input type="text" name="title">
    <textarea name="content"></textarea>

    <input type="hidden" name="zip_code" value=""> <!-- This is my honeypot -->

    <button type="submit">Create</button>
{{ /guest-entries:create }}
```

## Security

From a security perspective, only the latest version will receive a security release if a vulnerability is found.

If you discover a security vulnerability within Guest Entries, please report it [via email](mailto:security@doublethree.digital) straight away. Please don't report security issues in the issue tracker.

## Resources

* [**Issue Tracker**](https://github.com/doublethreedigital/guest-entries/issues): Find & report bugs in :addonName
* [**Email**](mailto:help@doublethree.digital): Support from the developer behind the addon

<!-- statamic:hide -->

---

<p>
<a href="https://statamic.com"><img src="https://img.shields.io/badge/Statamic-3.0+-FF269E?style=for-the-badge" alt="Compatible with Statamic v3"></a>
<a href="https://packagist.org/packages/doublethreedigital/guest-entries/stats"><img src="https://img.shields.io/packagist/v/doublethreedigital/guest-entries?style=for-the-badge" alt=":addonName on Packagist"></a>
</p>

<!-- /statamic:hide -->
