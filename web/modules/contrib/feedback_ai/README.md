# Feedback AI Module

## Description

This module for Drupal utilizes OpenAI to provide sentiment analysis
for user feedback submitted on a Drupal website. By analyzing the feedback,
it categorizes the sentiment into Positive, Negative, or Neutral.
This categorization helps website administrators gain
insights into the overall sentiment of user responses, enabling them
to make data-driven decisions.

## Dependencies
 -https://www.drupal.org/project/views_data_export

## Requirements

- Drupal 9,10

## Installation

1. Ensure all dependencies are installed.
   - views_data_export
2. Enable the feedback_ai module.
3. Configure permissions to allow appropriate roles
   to access the Feedback AI Submissions.

## OpenAI API Keys

To retrieve your OpenAI keys, follow these steps:

1. Login to https://platform.openai.com
2. Go to API keys section. Follow the instructions provided
   to create your API keys.
3. Copy the API Key and a Secret Key.

## Configuration

- After enabling the module, configure the Feedback OpenAI Settings
  on the module configuration page
(`/admin/config/feedbackopenai/settings`).

## Usage

1. Adding the Feedback Form as a Page
   Navigate to the Feedback Form Page:

   Visit the path /feedback-ai or go to Content > Feedback AI on your website.
   The feedback form page should be accessible
   and ready for users to submit their feedback.

2. Adding the Feedback Form as a Block

   Go to Structure > Block layout.
   Locate the region where you want to place the feedback AI Form block
   and click Place block.
   Search for the Feedback AI and click Place block.

3. Viewing Feedback Submissions
   Navigate to the Feedback AI Submissions Page:

   Visit the path /feedback-ai-submissions or Content > Feedback Ai Submissions
   on your website.
   This page displays all feedback submissions along with
   their sentiment analysis.

   Use Filters and Export Data:
   - Use the exposed filters to filter Sentiment Rating or submission on.
   - Click the "Export to CSV" link to export the filtered submissions
     to a CSV file.

   Viewing Sentiment Rating Chart:
   - The list of submissions, the Sentiment Rating Chart is displayed
     as a pie chart.
   - It shows the feedback  rating as  Positive, Negative,
     and Neutral feedback.
   - Adding Sentiment Rating Chart as a Block:
     Go to Structure > Block layout.
     Locate the region where you want to place the Sentiment Rating Chart block
     and click Place block.
     Search for the Feedback AI Chart and click Place block.

## Troubleshooting

- If sentiment Rating are inaccurate or unavailable,
  check the module configuration settings.

## Maintainers

- [Maintainer Kumari Medha](https://www.drupal.org/u/medha-kumari)
