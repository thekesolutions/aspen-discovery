#!/bin/bash
# Sourced by start.sh and cron.sh, the containers that run Aspen's PHP and Java code. They must
# write files/, images/ and fonts/ to the data volume, where Apache serves them from
# (data-alias.conf), not into the image's own copy.
# Requires $SITE_NAME and a log_info function.

link_data_dirs() {
	local dataDir="/data/aspen-discovery/$SITE_NAME"
	local localDir="/usr/local/aspen-discovery/code/web"
	local dir source dest

	for dir in files images fonts; do
		source="$localDir/$dir"
		dest="$dataDir/$dir"

		# Ensure persistent target directory exists
		mkdir -p "$dest"

		# Move original data only if source is a real directory and target is empty
		if [ -d "$source" ] && [ ! -L "$source" ] && [ "$(ls -A "$dest")" == "" ]; then
			mv "$source"/* "$dest"/ 2>/dev/null || true
		fi

		# Remove source only if it's a real directory (not a symlink)
		# This is required because ln -sfn can't replace a directory
		if [ -d "$source" ] && [ ! -L "$source" ]; then
			rm -rf "$source"
		fi

		# Create symlink atomically (ln -sfn replaces existing symlink in one operation)
		ln -sfn "$dest" "$source"

		log_info "Created symlink: $source → $dest"
	done
}
