# Git Deployment Setup - Manual Steps

If the automated setup script doesn't work, run these commands **on your server** via SSH:

## 1. SSH into your server
```bash
ssh tdhuedhn@dgtts.org
```

## 2. Create a bare Git repository
```bash
mkdir -p ~/broxbhai.git
cd ~/broxbhai.git
git init --bare
```

## 3. Create the post-receive hook (auto-deploy on push)
```bash
cat > ~/broxbhai.git/hooks/post-receive << 'EOF'
#!/bin/bash
WORK_DIR="$HOME/broxbhai"
mkdir -p "$WORK_DIR"
git --work-tree="$WORK_DIR" --git-dir="$HOME/broxbhai.git" checkout -f master
echo "✓ Code deployed to $WORK_DIR"
EOF
chmod +x ~/broxbhai.git/hooks/post-receive
```

## 4. Back on your local machine, push to the bare repository
```powershell
cd H:\Web\broxbhai
git push -u origin master
```

## 5. Verify deployment
SSH into server and check:
```bash
ls -la ~/broxbhai/
```

---

## Troubleshooting

**Error: "Could not update working tree"**
- This means you're pushing to a non-bare repo. Follow steps 1-3 above to create a bare repo.

**Error: "Connection refused"**
- Check if the SSH host and port are correct in `.env`
- Verify SSH key permissions: `chmod 600 ~/.ssh/deploy_key`

**Error: "Permission denied (publickey)"**
- Your SSH key is not authorized on the server
- Add your SSH public key to `~/.ssh/authorized_keys` on the server
