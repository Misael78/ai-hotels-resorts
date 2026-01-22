# ElevenLabs

## What is this

ElevenLabs is a great text-to-speech service that can create speech from any
text, even using custom trained voices. The module works as a provider for the
[AI module](https://www.drupal.org/project/ai) making it possible to create audio files form text, or even
replace speech inside audio files or remove noise from audio files.

This means that any third party library utilizing the [AI module](https://www.drupal.org/project/ai) for
text-to-speech and speech-to-speech  will make the ElevenLabs
module available. These makes them available for the AI Automator as well.

For more information on how to use the AI Automator
(previously AI Interpolator), check https://workflows-of-ai.com.

## Features
* It can do text to speech with predefined professional voices.
* It can do text to speech using your own voice.
* It can do speech to speech using an audio file and have it speak with your
voice.
* It can remove noise from audio files.

## Requirements
* Requires an account at [ElevenLabs](https://elevenlabs.io/). They have free trials.
* Requires the AI module.

## How to use as AI Automator type
1. Install the [AI module](https://www.drupal.org/project/ai).
2. Install this module.
3. Create a Drupal Key for ElevenLabs
3. Visit /admin/config/system/eleven-labs-settings and select your key.
4. Create some entity or node type with a simple text field.
5. Create an file field and set mp3.
6. Enable AI Automator checkbox and configure it.
7. Create an entity of the type you generated, fill in some text and save.
8. The audio file be filled out.

## Sponsors
* This module was supported by FreelyGive (https://freelygive.io/), your partner
in Drupal AI.
* Refactor work was done by [Kanopi Studios](https://kanopi.com/)
