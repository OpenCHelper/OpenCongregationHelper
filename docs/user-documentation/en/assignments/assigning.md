---
type: documentation
icon: heroicon-o-clipboard-document-check
title: Assigning and returning
order: 1
---

Assigning a territory means recording that a person is working it, starting from today. Returning it closes the
assignment, which stays in the history of the territory.

## Assigning a territory

1. Open the territory (or use the **⋮** menu on its row in the **Territories** list).
2. Click **Assign Territory**.
3. In **Assigned to**, choose the person (you can type to search). Both users who can log in and virtual users are
   listed, sorted by name.
4. Optionally choose a **Campaign** (only campaigns that have not ended are offered).
5. Click **Create**.

If the territory has already been worked (assigned and returned) in the chosen campaign, a checkbox asks you to confirm
that it should go out again for it: a territory normally goes out only once per campaign.

![Assigning a territory](/docs/user-documentation/en/assignments/assets/assign-modal.webp)

The assignee receives a notification and an email (unless they are a virtual user), and the territory shows up as out in
the territories list.

> ℹ️ **Note:** a territory can have only one assignment in progress. **Assign Territory** is shown only for available
> territories: to give an assigned territory to someone else, return it first.

## Returning a territory

1. Open the territory and click **End Assignment** (or use the **⋮** menu on its row).
2. Check the assignee and the campaign, then click **End**.

![Returning a territory](/docs/user-documentation/en/assignments/assets/return-modal.webp)

The assignment is closed with today's date and the assignee is notified (unless they are a virtual user).

You can also return it from **Assignments** (and from the assignment history of a territory, a person or a campaign):
click **End Assignment** on the row of an assignment in progress, or at the top of the page of the assignment. To
record a return on another date, click **Edit** in the **⋮** menu of the row instead and set the **End Date** (see
[Assignment history](history#correcting-an-assignment)).

### Return requests

The assignee can ask to return the territory: on the page of the assignment (e.g. opened from the notification) they
click **Ask to return** and confirm. The members of the territory department are notified, and the page of the
assignment shows **Return requested on …** with **End Assignment**, which returns it for those whose role allows it.

## Assigning or returning many territories at once

In the **Territories** list, tick the territories, then open the **bulk actions** menu and choose:

- **Assign Massively**: choose the person (and optionally a campaign) and click **Submit**. Territories that are
  already out are skipped, as are those already worked in the chosen campaign, unless you tick **Also assign …**,
  which lists them;
- **End Assignments**: returns all the selected territories that are out, after a confirmation. The others are skipped,
  as are those your role doesn't allow you to return.

A message then tells how many territories were assigned or returned and lists the skipped ones, with the reason: it is
green when none was skipped, amber when some were, and red when none went through.

![Assigning many territories](/docs/user-documentation/en/assignments/assets/territories-bulk-assign.webp)

> 💡 **Tip:** combine the **Assignment Status** filter set to **Unassigned** with **Group by Zone** to quickly find
> all the available territories of a zone and assign them to the same person.

## Recording past assignments

To record an assignment with different dates (e.g. when copying the paper register), open **Assignments**, click
**New assignment** and fill in the territory, the assignee, the **Start Date** and, if already returned, the
**End Date** (neither can be in the future). The same checks apply: a territory cannot have two assignments in
progress, and terminated campaigns cannot be used.
