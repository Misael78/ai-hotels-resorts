# ChatGPT Content Assistance

This module has the below 3 features -
1. **Act as a content generator**
2. **Act as a content translator**
3. **Act as a content assistance tool** like creating images from text,
   extracting SEO keyowrds from content etc.

**Act as a Content Generator:**
It will add a link on the content add and edit page. On clicking this link, 
it will open a popup which will provide access to the ChatGPT content search. 
Content creators can copy the article from the popup and can create a new 
content with it. This will be highly beneficial to the content creators 
and editors as they will be able to generate content instantly with ChatGPT 
AI on the content creation page itself.

**Act as a Content Translator:**
It will show a "Translate using ChatGPT" on the content "Translate" tab against 
each enabled languages if translation is not present for that language. 
On clicking this link it will fetch the original content and will translate 
it using ChatGPT service.

**Act as a Content Assistance Tool:**
This will add a new tab called "OpenAI Content Assistance Tool" in the 
content admin page (admin/content) and will give you give you different options
like creating images from text, extracting SEO keyowrds from a content etc.

This module has two parts:

1. **OpenAI API Configuration page**: An "OpenAI API Settings" link will appear
   on the Administration > Configuration page from where you can update the API
   endpoint, access token and other required information to make the API Call.
2. **OpenAI utility tools** on the content admin, creation and translate page.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/chatgpt_plugin).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/chatgpt_plugin).


## Contents of this file

- Requirements
- Installation
- Configuration
- Usage
- Maintainers


## Requirements

This module requires no modules outside of Drupal core. But we need an OpenAI 
ChatGPT access token which will allow our Drupal system to make API calls to
the OpenAI ChatGPT. Here is how you can get the API token:
1. Please signup first in the OpenAI site at https://beta.openai.com/account
   and then log into the portal
2. When logged into the portal, please create your API access token at
   https://beta.openai.com/account/api-keys


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Configuration

1. Navigate to Administration > Extend and enable the module.
2. Navigate to Administration > Configuration > OpenAI API Settings
   to configure OpenAI API settings.
3. Select the GPT model you want to leverage for your application.
4. GPT3 Completion API endpoint is https://api.openai.com/v1/completions.
5. GPT3.5 Chat API endpoint is https://api.openai.com/v1/chat/completions.
6. Select the API model for GPT3 or GPT3.5 like "text-davinci-003"(gpt3),
   "gpt-3.5-turbo"(gpt3.5) etc.
7. DALL.E API endpoint is at https://api.openai.com/v1/images/generations
8. Enter your API Access token and other information. For more info check
   https://beta.openai.com/docs/api-reference/completions.
9. Provide a max token value to add a word limit in your output.
10. Also please select the content types for which you want the AI assistance.
10. Now a "ChatGPT Search" link will appear in content add and edit page.


## Usage

In the settings page enter your API access token and save it.

The OpenAI utility tool links will now appear in all the above mentioned pages
which will provide OpenAI tool access to our content editors.


## Maintainers

- Anup Sinha - [anup.sinha](https://www.drupal.org/u/anupsinha)
