=== Alpaca Takeover ===
Contributors: rareview
Tags: kanban, issue tracker,project board, access control
Requires at least: 6.9
Requires PHP: 8.0
Requires Plugins: alpaca
Stable tag: 0.1.0
License: GPL-2.0-or-later

Turns a WordPress site into a login-protected, self-hosted kanban platform.

== Description ==

Alpaca Takeover strips away the standard WordPress content authoring and rendering experience, turning a standard WordPress site into a self-contained kanban hub.

The admin experience is reduced to only the screens and settings needed for the kanban experience. Standard admin screens like Dashboard, Posts, Pages, Media, Comments, Appearance, and Tools are disconnected, and direct access to those admin screens is denied.

Any logged-in site user can view the Project Board. Issue creation and updates retain Alpaca's existing capabilities; issue comments currently share Alpaca's issue-creation permission.

All front-end rendering is blocked. The site's only presence is the admin kanban interface.

But be aware: files served _directly_ by the web server, such as uploaded media, may still be accessible by URL and require separate web-server rules to protect.

== Appearance ==

Choose a site-wide theme from Configure > Appearance. Each theme uses one fixed color mode: Camelid is light and Othello is dark. Themes preserve the Project Board navigation and screen layout while varying fonts, colors, spacing, controls, borders, and shadows.

Plugins can register or enforce themes and provide theme stylesheets. See `docs/theme-development.md` for the theme registration API and CSS variables.

== Installation ==

1. Install and activate Alpaca Issue Tracker.
2. Install and activate Alpaca Takeover.
3. Assign users the existing `edit_posts` capability if they should create or update issues or comment on issues. All site users can view the board.

== Requirements ==

* WordPress 6.9 or later.
* PHP 8.0 or later.
* Alpaca Issue Tracker installed in the `alpaca` plugin directory and active.