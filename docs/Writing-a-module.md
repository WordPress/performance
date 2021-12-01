[Back to overview](./README.md)

# Writing a module

Modules in the performance plugin share various similarities with WordPress plugins:

* They must have a slug, a title, and a short description.
* They must function 100% standalone.
* They must have an entry point file that initializes the module.
* Their entry point file must contain a specific header comment with meta information about the module.

Every module surfaces on the admin settings page of the performance plugin, where it can be enabled or disabled.

## Module requirements

* The production code for a module must all be located in a directory `/modules/{module-slug}` where `{module-slug}` is the module's slug.
* The entry point file must be called `load.php` and per the above be located at `/modules/{module-slug}/load.php`.
* The `load.php` entry point file must contain a module header with the following fields:
    * TODO.
* The module must neither rely on any PHP code from outside its directory nor on any external PHP code. If relying on an external PHP dependency is essential for a module, the approach should be evaluated and discussed with the wider team.
* The module must use the `performance-lab` text domain for all of its localizable strings.
* All global code structures in the module PHP codebase must be prefixed (e.g. with a string based on the module slug) to avoid conflicts with other modules or plugins.
* All test code for a module (e.g. PHPUnit tests) must be located in a directory `/tests/modules/{module-slug}` where `{module-slug}` is the module's slug (i.e. the same folder name used above).
* The module must adhere to the WordPress coding and documentation standards.

## Module recommendations

* Modules should be written with a future WordPress core merge in mind and therefore use coding patterns already established in WordPress core. For this reason, using PHP language features like autoloaders, interfaces, or traits is discouraged unless they are truly needed for the respective module.
* Modules should always be accompanied by tests, preferably covering every function and class method.
* Modules should refrain from including infrastructure tooling such as build scripts, Docker containers etc. When such functionalities are needed, they should be implemented in a central location in the performance plugin, in a way that they can be reused by other modules as well - one goal of the performance plugin is to minimize the infrastructure code duplication that is often seen between different projects today.

## Example

The following is a minimum module entry point file `/modules/my-module/load.php` (i.e. the module slug is "my-module"):

```php
<?php

/**
 * Displays an admin notice that the module is active.
 */
function my_module_show_admin_notice() {
    ?>
    <div class="notice notice-info">
        <p>
            <?php esc_html_e( 'The "My Module" module is currently enabled.', 'performance-lab' ); ?>
        </p>
    </div>
    <?php
}
add_action( 'admin_notices', 'my_module_show_admin_notice' );

```
