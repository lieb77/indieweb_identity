# Indieweb Identity

## INTRODUCTION

The Indieweb Identity module provides a centralized system for managing your **Representative h-card**. It provides a settings form to manage your profile data (name, bio, avatar, and social links), and exposes the data via a block using a single directory component to output it as a machine-readable Microformats2 component.

The primary use cases for this module are:

- **Sovereign Identity:** Establishes a "source of truth" for your identity on your own domain, rather than relying on third-party silos.
- **Cross-Platform Verification:** Uses `rel="me"` attributes on social links to verify your ownership of profiles on services like Bluesky, Mastodon, and GitHub.
- **Machine Readability:** Provides the necessary metadata for IndieWeb services (like IndieAuth, Bridgy, and Webmentions) to discover your profile information automatically.

## REQUIREMENTS

Drupal core ^11.3

## INSTALLATION

Install via composer

## CONFIGURATION

1. **Permissions:** Grant the "Administer IndieWeb identity" permission to the appropriate roles.
2. **Identity Settings:** Navigate to *Configuration > Web services > IndieWeb Identity* to enter your name, bio, avatar, and social links (in YAML format).
3. **Block Placement:** Navigate to *Structure > Block Layout* and place the "IndieWeb H-Card" block. For optimal validation, place it in a region that appears on your homepage (e.g., Footer).
4. **Validation:** Use the link provided in the form's success message to verify your card at [IndieWebify.me](https://indiewebify.me).

## MAINTAINERS

Current maintainers for Drupal 11:

- Paul Lieberman (lieb) - https://www.drupal.org/u/lieb
