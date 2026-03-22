#!/usr/bin/env bash
# Install Python 3.8 on production server so Ansible deploy can run.
# You will be prompted for your sudo password on the server.
# Run from repo root or trellis/:  ./trellis/install-python-production.sh

set -e
cd "$(dirname "$0")"
# Use same host as deploy (from hosts/production)
SERVER="64.23.203.166"
USER="admin"

echo "Connecting to $USER@$SERVER to install Python 3.8 (you may be prompted for sudo password)..."
ssh -t "$USER@$SERVER" "sudo apt-get update && sudo apt-get install -y python3.8 && echo 'Done.' && which python3.8"
