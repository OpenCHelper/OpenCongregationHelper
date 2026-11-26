---
type: documentation
icon: heroicon-o-rocket-launch
title: Setting up the congregation
order: 1
---

This guide is for whoever starts using the application for the first time, after it has been installed (see the
[Setup guide](../setup)). Follow the steps in order.

## 1. Log in as root

Log in with the root user created during the installation, from the [setup wizard](../setup#from-the-browser) or
the terminal. Its name, logo and colour, administrators and features can be changed from the
[Settings](../administration/settings) and the [users](../administration/users).

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
