=== Skittybop ===
Contributors: remwes,cytechltd
Tags: Skittybop, jitsi, video, operators, support
Requires at least: 4.5
Tested up to: 6.6.1
Requires PHP: 5.3
Stable tag: 1.0.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Adds video calls to WordPress!

== Description ==

Skittybop is a WordPress plugin that enables users of a WordPress site to communicate with a pool of operators via video calls using the [Skittybop Video Call](https://skittybop.com) service. You can install and use the plugin by following these steps:

1. Download and install Skittybop using the built-in WordPress plugin installer.
2. Log in as an administrator and create one or more WordPress accounts with the *Operator* role.
3. Log in as any WordPress user and click the red button in the bottom right corner to start a video call with one of the available operators.
4. Log in as an operator to accept incoming video calls [^1].
5. Visit the Skittybop page in the administration panel to view and manage the video call history.

[^1]: Operators who need to stop receiving incoming video calls should log out from WordPress. This action will put them offline and stop notifications.

To unlock the full potential of the Skittybop plugin, you need to create an account and have an active subscription by visiting the [Skittybop API Management Platform](https://app.skittybop.com). For more information about Skittybop video Call service please follow the links below:

* [Skittybop Video Call Service](https://skittybop.com)
* [Skittybop API Management Platform](https://app.skittybop.com)
* [Skittybop Terms and Conditions](https://skittybop.com/terms)

== Installation ==

You can download and install Skittybop using the built-in WordPress plugin installer. If you download Skittybop manually, make sure it is uploaded to "/wp-content/plugins/skittybop/".

== Frequently Asked Questions ==

= If you have any question =

Use the support forum of this plugin.

= Skittybop cannot access my microphone or camera =

Skittybop uses your browser's API to ask for permissions to access your microphone or camera. In case you get an error that your device can not be accessed or used, please check one of the following:

* Another application uses the device.
* Your browsing context is insecure (that is, the page was loaded using HTTP rather than HTTPS).
* You denied access to your browser when you were asked for.
* You have denied globally access to all applications via your browser's configuration

== Screenshots ==

1. Start video call button
2. Pending outgoing video call dialog
3. Accept/Reject incoming video call dialog
4. Ongoing video call dialog
5. Video call history page
6. Video call history filtering
7. Operator status
8. Create an operator account
9. Pop-up video call
10. Pop-up video call window
11. Delete video calls for administrators

== Changelog ==

= 1.0.4 =

* Initial version of the plugin
