---
type: documentation
icon: heroicon-o-rocket-launch
title: Setting up the congregation
order: 1
---

This guide is for whoever starts using the application for the first time, after it has been installed (see the
[Setup guide](../setup)). Follow the steps in order.

## 1. Log in as root

Log in with the root user created during the installation: on a new installation the **setup wizard** opens, with four
steps:

1. **Installation**: the checks of the server (the same as `php artisan och:doctor`). Fix what is marked as an error or
   a warning, send a **test email** to check that the emails arrive, and create the missing storage link or keys of
   the AI assistants with their buttons;
2. **Congregation**: the name of the app, the address of the Kingdom Hall, the logo and the primary colour;
3. **Administrators**: the people who will manage the app with you. They become super administrators and receive an
   email to choose their password;
4. **Features**: the [AI assistants](../basics/profile), the [sharing](../assignments/sharing) of the assignments and
   the requirements of the accounts.

Everything can be changed later from the [Settings](../administration/settings) and the
[users](../administration/users). You can also **Skip** the wizard: until it is completed or skipped, a reminder is
shown at the top of the pages.

Then open your [profile](../basics/profile) to set your name, your preferred language and an emergency contact.

## 2. Create the zones

Open **Zones** and create a zone for each area of the congregation territory, choosing clearly different colors
(see [Zones](../territories/zones)).

## 3. Load the territories

- If you have a list of territories, [import](../territories/importing) it from a CSV file: zones that do not exist yet
  are created automatically.
- Otherwise [create them](../territories/creating) one by one.

Then [draw the map](../territories/maps) of each territory, or attach a scan of the paper card.

> 💡 **Tip:** check the **Map State** filter of the territories list to find the territories that still lack a map.

## 4. Create the roles and the users

1. Create the [roles](../administration/roles) you need, e.g. *publisher* and *territory servant*.
2. Create the [users](../administration/users): give **Allow access** and a role to those who will use the application,
   create everybody else as virtual users.
3. [Impersonate](../administration/users) a user of each role to check what they see.

## 5. Copy the current situation

For each territory that is currently out, [assign it](../assignments/assigning) to the right person. Use
**Assignments → New assignment** if you also want to record the real start date or past assignments from the paper
register. The people who can log in are notified of each assignment in progress, not of the ones recorded as
already returned.

## 6. Organize with tags

Add [tags](../administration/tags) to territories and zones (e.g. *Apartment blocks*, *Intercom only*) to make them
easier to filter and to search.

You are ready: the [dashboard](../basics/dashboard) now shows the map of the whole territory.
