CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Configuration
 * Custom Solutions
 * Code Recipes
 * Guest Checkout

INTRODUCTION
------------

Commerce Registration Wait List is a submodule of Commerce Registration that integrates with the Registration Wait List submodule of the Registration module. It provides a solution for supporting wait lists within a store that sells registration to events. Site builders with different requirements can use this module as a guide to crafting their own solution.

The "out of the box" solution provided by this module uses a special price resolver that detects when a product variation has reached capacity and will be adding to a wait list. This resolver makes wait listed registrations free during initial checkout. If space becomes available at a later time, the registration is moved off the wait list into "held" status and the customer receives an email with a link to a system generated second order (cart) that can be used to pay for the registration. After checkout of this second order occurs and payment is made, the registration is marked complete. This solution is designed for stores that require users to be registered before they can complete checkout. See the notes in the Guest Checkout section if your store supports anonymous user checkout.

To configure the "out of the box" solution, follow the instructions in the next section. If you need to build your own custom solution, see the Custom Solution and Code Recipes sections below for assistance.

CONFIGURATION
-------------

1. Get your store working correctly without wait list support. It is important to start from a "known good" state with a working store that already incorporates the Commerce Registration module to handle selling registrations. This should include a completed checkout flow and order receipt emails. This will help you to debug any issues that occur as you add wait list support.
1. Enable this module. This will also enable the Registration Wait List module.
1. Edit the relevant registration types and enable and configure both the wait list confirmation email and the space available confirmation email. The wait list confirmation should indicate that if space becomes available, the customer will receive another email with a link that will allow them to pay for the registration and complete their reservation at that time. The space available email should contain the link and should include some instructions to help the customer complete their reservation. See the Code Recipes section below for information on creating the link.
1. Change the display for the relevant product variation types to use "Calculated, with wait list support" as the formatter for the Price field. Use the gear icon to configure the formatter with a special message to your customers indicating that registration for the item is full and they will be placed on a waiting list. This message is only shown when capacity for the item is reached.
1. Configure the Order Summary of the relevant checkout flows to use a Commerce checkout summary view.
1. Change the Order Item: Title field in your Commerce cart, Commerce checkout summary, and Commerce order item table views to use the "Order item title, with wait list support" formatter.
1. Change the template for your order receipt emails to include a wait list indicator for the order items. See the Code Recipes section below for more information.
1. (Optional) Change the Add to Cart form display for the relevant order item types to use "Product variation title (waiting list)" as the field widget for the Purchased entity field.
1. Choose a product to test with and edit the registration settings for one of its registration-enabled product variations. Enable both the wait list and autofill options and choose Held as the autofill state. For testing purposes set a low capacity limit for standard capacity.
1. Test your checkout flow by filling capacity for your test product and then viewing that product. You should see the wait list message configured earlier and the item should be free. Add the item to your cart and checkout. Then increase capacity or cancel a previously completed registration to trigger the autofill process. This should create a second order and an email with a link to complete payment. Complete this second checkout and the associated registration should now be complete. Note that this second checkout must completed within the time period configured for held registrations on the registration type, otherwise the registration will expire and the order item will be removed from the cart.

CUSTOM SOLUTION
---------------

There are two different paths to creating a custom solution. If the "out of the box" solution provided by this module is reasonably close to your requirements, the overrides method is recommended.

**Using Overrides**

With this approach, you install this module, and then use hooks, services and event subscribers in your own custom module to override functionality to meet your requirements. The code recipes below provide some examples.

**Totally Custom**

With this approach, you create your own custom module to meet your requirements, using code in this module as a guide to creating your own solution, but not installing it. The downside is you will no longer be able to easily install upgrades that this module provides over time in future releases.

CODE RECIPES
------------

**Wait List Indicator Twig Extension**

This module includes a wait list indicator filter that can be used within a Twig template. You can add this to any template that has access to an order item. For example, to add this to your order receipt emails, edit your order receipt template and change the line with the order item label to the following:

	<span>{{ order_item.label }}</span> {{ order_item|order_item_waitlist_indicator }}

The indicator will only appear if the order item represents a wait listed registration, otherwise the filter returns an empty string.

