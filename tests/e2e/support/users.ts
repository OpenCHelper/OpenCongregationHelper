/**
 * The users seeded by `E2eSeeder` (and the root user of `serve.sh`), by role.
 */
export const users = {
    root: { email: 'root@e2e.test', password: 'RootPassword123', name: 'Root User' },
    admin: { email: 'admin@e2e.test', password: 'password', name: 'Adam Admin' },
    servant: { email: 'servant@e2e.test', password: 'password', name: 'Simon Servant' },
    overseer: { email: 'overseer@e2e.test', password: 'password', name: 'Oscar Overseer' },
    group: { email: 'group@e2e.test', password: 'password', name: 'Gino Group' },
    publisher: { email: 'publisher@e2e.test', password: 'password', name: 'Paul Publisher' },
    norole: { email: 'norole@e2e.test', password: 'password', name: 'Nora Norole' },
} as const;

export type Role = keyof typeof users;

export const roles = Object.keys(users) as Role[];

/**
 * The file of the signed-in browser state of a user (see `auth.setup.ts`).
 */
export const storageStateOf = (role: Role): string =>
    `tests/e2e/.auth/${process.env.E2E_APP_INSTANCE ?? 'app'}/${role}.json`;
