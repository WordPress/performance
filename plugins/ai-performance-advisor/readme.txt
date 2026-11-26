=== AI Performance Advisor ===

Contributors: wordpressdotorg
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag:   1.0.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, ai, site health, recommendations, optimization

Actionable, AI-generated performance tuning recommendations for your site, surfaced in a Site Health tab.

== Description ==

AI Performance Advisor analyzes your site using real configuration and performance data and asks a connected AI provider for specific, actionable performance recommendations. Results appear in a dedicated **AI Performance Advisor** tab on the Site Health screen.

The analysis runs only when you click **Analyze my site**, so you remain in control of when (and how often) your configured AI provider is called.

= What gets analyzed =

* WordPress, server, PHP, and database details from Site Health (private fields are excluded).
* Active plugins, the active theme, and version information.
* Performance-related Site Health test results.
* A PageSpeed Insights (Lighthouse) snapshot of your home page (optional; can be disabled in settings).
* Optimization Detective URL Metrics, when that plugin is active.

= Privacy =

No analysis is sent anywhere until you explicitly start it. Sensitive Site Health fields marked private (keys, salts, secrets) are never included. Site owners and developers can adjust exactly what is sent using the `aipa_context` filter.

= Requirements =

* WordPress 7.0 or higher (for the AI Client and Connectors APIs).
* At least one connected AI provider that supports text generation.

= Suggest-only =

This version only *suggests* changes and links you to where to make them. It does not modify any settings. The recommendation format is designed so that a future version can offer to apply changes for you as the WordPress Abilities API matures.

== Installation ==

1. Install and activate the plugin.
2. Connect an AI provider under Settings (Connectors).
3. Visit Tools → Site Health → AI Performance Advisor and click "Analyze my site".

== Frequently Asked Questions ==

= Does this cost anything? =

The analysis calls whatever AI provider you have connected, so any usage costs are determined by that provider. PageSpeed Insights is free at the low volumes this plugin uses.

= Does it change my site automatically? =

No. This version only provides recommendations. It never changes settings on your behalf.

== Changelog ==

= 1.0.0 =

* Initial release.
