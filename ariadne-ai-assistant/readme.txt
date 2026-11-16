=== Ariadne AI Assistant ===
Tags: ai chatbot, chatbot, woocommerce, customer support, live chat
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Puts the Ariadne AI assistant on your site or WooCommerce shop and connects it to your Ariadne account with one click, with no code to copy.

== Description ==

Ariadne is an AI assistant for websites and online shops. It answers your visitors from your own products, pages and business details, and collects leads and booking requests for you.

This plugin adds the Ariadne chat to your WordPress or WooCommerce site and links the site to your Ariadne account with one click:

* **Connect with one click.** Press "Connect to Ariadne", log in or create an account, and you are back in WordPress with the assistant on your site.
* **Preview first.** A new Ariadne site starts in preview mode: only you and your team see the assistant until you open it to everyone from the Ariadne dashboard.
* **Made for shops.** With WooCommerce active, Ariadne knows your site is a shop and looks for your products itself.
* **Works with speed plugins.** The chat script carries the opt-out attributes of common caching and optimisation plugins, so they leave it alone.
* **An off switch.** Hide the assistant from your site at any time, without disconnecting.

You need an Ariadne account; you can create one while connecting. Plans and prices: [ariadnebot.com/pricing](https://ariadnebot.com/pricing/).

== Installation ==

1. Download the plugin from [ariadnebot.com/downloads/ariadne-ai-assistant.zip](https://ariadnebot.com/downloads/ariadne-ai-assistant.zip).
2. In WordPress go to Plugins › Add New › Upload Plugin, choose the file and press "Install Now", then "Activate".
3. Go to Settings › Ariadne and press "Connect to Ariadne".
4. Log in or create an account, with Google or with email.
5. Press "Connect". You are back in WordPress and the assistant is already on your site, but at first only you can see it.
6. Open the Ariadne dashboard to see what Ariadne found on your site and to try the assistant.
7. When everything is as you want it, press "Show it to everyone" on the dashboard's Overview. From then on every visitor sees it.

Step-by-step help: [ariadnebot.com/help/wordpress](https://ariadnebot.com/help/wordpress/).

== Frequently Asked Questions ==

= Do I need an Ariadne account? =

Yes. You can create one while connecting.

= Why don't my visitors see the assistant? =

A site made by Connect starts in preview mode, so only you and your team see it. Open the Ariadne dashboard and press "Show it to everyone" on the Overview. Also check that "Show the assistant on my site" is ticked under Settings › Ariadne.

= I use a caching or speed plugin and the chat button does not show. =

Clear its cache. If the button still does not show, exclude `loader.js` from the plugin's JavaScript options (combine, defer, delay).

= Connect stops with an error. =

A security plugin may block the return to `wp-admin/admin-post.php`; allow it and press "Connect to Ariadne" again. If Connect is not possible on your site, copy the site key from the Install page of the Ariadne dashboard and paste it under Settings › Ariadne › Advanced.

= I created my account in another browser or on my phone. =

Press "Connect to Ariadne" again: you go straight to the Connect page, already signed in.

= What does Disconnect do? =

It removes the assistant from this site and forgets the site key. Your Ariadne account and its data stay as they are.

= What happens when I delete the plugin? =

Deleting it from the Plugins screen removes every setting it saved.

== External services ==

This plugin connects your site to Ariadne ([ariadnebot.com](https://ariadnebot.com/)), the AI assistant service it adds to your site. It is needed for the plugin to do anything; nothing is sent before you press "Connect to Ariadne" or paste a site key.

* **When you press "Connect to Ariadne"**, your browser opens the Ariadne dashboard at `ariadnebot.com/admin/connect/wordpress` with your site's address, the address of your `wp-admin/admin-post.php`, your site's title, whether WooCommerce is active, and two random values for this connection only (a state and a challenge). You then log in or create an account there.
* **When you come back from the dashboard**, your site's server sends the one-time code it received and the matching random verifier to `api.ariadnebot.com`, and gets back your Ariadne site's public site key, the addresses of the Ariadne chat and API, the site's name in Ariadne and its dashboard address. These are saved in your WordPress settings.
* **On every page of your site's front end** while connected and switched on, your visitors' browsers load the chat script from `ariadnebot.com/widget/loader.js` and talk to `api.ariadnebot.com`. When a visitor opens the chat, it sends the messages they type, the page they are on, their language and device type, and anything they choose to enter in a form in the chat (for example an email address for a booking request). The chat keeps the conversation in the visitor's browser for a few hours so it survives a page change.

Ariadne's [Terms of service](https://ariadnebot.com/terms/) and [Privacy policy](https://ariadnebot.com/privacy/) (in Greek: [Όροι χρήσης](https://ariadnebot.com/el/terms/), [Πολιτική απορρήτου](https://ariadnebot.com/el/privacy/)).

== Changelog ==

= 1.0.1 =
* The plugin is now called "Ariadne AI Assistant" (wordpress.org does not allow "WordPress" in a plugin's name). Nothing else changed.

= 1.0.0 =
* First release: Connect with one click, the chat on the front end with the opt-outs for speed plugins, Disconnect, an on/off switch, a site key field for sites that cannot use Connect, and a Greek translation.

== Upgrade Notice ==

= 1.0.1 =
The new name, "Ariadne AI Assistant". Your settings and connection stay as they are.

= 1.0.0 =
First release.
