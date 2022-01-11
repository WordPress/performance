[Back to overview](./README.md)

# Writing a module

Modules in the performance plugin share various similarities with WordPress plugins:

* They must have a slug, a name, and a short description.
* They must function 100% standalone.
* They must have an entry point file that initializes the module.
* Their entry point file must contain a specific header comment with meta information about the module.

Every module surfaces on the admin settings page of the performance plugin, where it can be enabled or disabled.

## Module requirements

* The production code for a module must all be located in a directory `/modules/{focus}/{module-slug}` where `{module-slug}` is the module's slug and `{focus}` is the focus area: an identifier of a single focus (e.g. `images`). This should correspond to a section on the performance plugin's settings page. [See the `perflab_get_focus_areas()` function for the currently available focus areas.](../admin/load.php#L161)
* The entry point file must be called `load.php` and per the above be located at `/modules/{focus}/{module-slug}/load.php`.
* The `load.php` entry point file must contain a module header with the following fields:
    * `Module Name`: Name of the module (comparable to `Plugin Name` for plugins). It will be displayed on the performance plugin's settings page.
    * `Description`: Brief description of the module (comparable to `Description` for plugins). It will be displayed next to the module name on the performance plugin's settings page.
    * `Experimental`: Either `Yes` or `No`. If `Yes`, the module will be marked as explicitly experimental on the performance plugin's settings page. While all modules are somewhat experimental (similar to feature plugins), for some that may apply more than for others. For example, certain modules we would encourage limited testing in production for, where we've already established a certain level of reliability/quality, in other cases modules shouldn't be used in production at all.
* The module must neither rely on any PHP code from outside its directory nor on any external PHP code. If relying on an external PHP dependency is essential for a module, the approach should be evaluated and discussed with the wider team.
* The module must use the `performance-lab` text domain for all of its localizable strings.
* All global code structures in the module PHP codebase must be prefixed (e.g. with a string based on the module slug) to avoid conflicts with other modules or plugins.
* All test code for a module (e.g. PHPUnit tests) must be located in a directory `/tests/modules/{focus}/{module-slug}` where `{module-slug}` is the module's slug and `{focus}` is the focus area (i.e. the same folder names used above).
    * If tests require some test-specific structures (e.g. dummy data or mock classes), those should be implemented in a directory `/tests/testdata/modules/{focus}/{module-slug}`.
* The module must adhere to the WordPress coding and documentation standards.

## Module recommendations

* Modules should be written with a future WordPress core merge in mind and therefore use coding patterns already established in WordPress core. For this reason, using PHP language features like autoloaders, interfaces, or traits is discouraged unless they are truly needed for the respective module.
* Modules should always be accompanied by tests, preferably covering every function and class method.
* Modules should refrain from including infrastructure tooling such as build scripts, Docker containers etc. When such functionalities are needed, they should be implemented in a central location in the performance plugin, in a way that they can be reused by other modules as well - one goal of the performance plugin is to minimize the infrastructure code duplication that is often seen between different projects today.

## Example

The following is a minimum module entry point file `/modules/images/my-module/load.php` (i.e. the module slug is "my-module", and the focus is "images"):

```php
<?php
/**
 * Module Name: My Module
 * Description: Enhances performance for something.
 * Experimental: No
 *
 * @package performance-lab
 */

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
