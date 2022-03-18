[Back to overview](./README.md)

# Version support policy

As outlined in the [plugin announcement post](https://make.wordpress.org/core/2022/03/07/the-performance-lab-plugin-has-been-released/), the Performance Lab plugin is a collection of performance-related "feature projects" for WordPress core. These feature projects are represented in this plugin as individual standalone [modules](./Writing-a-module.md).

All modules bundled in the Performance Lab plugin are at different stages of development and may be merged into WordPress core at different points. This document describes the version support policy for the different modules, with a special focus on backward compatibility.

## WordPress core versions

**The Performance Lab plugin commits to supporting the latest stable version of WordPress core.** With that said, the minimum WordPress version requirement will not be bumped unnecessarily, so realistically the plugin will usually support the latest _two_ stable versions of WordPress core. If or once a feature that is only available in the latest WordPress core stable release is required for a plugin module, the minimum requirement of the plugin will be bumped to that version.

Supporting a greater array of older WordPress core versions would be detrimental for the plugin, as it is primarily intended for beta testing and often requires using features that have only been introduced in a recent WordPress core version.

It needs to be clarified though that **the Performance Lab plugin will never require an alpha or beta version of a future WordPress core release**. This policy ensures that sites using the latest stable version of WordPress will always be able to use the plugin as well.

## Removal of modules

Once a module has been merged into WordPress core, there are two potential options for how to proceed with the module:
* If module development is considered completed and continuing development within WordPress core is preferred, the module should be removed from the plugin eventually.
* If module development can continue in the plugin based on the parts that have already been merged into WordPress core, only the parts of the module that were merged should be removed from the plugin eventually.

Whichever of the two options applies to a module, (critical functionality of) standalone modules in the Performance Lab plugin must never be removed in a way that would break sites which rely on the module's current feature set.

Therefore, **the Performance Lab plugin commits to only removing a module in combination with bumping the minimum WordPress version requirement to the same stable core release that the merged module code was published with**. This is best clarified with an example:
* If a module gets merged during development of WordPress 6.0, the module will not be removed from the plugin immediately.
* Only after WordPress 6.0 has been released, the module can be removed from the plugin.
* The first plugin version in which the module is no longer present **must** bump the minimum WordPress version requirement to 6.0.

The above policy ensures that sites which rely on the feature will never see that feature removed, even if they do not update to the latest WordPress core release. Because the Performance Lab plugin limits removing merged modules to bumping the WordPress core version requirement, plugin versions without the module would then no longer surface to sites on outdated WordPress core versions, keeping their behavior intact.

Obviously, the recommendation is to keep both WordPress core and the Performance Lab plugin up to date for the best experience.
