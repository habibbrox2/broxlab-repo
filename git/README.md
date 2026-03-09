# Git Terminal

Single terminal entrypoint for the BroxBhai git workflow.

## Main file

- `git-terminal.cjs` - interactive menu + direct commands
- `GIT-HELPER.md` - command reference

## Quick usage

```bash
# Open interactive menu in a new terminal window
npm run git

# Show direct command help in current window
npm run git -- --help

# Clone repository
npm run clone -- https://github.com/user/repo.git

# Smart opposite-branch clone
npm run sync

# Location management
npm run branch -- status
npm run branch -- switch home
npm run branch -- switch office

# Common direct commands
npm run git -- status
npm run git -- pull
npm run git -- push "commit message"
```

Interactive menu notes:

- single-digit menu options run immediately without pressing `Enter`
- type `back` to move one step back during action input
- type `main` to jump back to the main menu during action input

## Package scripts

```json
"scripts": {
  "git": "node git/git-launcher.cjs",
  "git-helper": "node git/git-terminal.cjs",
  "clone": "node git/git-terminal.cjs clone",
  "branch": "node git/git-terminal.cjs location",
  "sync": "node git/git-terminal.cjs smart-clone"
}
```
