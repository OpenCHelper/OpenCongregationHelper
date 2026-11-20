---
type: documentation
icon: heroicon-o-shield-check
title: Roles and permissions
order: 3
---

What each user can see and do is decided by their **roles**. A role is a named set of **permissions**. Open
**Administration → Roles** to manage them.

![Roles list](/docs/user-documentation/en/administration/assets/roles-list.webp)

## The super administrator

The **super_admin** role (*Super Admin*) can do everything, including the actions reserved to it:

- assigning roles to users;
- impersonating users.

## Creating or editing a role

1. Click **New Role** (or **Edit** on an existing one).
2. Enter the **Name** (e.g. `publisher`). Leave **Guard Name** set to `web`.
3. Tick the permissions in the **Resources**, **Pages** and **Widgets** tabs. **Select all** in each box ticks all the
   permissions of that resource; the **Select All** toggle at the top ticks everything.
4. Click **Save changes**.

![Editing a role](/docs/user-documentation/en/administration/assets/role-edit.webp)

## Meaning of the permissions

For every resource (Territory, Territory Zone, Territory Assignment, Campaign, User, Role, …) there are the same
permissions:

| Permission | Allows to |
|------------|-----------|
| **View Any** | open the list of that resource |
| **View** | open the details of a record |
| **Create** / **Update** / **Delete** | create, edit, delete records |
| **Restore**, **Force Delete** (and their *Any* versions) | restore deleted records, delete them permanently |
| **Replicate**, **Reorder** | not used at the moment |

Some permissions have a special meaning:

- **Create** on *Territory Assignment* allows to **assign** territories, **Update** allows to **return** them;
- without **View Any** on *Territory Assignment*, the user only sees **their own** assignments;
- the *Pages* and *Widgets* tabs control, for example, the access to the profile page and the dashboard map.

## Suggested roles

| Role | Suggested permissions |
|------|-----------------------|
| **Publisher** | View / View Any on Territory and Territory Zone, View on Territory Assignment, the profile page and the dashboard map. Publishers see the territories and only their own assignments. |
| **Territory servant** | Everything on Territory, Territory Zone, Territory Assignment and Campaign, plus View / View Any on User. |
| **Administrator** | The **super_admin** role. |

> 💡 **Tip:** after changing a role, [impersonate](users) a user with that role to check what they see.
