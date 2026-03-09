# Git Terminal Commands

## Main entrypoint

```bash
npm run git
npm run git -- --help
```

- `npm run git` with no extra arguments opens a new terminal window.
- `npm run git -- ...` forwards the command in the current terminal.
- `npm run git-helper -- ...` always runs directly in the current terminal.

## Direct actions

```bash
npm run git -- clone <url> [dir]
npm run git -- pull [branch]
npm run git -- push [branch] [message] [--force]
npm run git -- merge <source> <target> [--squash]
npm run git -- branch-create <branch> [--from <base>] [--push]
npm run git -- branch-delete <branch> [--remote]
npm run git -- branch-list [--remote]
npm run git -- checkout <branch>
npm run git -- status
npm run git -- log [--n 10]
npm run git -- sync <base-branch>
npm run git -- tag <name> [message] [--push]
npm run git -- undo [--hard]
npm run git -- stash <save|pop|list|drop> [name]
npm run git -- clean
npm run git -- diff [branch]
npm run git -- smart-clone [repo-url] [dir]
```

## Location commands

```bash
npm run branch -- setup
npm run branch -- switch home
npm run branch -- switch office
npm run branch -- status
npm run branch -- sync
```

## Notes

- Running `npm run git` without arguments opens the interactive menu.
- Menu number selection is instant; single-digit options do not require `Enter`.
- Menu selection also accepts command names when you want to type them manually.
- At action prompts, type `back` to return one step or `main` to return to the main menu.
- Confirmation uses `Enter` to run, `Esc` to go back, and `M` to return to the main menu.
- `push` can auto-detect the branch from `.env` using `WORK_LOCATION`.
