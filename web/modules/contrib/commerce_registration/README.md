CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers


INTRODUCTION
------------

Commerce Registration provides Drupal Commerce with the ability to sell registrations via the [Registration](https://drupal.org/project/registration) module. It provides checkout panes, order processors and other Commerce components so you can quickly and easily integrate selling registrations into your store. For example, you can setup an e-commerce site that sells tickets to events - each ticket can be considered an event registration.

 * For a full description of the module visit the [Commerce Registration project page](https://www.drupal.org/project/commerce_registration).

 * To submit bug reports and feature suggestions, or to track changes, visit the [Commerce Registration project issues queue](https://www.drupal.org/project/issues/commerce_registration).


REQUIREMENTS
------------

Commerce Registration requires the following modules:

* [Drupal Commerce](https://www.drupal.org/project/commerce)
* [Registration](https://www.drupal.org/project/registration)

INSTALLATION
------------

Install the Commerce Registration module as you would normally install a contributed Drupal module. Visit [https://www.drupal.org/node/1897420](https://www.drupal.org/node/1897420) for further information.

If you have previously installed Drupal Commerce and Registration, and added registration fields to any entity types already, Commerce Registration will ensure that no registration fields have been added to Commerce product types. Installation will fail if any are found - in this case you will need to delete the registration fields and try again. This check is needed because registrations fields must only be added to Commerce product **variation** types when using Commerce Registration, since product variations are the purchasable entities.

CONFIGURATION
-------------

1. Following the instructions for the Registration module, create at least one registration bundle (or type) at /admin/structure/registration-types, much like you would a content type. For example, add a registration type named Conference or Seminar.
1. Add a registration field to any Commerce product **variation** type you want to enable registrations for at /admin/commerce/config/product-variation-types/***variation\_type_name***/edit/fields. For example, you may have a product variation type named Event that you want to enable Conference registrations for - add a field to that product variation type. Provide appropriate default registration settings for the field as needed.
1. Configure the Form Display for the product variation type you added the registration field to. Typically you would want the registration field to be editable instead of disabled. The setting for whether a Register tab should be displayed for product variations of the configured type can be ignored, since the Register form will be replaced by a checkout pane.
1. Edit product variations that have a registration field, and select a registration type to use for each variation. This step may not be needed depending on the defaults you used when adding the registration field.
1. View the products that have at least one product variation with a registration field set - these products will have a Manage Registrations tab. This tab is for the product as a whole (variations with a registration field set also have a tab, that applies only to that variation). Using the local tasks on the product version of the Manage Registrations tab, you can view all registrations for that product, edit settings for all the product's variations, and send emails to all registrants for that product.
1. (Optional) Edit the "Add to cart" form display for the order item type you are using for your registration enabled products. Choose the "Product variation title (spaces available)" widget if you want users to know how many registrations are still available for purchase for each item when they are viewing a given product.
1. Edit the order checkout flow you will be using for your store at /admin/commerce/config/checkout-flows. If users checking out can purchase registrations for others, or there is data to collect during registration (this most commonly applies if you add more fields to your registration type), you can enable the Registration Information checkout pane and place it in the **Order Information** step. This pane displays a registration form that collects the required field data. If users checking out can only purchase registrations for themselves, and there is no data to collect during registration, then keep the Registration Information pane disabled, and ensure the Registration Process pane is enabled and part of the **Payment** step. The Registration Process pane automatically creates registrations for the person checking out. Note that if you are using the Registration Process checkout pane, it must come **before** the Payment Process checkout pane in the Payment step, otherwise registrations will not be created during checkout.
1. (Optional) If you enabled the Registration Information checkout pane, you can configure a "checkout" form display that is different than the default form display. This allows you have one set of fields available to site administrators who may need to edit a registration, and a different set of fields for users who are completing a registration form during checkout. The Registration Information pane will use the "checkout" form display if it exists, otherwise it will use the default form display.
1. (Optional) If you want users checking out to put a "hold" on registrations that have a limited quantity available, make the "Held" state the default state for new registrations. Also enable either the Registration Information pane or the Registration Process pane (but not both) within the Order Information step of the order checkout flow. To release the hold if the user does not complete checkout promptly, either configure the length of the hold on the registration type (after which the registration will be cancelled), or configure the order type to delete abandoned carts (which will delete the registration altogether).
1. Unless you override the functionality of the Commerce Registration module using a custom module, registrations are put in pending status after order submission, and into complete status after full payment is received for an order. Registrations created through the checkout process are visible in the Commerce Registrations listing at /admin/commerce/registrations.

MAINTAINERS
-----------

 * John Oltman - [https://www.drupal.org/u/johnoltman](https://www.drupal.org/u/johnoltman)
 * Joseph Pontani (jpontani) - [https://www.drupal.org/u/jpontani](https://www.drupal.org/u/jpontani)
