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

From **Assignments** there is no button to return a territory: click **Edit** on its row and set the **End Date** (see
[Assignment history](history#correcting-an-assignment)).

### Return requests

The assignee can ask to return the territory: on the page of the assignment (e.g. opened from the notification) they
click **Ask to return** and confirm. The members of the territory department are notified, and the page of the
assignment shows **Return requested on …** with **End Assignment**, which returns it for those whose role allows it.

## Assigning or returning many territories at once

In the **Territories** list, tick the territories, then open the **bulk actions** menu and choose:

- **Assign Massively**: choose the person (and optionally a campaign) and click **Submit**. Territories that are
  already out are skipped, without a message;
- **End Assignments**: returns all the selected territories that are out, after a confirmation. The others are skipped,
  without a message.

![Assigning many territories](/docs/user-documentation/en/assignments/assets/territories-bulk-assign.webp)

> 💡 **Tip:** combine the **Assignment Status** filter set to **Unassigned** with **Group by Zone** to quickly find
> all the available territories of a zone and assign them to the same person.

## Recording past assignments

To record an assignment with different dates (e.g. when copying the paper register), open **Assignments**, click
**New assignment** and fill in the territory, the assignee, the **Start Date** and, if already returned, the
**End Date** (neither can be in the future). The same checks apply: a territory cannot have two assignments in
progress, and terminated campaigns cannot be used.
