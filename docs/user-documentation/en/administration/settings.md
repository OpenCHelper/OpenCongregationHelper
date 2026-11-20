---
type: documentation
icon: heroicon-o-adjustments-horizontal
title: Settings
order: 5
---

The **Settings** pages let the super administrators change the options of the application without editing the `.env`
file on the server. Open **Administration → Settings**: the pages are shown only to the users with the `super_admin`
role. The options are grouped by area, with one page each in the menu on the side.

## General

![General settings](/docs/user-documentation/en/administration/assets/settings-general.webp)

- **Kingdom Hall address**: the address of the Kingdom Hall of the congregation (optional);
- **Logo**: shown in the top bar instead of the name (at most 2 MB);
- **Primary colour**: the colour of the buttons, links and selected items, for the users who did not choose their own
  in their UI preferences.

## Territories

![Territory settings](/docs/user-documentation/en/administration/assets/settings-territories.webp)

- **Default map centre**: where the maps open while no territory has a map yet, as `latitude, longitude` (e.g.
  `41.159063, -74.255188`). Afterwards the maps open on the territories;
- **Show personal data on shared pages**: when on, the public page of a [shared assignment](../assignments/sharing)
  also shows the name of the assignee and the notes of the territory.

Under **Assignments out for too long** the levels are listed in order, each with its colour, and the bar below draws
them to scale while you type:

- the days after which an assignment still in progress is out for too long and then lost: the latter must be more;
- the **day** and **time** (in the timezone chosen on the **General** page) of the weekly reminders sent to the
  assignees of the assignments out for too long. They are sent only if the scheduler is running at that time, as a cron
  job or as a long-running process (e.g. with Supervisor); on a hosting without cron, the external service must call it
  then (see the [Setup guide](../setup)).

## Campaigns

![Campaign settings](/docs/user-documentation/en/administration/assets/settings-campaigns.webp)

The days before its end from which a campaign is **expiring** and **almost expired**: the latter must be fewer. The
statuses are listed in order, with the colours of the campaign badges, and the bar below draws them to scale while you
type.

Click **Save changes** to apply them: they take effect immediately, for every user.

## Default values

Under each option, **Default** shows its value before it is changed here: for the map centre and the personal data, the
value set in `.env` by whoever installed the application (see the [Setup guide](../setup)). These two options keep
following `.env` until they are changed here; to make one follow `.env` again, set it back to its default value and
save. The name, language and timezone of the application are set only in `.env`.
