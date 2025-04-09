#!/data/data/com.termux/files/usr/bin/bash

# Config - Set this once
GIT_USERNAME="YourUsername"
GIT_EMAIL="youremail@example.com"
GIT_REPO="https://github.com/YourUsername/YourRepo.git"

# Git setup
git config user.name "$GIT_USERNAME"
git config user.email "$GIT_EMAIL"

# Infinite Watch Loop
while true; do
    CHANGES=$(git status --porcelain)

    if [ ! -z "$CHANGES" ]; then
        echo "Changes detected. Committing and pushing..."
        git add .
        git commit -m "Auto-update on $(date '+%Y-%m-%d %H:%M:%S')"
        git push origin main
        echo "Push complete."
    else
        echo "No changes found at $(date '+%H:%M:%S')"
    fi

    sleep 10  # Check every 10 seconds
done