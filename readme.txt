=== WooCommerce Clover Payment Gateway ===
Contributors: kelvinzheng
Donate link: http://www.clover.com
Tags: mobile, payment
Requires at least: 2.0.2
Tested up to: 3.3.1
Stable tag: 1.1

1-tap buying for smartphone mCommerce

== Description ==

<p>Mobile checkout currently converts at 1/10th your web checkout flows.  Clover enables your mobile customers to convert at 2-3X your existing checkout flows.  We also offer instant cash back incentives to your customers for using Clover for the first time.</p>

<p>Clover is giving away <a href="https://www.clover.com/100k">$100K</a> to the customers of our early developer adopters</p>

<p>
To use this plugin, please create an account at http://www.clover.com/dashboard.  You can also learn more about the merchant flows at http://www.clover.com.  
</p><p>
Please contact us for any custom work you might need to integrate Clover into your Wordpress project at <a href="mailto:support@clover.com">support@clover.com</a>
</p>

== Installation ==

This module is for WooCommerce Version 1.x only. It will NOT work with earlier versions of WooCommerce.

Step 1. Pre-Installation
------------------------
If you have not already done so, install WordPress, then install (and activate) WooCommerce.  Also, make sure you have created your Clover merchant account here: http://www.clover.com/dashboard

Step 2. Upload Clover Module files
------------------------------------
Upload the woocommerce-gateway-clover folder and its contents to the wp-content\plugins folder of your WordPress installation.

Step 3. Login and Configure
---------------------------
a) Login to your WordPress admin area.
b) on the left navigation, click "Plugins".
c) click the "Activate" link under the "WooCommerce CardSave Redirect Gateway".
d) on the left navigation, select "WooCommerce" > "Settings".
e) Click the "Payment Gateways" tab.
f) Click the "Clover" tab.
d) Click the tick alongside the "Clover" option, and click the "Save Changes" button under the list of gateways.
e) on the right hand column, using the "Payment Gateway" drop down menu, select "Clover". Enter the following information into the boxes provided.

Title: This is name displayed alongside the payment option on the checkout page.
Description: This is shown in the box under the module, once it has been selected.
Merchant ID: As provided in the Clover Dashboard.
Merchant Secret: As provided in the Clover Dashboard.

Click the "Save changes" button when you have finished.

== Frequently Asked Questions ==

You should use WooCommerce admin tools to manage these orders instead of the dashboard features we provide on our site, to keep the information properly synchronized.

"Auto Accept Order" on the setting page is unchecked
----------------------------------------------------

a) When a customer places an order using Clover we only authorize their credit card until the order is accepted. 
b) You can accept the order by changing the order status from "Processing" to "Completed".
c) When order is accepted, their card will be officially charged. 
d) Before a order is accepted, you can cancel the order by changing Order status to "Canceled", the customer's card will never be charged, although they may still see the original authorization from their placement of the order.  

"Auto Accept Order" on the setting page is checked
----------------------------------------------------
a) A customer order will automatically be accepted and their card will be charged immediately every time when the order was placed.
b) Change order status to "Canceled" will not refund customer's credit card. 

Refund
------
If you need to refund the order that has been accepted, you need to change the order status from "Completed" to "Refund". Their card will be credited back the original amount at that time. 

== Screenshots ==

1. screehshots are coming

== Changelog ==

= 1.1 =
 * Fixed the bug that preventing using non-clover payment method from the purchase.

= 1.0 =
1. First product release.

== Upgrade Notice ==

= 1.1 =
 * Fixed the bug that preventing using non-clover payment method from the purchase.
