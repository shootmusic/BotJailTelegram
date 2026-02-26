#!/bin/bash
echo "🚀 Deploying to GitHub..."

git add .
git commit -m "Update bot $(date)"
git push origin main

echo "✅ Done! Push ke Railway otomatis"