By default, the indicator is a span tag containing "(waiting list)". You can change this by copying the wait list indicator template from this module into your theme and modifying it as needed.

Keep in mind this indicator is used in the "Order item title, with wait list support" formatter mentioned in the Configuration section above. So any changes you make to the indicator template will appear during checkout as well, for order items representing wait listed registrations.

**Link to Checkout**

The "space available" email that gets sent after a registration is moved off the wait list should include a link to the new order created to pay for the registration. The following HTML is an example of this link:

	<a href="/checkout/[commerce_order:order_id]/order_information">Checkout and pay for this registration.</a>

To add this to your email, you would need to edit the email message body in HTML Source mode. At runtime, the commerce order ID token is replaced with the actual order ID value.

Note that the path includes "order_information" which is the default first page of checkout flow. If you have a custom checkout flow using a different path, use that instead.

**Override Wait List Messages**

This module displays messages when items in the cart are added to or removed from a waiting list. You can override these messages, or disable them, using an event subscriber.

In my_module.services.yml:

	services:
	  mymodule.waitlist_event_subscriber:
	  class: Drupal\my_module\EventSubscriber\WaitListEventSubscriber
	  tags:
	  - { name: event_subscriber }

In my_module/src/EventSubscriber/WaitListEventSubscriber.php:

	<?php
	
	namespace Drupal\my_module\EventSubscriber;
	
	use Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent;
	use Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvents;
	use Symfony\Component\EventDispatcher\EventSubscriberInterface;
	
	/**
	 * Provides a wait list event subscriber.
	 */
	class WaitListEventSubscriber implements EventSubscriberInterface {
	
	  /**
	   * Processes event fired when an item in the cart is added to a wait list.
	   *
	   * This event is common because it always occurs when a wait listed item is
	   * initially added to the cart.
	   *
	   * @param \Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent $event
	   *   The wait list event.
	   */
	  public function onAdd(CommerceRegistrationWaitListEvent $event) {
	    // Change the message.
	    \Drupal::messenger()->addWarning(t('An item in your cart has been added to a waiting list. You must complete checkout with the wait listed item in your cart to reserve your place on the waiting list. You will receive an email if space becomes available, with instructions on how to complete your reservation.'));
	    $event->setHandled(TRUE);
	  }
	
	  /**
	   * Processes event fired when an item in the cart is moved off the wait list.
	   *
	   * This event is rare, since usually items are moved off the wait list after
	   * checkout has already completed. However, it can happen if a wait listed
	   * item is added to the cart, and someone else cancels a previously completed
	   * registration before the user completes checkout.
	   *
	   * @param \Drupal\commerce_registration_waitlist\Event\CommerceRegistrationWaitListEvent $event
	   *   The wait list event.
	   */
	  public function onRemove(CommerceRegistrationWaitListEvent $event) {
	    // Disable the message. This is not advised, but is shown as an example.
	    $event->setHandled(TRUE);
	  }
	
	  /**
	   * {@inheritdoc}
	   */
	  public static function getSubscribedEvents(): array {
	    return [
	      CommerceRegistrationWaitListEvents::COMMERCE_REGISTRATION_WAITLIST_ADD => 'onAdd',
	      CommerceRegistrationWaitListEvents::COMMERCE_REGISTRATION_WAITLIST_REMOVE => 'onRemove',
	    ];
	  }
	
	}

**Extend or Replace Services**

Most of the functionality in this module is provided through services. You can extend or replace these services with your own classes. Follow the example at the top of this page: [https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/altering-existing-services-providing-dynamic-services](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/altering-existing-services-providing-dynamic-services)

GUEST CHECKOUT
--------------

Users who checkout wait listed registrations as a guest ("anonymous users") are not directly supported using the "out of the box" solution. These customers will receive the "space available" email, but the checkout link will not work because there is no way to assign an anonymous user session to the system generated order. Site builders who need to support guest checkout will need to modify the "space available" email to not include a checkout link, and will need to provide alternate instructions. One possibility is having the users register, and then having a site administrator manually assign the system generated order to that new user account using Commerce order administration.
